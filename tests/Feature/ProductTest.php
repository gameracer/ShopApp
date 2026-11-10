<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_registers_product_with_prices_and_tags(): void
    {
        $owner = User::factory()->owner()->create();
        $drinks = Tag::factory()->create(['name' => 'drinks']);

        $this->actingAs($owner)->post('/products', [
            'name' => 'Soda 500ml', 'sku' => '123', 'unit' => 'bottle',
            'buying_price' => 800, 'selling_price' => 1000, 'quantity' => 24,
            'reorder_level' => 6, 'tags' => [$drinks->id], 'new_tags' => 'Soft Drinks, cold',
        ])->assertRedirect();

        $product = Product::where('name', 'Soda 500ml')->firstOrFail();
        $this->assertEquals(800, $product->buying_price);
        $this->assertEquals(1000, $product->selling_price);
        $this->assertEqualsCanonicalizing(['drinks', 'soft drinks', 'cold'], $product->tags->pluck('name')->all());
        $this->assertDatabaseHas('stock_entries', ['product_id' => $product->id, 'quantity' => 24]);
    }

    public function test_search_finds_by_name_sku_and_tag_and_filters_by_tags(): void
    {
        $owner = User::factory()->owner()->create();
        $drinks = Tag::factory()->create(['name' => 'drinks']);
        $dairy = Tag::factory()->create(['name' => 'dairy']);

        $soda = Product::factory()->create(['name' => 'Coca Cola', 'sku' => 'CC500']);
        $milk = Product::factory()->create(['name' => 'Fresh Milk', 'sku' => 'MLK1']);
        $rice = Product::factory()->create(['name' => 'Rice', 'sku' => 'RC1']);
        $soda->tags()->attach($drinks);
        $milk->tags()->attach([$drinks->id, $dairy->id]);

        $names = fn ($res) => collect($res->json())->pluck('name')->all();

        $this->assertSame(['Coca Cola'], $names($this->actingAs($owner)->getJson('/products/search?q=cola')));
        $this->assertSame(['Fresh Milk'], $names($this->actingAs($owner)->getJson('/products/search?q=MLK')));
        $this->assertSame(['Coca Cola', 'Fresh Milk'], $names($this->actingAs($owner)->getJson('/products/search?q=drinks')));
        $this->assertSame(['Fresh Milk'], $names($this->actingAs($owner)->getJson("/products/search?tags[]={$drinks->id}&tags[]={$dairy->id}")));

        $this->actingAs($owner)->get('/products?tags[]='.$dairy->id)->assertSee('Fresh Milk')->assertDontSee('Coca Cola');
    }

    public function test_seller_does_not_see_buying_price_in_search(): void
    {
        $seller = User::factory()->seller()->create();
        Product::factory()->create(['buying_price' => 777]);

        $this->actingAs($seller)->getJson('/products/search')->assertJsonPath('0.buying_price', null);
    }

    public function test_stock_in_increases_quantity_and_keeps_earliest_expiry(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['quantity' => 5, 'buying_price' => 100, 'selling_price' => 150, 'expiry_date' => now()->addDays(10)]);

        $this->actingAs($seller)->postJson('/stock', [
            'supplier' => 'Wholesaler',
            'items' => [['product_id' => $product->id, 'quantity' => 20, 'buying_price' => 1, 'selling_price' => 1, 'expiry_date' => now()->addDays(90)->toDateString()]],
        ])->assertOk();

        $product->refresh();
        $this->assertEquals(25, $product->quantity);
        $this->assertEquals(100, $product->buying_price, 'sellers cannot change prices');
        $this->assertSame(now()->addDays(10)->toDateString(), $product->expiry_date->toDateString());
    }

    public function test_owner_stock_in_can_update_prices(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 0, 'buying_price' => 100, 'selling_price' => 150]);

        $this->actingAs($owner)->postJson('/stock', [
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'buying_price' => 120, 'selling_price' => 180, 'expiry_date' => now()->addDays(60)->toDateString()]],
        ])->assertOk();

        $product->refresh();
        $this->assertEquals(120, $product->buying_price);
        $this->assertEquals(180, $product->selling_price);
        $this->assertSame(now()->addDays(60)->toDateString(), $product->expiry_date->toDateString());
    }

    public function test_near_expiry_items_show_on_alerts_page(): void
    {
        $user = User::factory()->seller()->create();
        Product::factory()->expiringIn(5)->create(['name' => 'Milk Soon']);
        Product::factory()->expiringIn(-1)->create(['name' => 'Old Bread']);
        Product::factory()->expiringIn(200)->create(['name' => 'Canned Beans']);
        Product::factory()->expiringIn(3)->create(['name' => 'Sold Out Yoghurt', 'quantity' => 0]);

        $this->assertSame(2, Product::nearExpiry()->count());

        $response = $this->actingAs($user)->get('/alerts')
            ->assertSee('Milk Soon')->assertSee('Old Bread')->assertDontSee('Canned Beans');

        $this->assertSame(['Milk Soon'], $response->viewData('expiring')->pluck('name')->values()->all());
        $this->assertSame(['Old Bread'], $response->viewData('expired')->pluck('name')->values()->all());
        // Sold out items are not expiry alerts (they show under low stock instead).
        $this->assertContains('Sold Out Yoghurt', $response->viewData('lowStock')->pluck('name')->all());
    }
}
