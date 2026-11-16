<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        User::factory()->owner()->create();

        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->owner()->create(['email' => 'boss@shop.test']);

        $this->post('/login', ['email' => 'boss@shop.test', 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_seller_starts_on_the_till_panel_with_few_options(): void
    {
        $seller = User::factory()->seller()->create(['email' => 'till@shop.test']);

        $this->post('/login', ['email' => 'till@shop.test', 'password' => 'password'])->assertRedirect('/');

        // Home is a launcher of big cards.
        $this->actingAs($seller)->get('/')->assertOk()
            ->assertSee('home-card', false)
            ->assertSee(route('sales.create'), false)
            ->assertSee(route('stock.create'), false)
            ->assertSee(route('credit.index'), false)
            ->assertDontSee(__('Profit today'));

        $this->actingAs($seller)->get('/sales/new')->assertOk()
            ->assertSee('panel-tab', false)
            ->assertSee(route('credit.index'), false)
            ->assertDontSee('nav-link', false)
            ->assertDontSee(route('expenses.index'), false);

        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->get('/sales/new')->assertOk()->assertSee('nav-link', false)->assertDontSee('panel-tab', false);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'off@shop.test', 'is_active' => false]);

        $this->post('/login', ['email' => 'off@shop.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_seller_cannot_access_owner_pages(): void
    {
        $seller = User::factory()->seller()->create();

        foreach (['/reports', '/expenses', '/users', '/settings', '/tags', '/products/create'] as $url) {
            $this->actingAs($seller)->get($url)->assertForbidden();
        }

        foreach (['/', '/products', '/sales/new', '/stock/add', '/credit', '/customers', '/alerts'] as $url) {
            $this->actingAs($seller)->get($url)->assertOk();
        }
    }

    public function test_owner_can_access_every_page(): void
    {
        $owner = User::factory()->owner()->create();

        foreach (['/', '/reports', '/reports?period=monthly', '/reports?period=yearly&date=2026', '/expenses', '/expenses/create', '/users', '/users/create', '/settings', '/tags', '/products', '/products/create', '/sales', '/sales/new', '/stock', '/stock/add', '/credit', '/customers', '/customers/create', '/alerts', '/profile'] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
    }

    public function test_owner_manages_sellers_but_cannot_remove_last_owner(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/users', [
            'name' => 'Seller One', 'email' => 's1@shop.test', 'role' => 'seller', 'locale' => 'sw',
            'password' => 'secret12', 'password_confirmation' => 'secret12', 'is_active' => 1,
        ])->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['email' => 's1@shop.test', 'role' => 'seller']);

        $this->actingAs($owner)->put("/users/{$owner->id}", [
            'name' => $owner->name, 'email' => $owner->email, 'role' => 'seller', 'locale' => 'en', 'is_active' => 1,
        ])->assertSessionHasErrors('role');
        $this->assertTrue($owner->fresh()->isOwner());
    }
}
