<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_corrects_stock_entered_by_mistake(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 110, 'buying_price' => 100]);

        $this->actingAs($owner)->get("/products/{$product->id}/adjust")->assertOk()->assertSee('Entered by mistake');

        $this->actingAs($owner)->post("/products/{$product->id}/adjust", [
            'quantity' => 20, 'reason' => 'mistake', 'note' => 'typed 100 instead of 10',
        ])->assertRedirect("/products/{$product->id}");

        $this->assertEquals(20, $product->refresh()->quantity);
        $this->assertDatabaseHas('stock_entries', [
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'quantity' => -90,
            'note' => 'Entered by mistake: typed 100 instead of 10',
        ]);

        $this->actingAs($owner)->get('/stock')->assertSee('−90')->assertSee('Entered by mistake');
    }

    public function test_stock_count_can_add_stock_found_on_the_shelf(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 4]);

        $this->actingAs($owner)->post("/products/{$product->id}/adjust", ['quantity' => 6.5, 'reason' => 'count']);

        $this->assertEquals(6.5, $product->refresh()->quantity);
        $this->assertDatabaseHas('stock_entries', ['product_id' => $product->id, 'quantity' => 2.5, 'note' => 'Stock count']);
    }

    public function test_adjustment_needs_a_reason_and_a_change(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($owner)->post("/products/{$product->id}/adjust", ['quantity' => 3])->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post("/products/{$product->id}/adjust", ['quantity' => -1, 'reason' => 'damaged'])->assertSessionHasErrors('quantity');
        $this->actingAs($owner)->post("/products/{$product->id}/adjust", ['quantity' => 5, 'reason' => 'damaged'])->assertSessionHasErrors('quantity');

        $this->assertEquals(5, $product->refresh()->quantity);
        $this->assertDatabaseCount('stock_entries', 0);
    }

    public function test_sellers_cannot_adjust_stock(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($seller)->get("/products/{$product->id}/adjust")->assertForbidden();
        $this->actingAs($seller)->post("/products/{$product->id}/adjust", ['quantity' => 0, 'reason' => 'lost'])->assertForbidden();

        $this->assertEquals(5, $product->refresh()->quantity);
    }

    public function test_changing_quantity_on_the_product_form_is_recorded(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 10]);

        $form = ['name' => $product->name, 'unit' => $product->unit, 'buying_price' => 100, 'selling_price' => 150];

        $this->actingAs($owner)->put("/products/{$product->id}", $form + ['quantity' => 7])->assertRedirect();
        $this->assertEquals(7, $product->refresh()->quantity);
        $this->assertDatabaseHas('stock_entries', ['product_id' => $product->id, 'quantity' => -3, 'note' => 'Changed on the product form']);

        // Saving other details without touching the quantity adds nothing to the history.
        $this->actingAs($owner)->put("/products/{$product->id}", $form + ['quantity' => 7, 'selling_price' => 160])->assertRedirect();
        $this->assertDatabaseCount('stock_entries', 1);
    }
}
