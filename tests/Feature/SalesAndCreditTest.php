<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAndCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_sale_reduces_stock_and_records_profit(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['quantity' => 10, 'buying_price' => 800, 'selling_price' => 1000]);

        $this->actingAs($seller)->postJson('/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 1]],
            'payment_method' => 'cash',
        ])->assertOk()->assertJsonStructure(['redirect']);

        $sale = Sale::firstOrFail();
        $this->assertEquals(3000, $sale->total, 'sellers cannot override prices');
        $this->assertEquals(2400, $sale->cost_total);
        $this->assertEquals(3000, $sale->amount_paid);
        $this->assertEquals(7, $product->fresh()->quantity);
        $this->assertTrue($sale->isPaid());
    }

    public function test_cannot_sell_more_than_in_stock_or_expired_items(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['quantity' => 2]);
        $expired = Product::factory()->expiringIn(-1)->create(['quantity' => 5]);

        $this->actingAs($seller)->postJson('/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->actingAs($seller)->postJson('/sales', [
            'items' => [['product_id' => $expired->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertSame(0, Sale::count());
        $this->assertEquals(2, $product->fresh()->quantity);
    }

    public function test_credit_sale_requires_customer_and_tracks_balance(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 1000]);

        $this->actingAs($seller)->postJson('/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payment_method' => 'credit',
            'amount_paid' => 1000,
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');

        $this->actingAs($seller)->postJson('/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payment_method' => 'credit',
            'amount_paid' => 1000,
            'new_customer_name' => 'Mama Neema',
            'new_customer_phone' => '0712000000',
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertOk();

        $customer = Customer::where('name', 'Mama Neema')->firstOrFail();
        $sale = Sale::firstOrFail();
        $this->assertEquals(4000, $sale->balance());
        $this->assertEquals(4000, $customer->balance);

        $this->actingAs($seller)->get('/credit')->assertSee('Mama Neema')->assertSee('4,000.00');

        // Repay in two instalments; overpayment is capped at the balance.
        $this->actingAs($seller)->post("/sales/{$sale->id}/payments", ['amount' => 1500, 'method' => 'mobile'])->assertRedirect();
        $this->assertEquals(2500, $sale->fresh()->balance());

        $this->actingAs($seller)->post("/sales/{$sale->id}/payments", ['amount' => 99999, 'method' => 'cash']);
        $this->assertTrue($sale->fresh()->isPaid());
        $this->assertEquals(5000, $sale->payments()->sum('amount'));

        $this->actingAs($seller)->get('/credit')->assertDontSee('Mama Neema');
    }

    public function test_owner_can_void_sale_and_restore_stock(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 10]);

        $this->actingAs($owner)->postJson('/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'payment_method' => 'cash',
        ])->assertOk();

        $sale = Sale::firstOrFail();
        $this->actingAs($owner)->delete("/sales/{$sale->id}")->assertRedirect('/sales');

        $this->assertEquals(10, $product->fresh()->quantity);
        $this->assertSame(0, Sale::count());
    }

    public function test_owner_can_override_price(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 10, 'selling_price' => 1000]);

        $this->actingAs($owner)->postJson('/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 900]],
            'payment_method' => 'mobile',
        ])->assertOk();

        $this->assertEquals(1800, Sale::first()->total);
    }
}
