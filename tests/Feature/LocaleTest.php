<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_swahili_and_back(): void
    {
        $user = User::factory()->owner()->create(['locale' => 'en']);

        $this->actingAs($user)->get('/')->assertSee('Dashboard');

        $this->actingAs($user)->from('/')->get('/locale/sw')->assertRedirect('/');
        $this->assertSame('sw', $user->fresh()->locale);
        $this->actingAs($user)->get('/')->assertSee('Dashibodi')->assertSee('Mauzo ya leo');

        $this->actingAs($user)->get('/locale/en');
        $this->actingAs($user)->get('/')->assertSee('Sales today');
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->get('/locale/fr')->assertNotFound();
    }

    public function test_login_page_can_be_shown_in_swahili(): void
    {
        \App\Models\User::factory()->create();

        $this->get('/locale/sw');
        $this->get('/login')->assertSee('Ingia');
    }

    public function test_every_swahili_key_is_a_string(): void
    {
        $sw = json_decode(file_get_contents(lang_path('sw.json')), true);

        $this->assertNotEmpty($sw);
        foreach ($sw as $key => $value) {
            $this->assertIsString($value, $key);
            $this->assertNotSame('', trim($value), $key);
        }
    }
}
