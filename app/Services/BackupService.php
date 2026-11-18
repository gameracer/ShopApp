<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Backups of the shop database.
 *
 * A backup is a full copy of the SQLite database (shopapp-YYYYMMDD-HHMMSS-<label>.sqlite)
 * written to the backup folder (config app.backup_path; the desktop app points it at
 * %APPDATA%\ShopApp\backups) and, if the owner chose one, copied to a second folder
 * such as a USB drive or a OneDrive/Google Drive folder.
 *
 * Automatic backups run on the schedule set in Settings → Backups. `backup:run --if-due`
 * is called every few minutes (by the desktop app, or by the Laravel scheduler on a
 * server) and only backs up when a scheduled time has passed since the last backup, so
 * a backup missed while the computer was off is taken as soon as it is back on.
 */
class BackupService
{
    public const FREQUENCIES = ['hourly', 'daily', 'weekly'];

    /** Labels of backups made by the schedule; only these are trimmed to the owner's "keep" number. */
    public const AUTOMATIC_LABELS = ['auto', 'closing'];

    /** Manual, before-update and before-restore backups: this many of the newest are kept. */
    public const KEEP_OTHER = 30;

    /** A backup when ShopApp closes is skipped if one was made this recently. */
    public const ON_CLOSE_MIN_GAP_MINUTES = 10;

    private const FILE_PATTERN = '/^shopapp-\d{8}-\d{6}-[a-z0-9.-]+\.sqlite$/';

    public function folder(): string
    {
        return rtrim(config('app.backup_path'), '\\/');
    }

    public function copyFolder(): ?string
    {
        $path = trim((string) Setting::get('backup_copy_path'));

        return $path === '' ? null : rtrim($path, '\\/');
    }

    /**
     * Write a backup now. Returns the path of the new file.
     *
     * @throws RuntimeException when the database cannot be copied
     */
    public function run(string $label = 'manual'): string
    {
        $label = preg_replace('/[^a-z0-9.-]+/', '-', strtolower($label)) ?: 'manual';

        try {
            File::ensureDirectoryExists($this->folder());
            $stamp = $this->now()->format('Ymd-His');
            $dest = $this->folder().DIRECTORY_SEPARATOR."shopapp-{$stamp}-{$label}.sqlite";
            $this->copyDatabase($dest);
        } catch (Throwable $e) {
            $this->recordStatus(error: $e->getMessage());
            Log::error('Backup failed: '.$e->getMessage());
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $copyError = null;
        if ($copy = $this->copyFolder()) {
            try {
                File::ensureDirectoryExists($copy);
                if (! File::copy($dest, $copy.DIRECTORY_SEPARATOR.basename($dest))) {
                    throw new RuntimeException('could not write the file');
                }
                $this->prune($copy);
            } catch (Throwable $e) {
                $copyError = __('The backup was saved, but copying it to :folder failed: :error', ['folder' => $copy, 'error' => $e->getMessage()]);
                Log::warning($copyError);
            }
        }

        $this->prune($this->folder());
        $this->recordStatus(at: now()->toIso8601String(), error: $copyError);
        Log::info("Backup written: {$dest}");

        return $dest;
    }

    /** Run a scheduled backup if one is due. Returns the new file, or null if nothing was due. */
    public function runIfDue(): ?string
    {
        return $this->isDue() ? $this->run('auto') : null;
    }

    /** Backup when the desktop app closes, if the owner turned it on. */
    public function runOnClose(): ?string
    {
        if (! $this->enabled() || Setting::get('backup_on_close') !== '1') {
            return null;
        }
        $last = $this->lastBackupAt();
        if ($last && $last->diffInMinutes(now(), true) < self::ON_CLOSE_MIN_GAP_MINUTES) {
            return null;
        }

        return $this->run('closing');
    }

    public function enabled(): bool
    {
        return Setting::get('backup_enabled') === '1';
    }

    public function isDue(): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        $last = $this->lastBackupAt();

        return $last === null || $last->lessThan($this->lastSlot());
    }

    /** The most recent scheduled backup time that has already passed. */
    public function lastSlot(): CarbonImmutable
    {
        $now = $this->now();
        [$hour, $minute] = $this->time();

        switch (Setting::get('backup_frequency')) {
            case 'hourly':
                return $now->startOfHour();

            case 'weekly':
                $weekday = (int) Setting::get('backup_weekday');
                $slot = $now->setTime($hour, $minute)->subDays(($now->dayOfWeek - $weekday + 7) % 7);

                return $slot->greaterThan($now) ? $slot->subWeek() : $slot;

            default: // daily
                $slot = $now->setTime($hour, $minute);

                return $slot->greaterThan($now) ? $slot->subDay() : $slot;
        }
    }

    /** When the next automatic backup will run (in the shop's timezone). */
    public function nextRun(): ?CarbonImmutable
    {
        if (! $this->enabled()) {
            return null;
        }
        if ($this->isDue()) {
            return $this->now();
        }
        $slot = $this->lastSlot();

        return match (Setting::get('backup_frequency')) {
            'hourly' => $slot->addHour(),
            'weekly' => $slot->addWeek(),
            default => $slot->addDay(),
        };
    }

    public function lastBackupAt(): ?CarbonImmutable
    {
        $value = Setting::get('backup_last_at');

        return $value ? CarbonImmutable::parse($value)->setTimezone($this->timezone()) : null;
    }

    public function lastError(): ?string
    {
        return Setting::get('backup_last_error') ?: null;
    }

    /**
     * Backups in the backup folder, newest first.
     *
     * @return list<array{name: string, size: int, at: CarbonImmutable, automatic: bool}>
     */
    public function list(?string $folder = null): array
    {
        $folder ??= $this->folder();
        if (! is_dir($folder)) {
            return [];
        }

        $files = [];
        foreach (File::files($folder) as $file) {
            if (! preg_match(self::FILE_PATTERN, $file->getFilename())) {
                continue;
            }
            $files[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'at' => CarbonImmutable::createFromTimestamp($file->getMTime())->setTimezone($this->timezone()),
                'automatic' => $this->isAutomatic($file->getFilename()),
            ];
        }
        usort($files, fn ($a, $b) => [$b['at'], $b['name']] <=> [$a['at'], $a['name']]);

        return $files;
    }

    /** Full path of a backup in the backup folder, or null if there is no such backup. */
    public function path(string $name): ?string
    {
        if (! preg_match(self::FILE_PATTERN, $name)) {
            return null;
        }
        $path = $this->folder().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    /**
     * The shop's timezone, used for the backup time: the one saved from the owner's browser,
     * else this computer's (the desktop app passes it as SHOPAPP_TIMEZONE), else the app's.
     */
    public function timezone(): string
    {
        $zones = timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC);
        foreach ([Setting::get('backup_timezone'), config('app.local_timezone')] as $tz) {
            if (is_string($tz) && $tz !== '' && in_array($tz, $zones, true)) {
                return $tz;
            }
        }

        return config('app.timezone');
    }

    /** Keep the newest automatic backups (owner's setting) and the newest KEEP_OTHER other backups. */
    private function prune(string $folder): void
    {
        $keep = max(1, (int) Setting::get('backup_keep'));
        $backups = $this->list($folder);

        $automatic = array_values(array_filter($backups, fn ($b) => $b['automatic']));
        $other = array_values(array_filter($backups, fn ($b) => ! $b['automatic']));

        foreach ([...array_slice($automatic, $keep), ...array_slice($other, self::KEEP_OTHER)] as $old) {
            File::delete($folder.DIRECTORY_SEPARATOR.$old['name']);
        }
    }

    private function isAutomatic(string $name): bool
    {
        preg_match('/^shopapp-\d{8}-\d{6}-(.+)\.sqlite$/', $name, $m);

        return in_array($m[1] ?? '', self::AUTOMATIC_LABELS, true);
    }

    /**
     * Consistent copy of the live database, safe while the app is in use. It runs on its
     * own connection because VACUUM cannot run inside the app's open transaction.
     */
    private function copyDatabase(string $dest): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'sqlite') {
            throw new RuntimeException(__('Backups work with the SQLite database only. Back up this database with its own tools.'));
        }
        $source = $config['database'] ?? '';
        if ($source === '' || $source === ':memory:' || ! is_file($source)) {
            throw new RuntimeException(__('The database file was not found.'));
        }
        if (file_exists($dest)) {
            File::delete($dest);
        }

        $db = new PDO('sqlite:'.$source, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('PRAGMA busy_timeout = 10000');
        $db->exec('VACUUM INTO '.$db->quote($dest));
        $db = null;

        if (! is_file($dest) || filesize($dest) === 0) {
            throw new RuntimeException(__('The backup file could not be written.'));
        }
    }

    private function recordStatus(?string $at = null, ?string $error = null): void
    {
        $values = ['backup_last_error' => $error ?? ''];
        if ($at) {
            $values['backup_last_at'] = $at;
        }
        try {
            Setting::put($values);
        } catch (Throwable) {
            // Status is informative only; never let it hide the backup result.
        }
    }

    /** @return array{int, int} */
    private function time(): array
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) Setting::get('backup_time'), $m)) {
            return [(int) $m[1], (int) $m[2]];
        }

        return [20, 0];
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }
}
