<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The in-app user manual (docs/manual/<locale>.md, shown at /help).
 */
class HelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_follows_the_users_language_for_owners_and_sellers(): void
    {
        $owner = User::factory()->owner()->create(['locale' => 'en']);
        $seller = User::factory()->seller()->create(['locale' => 'sw']);

        $this->actingAs($owner)->get('/help')->assertOk()
            ->assertSee('ShopApp user manual')
            ->assertSee('Selling at the till')
            ->assertSee('href="#selling-at-the-till"', false)
            ->assertSee('manual/en/till.webp', false)
            ->assertDontSee('../../public/', false);

        $this->actingAs($seller)->get('/help')->assertOk()
            ->assertSee('Mwongozo wa mtumiaji wa ShopApp')
            ->assertSee('Kuuza kaunta')
            ->assertSee('manual/sw/till.webp', false);

        $this->get('/help')->assertOk(); // still logged in as the seller
        auth()->logout();
        $this->get('/help')->assertRedirect('/login');
    }

    public function test_every_screenshot_in_the_manual_exists_in_both_languages(): void
    {
        foreach (['en', 'sw'] as $locale) {
            preg_match_all('#\]\(\.\./\.\./public/(manual/[^)]+)\)#', file_get_contents(base_path("docs/manual/{$locale}.md")), $m);
            $this->assertNotEmpty($m[1], "no screenshots in {$locale}.md");
            foreach ($m[1] as $image) {
                $this->assertFileExists(public_path($image));
                $this->assertStringContainsString("manual/{$locale}/", $image, "{$locale}.md uses another language's screenshot");
            }
        }

        // Both manuals show the same screens.
        $shots = fn ($l) => array_map('basename', glob(public_path("manual/{$l}/*.webp")));
        $this->assertSame($shots('en'), $shots('sw'));
    }
}
