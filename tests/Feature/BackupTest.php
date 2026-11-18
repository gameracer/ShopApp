<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use PDO;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // Backups copy a real database file on their own connection, so give them one
        // (the test app itself keeps using the in-memory database).
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shopapp-backup-test-'.uniqid();
        File::ensureDirectoryExists($this->dir);
        $db = $this->dir.DIRECTORY_SEPARATOR.'shop.sqlite';
        (new PDO('sqlite:'.$db))->exec("CREATE TABLE products (name TEXT); INSERT INTO products VALUES ('Sugar 1kg')");

        config([
            'database.connections.sqlite.database' => $db,
            'app.backup_path' => $this->dir.DIRECTORY_SEPARATOR.'backups',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_owner_saves_backup_settings(): void
    {
        $owner = User::factory()->owner()->create();
        $copy = $this->dir.DIRECTORY_SEPARATOR.'usb';

        $this->actingAs($owner)->put('/settings/backups', [
            'backup_enabled' => '1',
            'backup_frequency' => 'weekly',
            'backup_weekday' => '5',
            'backup_time' => '21:30',
            'backup_keep' => '8',
            'backup_copy_path' => $copy,
            'backup_timezone' => 'Africa/Dar_es_Salaam',
        ])->assertRedirect(route('settings.edit').'#backups')->assertSessionHasNoErrors();

        $this->assertSame('weekly', Setting::get('backup_frequency'));
        $this->assertSame('5', Setting::get('backup_weekday'));
        $this->assertSame('21:30', Setting::get('backup_time'));
        $this->assertSame('8', Setting::get('backup_keep'));
        $this->assertSame('0', Setting::get('backup_on_close'));
        $this->assertSame('Africa/Dar_es_Salaam', Setting::get('backup_timezone'));
        $this->assertDirectoryExists($copy);

        $this->actingAs($owner)->get('/settings')->assertOk()->assertSee('Backups')->assertSee('No backup yet');
    }

    public function test_backup_settings_are_validated_and_owner_only(): void
    {
        $owner = User::factory()->owner()->create();
        $seller = User::factory()->seller()->create();
        $valid = ['backup_frequency' => 'daily', 'backup_weekday' => '0', 'backup_time' => '20:00', 'backup_keep' => '14'];

        $this->actingAs($owner)->put('/settings/backups', ['backup_frequency' => 'yearly', 'backup_time' => '25:00', 'backup_keep' => '0'] + $valid)
            ->assertSessionHasErrors(['backup_frequency', 'backup_time', 'backup_keep']);
        $this->actingAs($owner)->put('/settings/backups', ['backup_copy_path' => 'relative\folder'] + $valid)
            ->assertSessionHasErrors('backup_copy_path');

        $this->actingAs($seller)->put('/settings/backups', $valid)->assertForbidden();
        $this->actingAs($seller)->post('/backups')->assertForbidden();
    }

    public function test_back_up_now_writes_a_copy_and_it_can_be_downloaded(): void
    {
        $owner = User::factory()->owner()->create();
        Setting::put(['backup_copy_path' => $this->dir.DIRECTORY_SEPARATOR.'usb']);

        $this->actingAs($owner)->post('/backups')->assertRedirect()->assertSessionHasNoErrors();

        $files = app(BackupService::class)->list();
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^shopapp-\d{8}-\d{6}-manual\.sqlite$/', $files[0]['name']);
        $this->assertFileExists($this->dir.'/usb/'.$files[0]['name']);
        $this->assertNotNull(Setting::get('backup_last_at'));

        $backup = new PDO('sqlite:'.config('app.backup_path').'/'.$files[0]['name']);
        $this->assertSame('Sugar 1kg', $backup->query('SELECT name FROM products')->fetchColumn());

        $this->actingAs($owner)->get('/backups/'.$files[0]['name'])->assertOk()->assertDownload($files[0]['name']);
        $this->actingAs($owner)->get('/backups/..%2F.env')->assertNotFound();
        $this->actingAs($owner)->get('/settings')->assertSee($files[0]['name']);
    }

    public function test_daily_backup_is_due_after_its_time_in_the_shops_timezone(): void
    {
        Setting::put(['backup_frequency' => 'daily', 'backup_time' => '20:00', 'backup_timezone' => 'Africa/Dar_es_Salaam']);
        $backups = app(BackupService::class);

        // 16:30 UTC = 19:30 in Dar es Salaam; yesterday's backup was taken after yesterday's 20:00.
        Carbon::setTestNow('2026-11-18 16:30:00');
        Setting::put(['backup_last_at' => '2026-11-17T17:05:00+00:00']);
        $this->assertFalse($backups->isDue());
        $this->assertSame('2026-11-18 20:00', $backups->nextRun()->format('Y-m-d H:i'));

        Carbon::setTestNow('2026-11-18 17:01:00'); // 20:01 local
        $this->assertTrue($backups->isDue());

        $this->artisan('backup:run --if-due')->assertSuccessful();
        $this->assertCount(1, $backups->list());
        $this->assertStringEndsWith('-auto.sqlite', $backups->list()[0]['name']);
        $this->assertFalse($backups->isDue());

        // Computer off at 20:00 the next day: the backup is made when it is turned on again.
        Carbon::setTestNow('2026-11-20 06:00:00');
        $this->assertTrue($backups->isDue());

        Setting::put(['backup_enabled' => '0']);
        $this->assertFalse($backups->isDue());
        $this->assertNull($backups->nextRun());
    }

    public function test_weekly_and_hourly_schedules(): void
    {
        $backups = app(BackupService::class);
        Carbon::setTestNow('2026-11-18 10:15:00'); // a Wednesday

        Setting::put(['backup_frequency' => 'weekly', 'backup_weekday' => '5', 'backup_time' => '18:00', 'backup_timezone' => 'UTC']);
        $this->assertSame('2026-11-13 18:00', $backups->lastSlot()->format('Y-m-d H:i')); // last Friday

        Setting::put(['backup_weekday' => '3', 'backup_time' => '09:00']);
        $this->assertSame('2026-11-18 09:00', $backups->lastSlot()->format('Y-m-d H:i')); // today

        Setting::put(['backup_frequency' => 'hourly', 'backup_last_at' => '2026-11-18T10:05:00+00:00']);
        $this->assertFalse($backups->isDue());
        Carbon::setTestNow('2026-11-18 11:00:00');
        $this->assertTrue($backups->isDue());
    }

    public function test_old_automatic_backups_are_deleted_but_others_kept(): void
    {
        Setting::put(['backup_keep' => '3']);
        $folder = config('app.backup_path');
        File::ensureDirectoryExists($folder);
        for ($i = 1; $i <= 5; $i++) {
            touch($old = "{$folder}/shopapp-2026010{$i}-200000-auto.sqlite", strtotime("2026-01-0{$i} 20:00"));
            touch("{$folder}/shopapp-2026010{$i}-120000-manual.sqlite", strtotime("2026-01-0{$i} 12:00"));
        }
        touch("{$folder}/notes.txt");

        app(BackupService::class)->run('auto');

        $names = array_column(app(BackupService::class)->list(), 'name');
        $this->assertCount(3, array_filter($names, fn ($n) => str_ends_with($n, '-auto.sqlite')));
        $this->assertNotContains('shopapp-20260103-200000-auto.sqlite', $names);
        $this->assertContains('shopapp-20260105-200000-auto.sqlite', $names);
        $this->assertCount(5, array_filter($names, fn ($n) => str_ends_with($n, '-manual.sqlite')));
        $this->assertFileExists("{$folder}/notes.txt");
    }

    public function test_backup_on_close_respects_the_setting_and_skips_right_after_a_backup(): void
    {
        $backups = app(BackupService::class);

        Setting::put(['backup_on_close' => '0']);
        $this->assertNull($backups->runOnClose());

        Setting::put(['backup_on_close' => '1']);
        $this->assertNotNull($backups->runOnClose());
        $this->assertNull($backups->runOnClose()); // a backup was made moments ago
    }

    public function test_a_failed_backup_is_reported(): void
    {
        config(['database.connections.sqlite.database' => $this->dir.'/missing.sqlite']);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/backups')->assertSessionHasErrors('backup');
        $this->artisan('backup:run')->assertFailed();
        $this->assertNotEmpty(Setting::get('backup_last_error'));
        $this->actingAs($owner)->get('/settings')->assertSee('Last backup problem');
    }
}
