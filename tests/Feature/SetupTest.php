<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'shop_name' => 'Duka la Mama Neema',
            'owner_name' => 'Neema',
            'owner_email' => 'neema@duka.test',
            'owner_password' => 'secret123',
            'owner_password_confirmation' => 'secret123',
            'owner_locale' => 'sw',
            'add_seller' => '1',
            'seller_name' => 'Juma',
            'seller_email' => 'juma@duka.test',
            'seller_password' => 'secret456',
            'seller_password_confirmation' => 'secret456',
            'seller_locale' => 'sw',
        ];
    }

    public function test_fresh_install_sends_login_to_setup(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('Set up your shop');
    }

    public function test_setup_creates_owner_seller_and_shop_name(): void
    {
        $this->post('/setup', $this->payload())->assertRedirect('/');

        $owner = User::where('email', 'neema@duka.test')->firstOrFail();
        $this->assertTrue($owner->isOwner());
        $this->assertAuthenticatedAs($owner);
        $this->assertTrue(User::where('email', 'juma@duka.test')->firstOrFail()->isSeller());
        $this->assertSame('Duka la Mama Neema', Shop::name());
    }

    public function test_seller_and_shop_name_are_optional(): void
    {
        $this->post('/setup', $this->payload([
            'shop_name' => '',
            'add_seller' => '0',
            'seller_email' => '',
        ]))->assertRedirect('/');

        $this->assertSame(1, User::count());
        $this->assertSame('My Shop', Shop::name());
    }

    public function test_setup_validates_passwords(): void
    {
        $this->post('/setup', $this->payload(['owner_password_confirmation' => 'nope']))
            ->assertSessionHasErrors('owner_password');

        $this->assertSame(0, User::count());
    }

    public function test_setup_is_closed_once_a_user_exists(): void
    {
        User::factory()->owner()->create();

        $this->get('/setup')->assertNotFound();
        $this->post('/setup', $this->payload())->assertNotFound();
        $this->get('/login')->assertOk();
    }
}
