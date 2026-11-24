<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Barcode scanner lookups and thermal receipt printing.
 */
class DevicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanned_barcode_returns_the_exact_product_as_json(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sku' => '6001234567890', 'quantity' => 4]);
        Product::factory()->create(['sku' => '60012345678901']); // longer code containing the first

        $this->actingAs($seller)->getJson('/products/barcode?code=6001234567890')
            ->assertOk()
            ->assertJson(['id' => $product->id, 'quantity' => 4, 'expired' => false, 'buying_price' => null]);

        $this->actingAs($seller)->getJson('/products/barcode?code=600123')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_scan_tolerates_letter_case_and_leading_zeros(): void
    {
        $seller = User::factory()->seller()->create();
        $upc = Product::factory()->create(['sku' => '012345678905']);     // UPC-A as printed
        $excel = Product::factory()->create(['sku' => '4006381333931']);  // fine as is
        $dropped = Product::factory()->create(['sku' => '76222004']);      // Excel dropped the leading 0
        $code39 = Product::factory()->create(['sku' => 'abc-123']);

        $find = fn (string $code) => $this->actingAs($seller)->getJson('/products/barcode?code='.urlencode($code))->assertOk()->json('id');

        $this->assertSame($upc->id, $find('0012345678905'), 'EAN-13 form of a UPC-A code');
        $this->assertSame($upc->id, $find('12345678905'), 'scanner that drops the leading zero');
        $this->assertSame($excel->id, $find('4006381333931'));
        $this->assertSame($dropped->id, $find('076222004'));
        $this->assertSame($code39->id, $find('ABC-123'));
        $this->actingAs($seller)->getJson('/products/barcode?code=12345')->assertNotFound();
    }

    public function test_exact_barcode_beats_a_leading_zero_variant(): void
    {
        $seller = User::factory()->seller()->create();
        Product::factory()->create(['sku' => '0123456']);
        $exact = Product::factory()->create(['sku' => '123456']);

        $this->actingAs($seller)->getJson('/products/barcode?code=123456')->assertOk()->assertJson(['id' => $exact->id]);
    }

    public function test_import_rejects_barcodes_damaged_by_excel(): void
    {
        $owner = User::factory()->owner()->create();
        $csv = "name,sku,buying_price,selling_price\nSoda,6.00123E+12,800,1000\nJuice,6001234567890,900,1200\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($owner)->post('/products/import', ['file' => $file]);

        $this->assertDatabaseMissing('products', ['name' => 'Soda']);
        $this->assertDatabaseHas('products', ['name' => 'Juice', 'sku' => '6001234567890']);
    }

    public function test_expired_products_are_flagged_on_scan(): void
    {
        $seller = User::factory()->seller()->create();
        Product::factory()->expiringIn(-1)->create(['sku' => 'OLD1']);

        $this->actingAs($seller)->getJson('/products/barcode?code=OLD1')->assertOk()->assertJson(['expired' => true]);
    }

    public function test_scanning_outside_the_till_opens_the_product_or_offers_to_create_it(): void
    {
        $owner = User::factory()->owner()->create();
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['sku' => 'ABC123']);

        $this->actingAs($owner)->get('/products/barcode?code=ABC123')->assertRedirect(route('products.show', $product));
        $this->actingAs($owner)->get('/products/barcode?code=NEW999')->assertRedirect(route('products.create', ['sku' => 'NEW999']));
        $this->actingAs($seller)->get('/products/barcode?code=NEW999')->assertRedirect(route('products.index', ['q' => 'NEW999']));

        $this->actingAs($owner)->get('/products/create?sku=NEW999')->assertOk()->assertSee('value="NEW999"', false);
    }

    public function test_thermal_receipt_uses_the_configured_paper_width(): void
    {
        $seller = User::factory()->seller()->create();
        $sale = $this->sell($seller);
        Setting::put(['receipt_width' => '58', 'receipt_footer' => 'No returns']);

        $this->actingAs($seller)->get(route('sales.receipt', $sale))
            ->assertOk()
            ->assertSee('data-paper="58"', false)
            ->assertSee($sale->invoice_no)
            ->assertSee('No returns');
    }

    public function test_sellers_cannot_print_other_sellers_walk_in_receipts(): void
    {
        $sale = $this->sell(User::factory()->seller()->create());

        $this->actingAs(User::factory()->seller()->create())->get(route('sales.receipt', $sale))->assertForbidden();
    }

    public function test_auto_print_sends_the_till_to_a_printing_receipt_page(): void
    {
        $seller = User::factory()->seller()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'payment_method' => 'cash'];

        $first = $this->actingAs($seller)->postJson('/sales', $payload)->assertOk()->json('redirect');
        $this->assertStringNotContainsString('print=1', $first);

        Setting::put(['auto_print_receipt' => '1']);
        $second = $this->actingAs($seller)->postJson('/sales', $payload)->assertOk()->json('redirect');
        $this->assertStringEndsWith('?print=1', $second);
    }

    public function test_owner_saves_device_settings_and_prints_a_test_receipt(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->put('/settings', [
            'shop_name' => 'Duka', 'currency' => 'TZS', 'expiry_alert_days' => 30,
            'receipt_width' => '58', 'receipt_footer' => 'Karibu tena', 'auto_print_receipt' => '1',
            // scanner_beep unticked
        ])->assertRedirect();

        $this->assertSame('58', Setting::get('receipt_width'));
        $this->assertSame('1', Setting::get('auto_print_receipt'));
        $this->assertSame('0', Setting::get('scanner_beep'));

        $this->actingAs($owner)->put('/settings', [
            'shop_name' => 'Duka', 'currency' => 'TZS', 'expiry_alert_days' => 30, 'receipt_width' => '100',
        ])->assertSessionHasErrors('receipt_width');

        $this->actingAs($owner)->get('/settings/test-receipt?width=80')->assertOk()->assertSee('data-paper="80"', false);
        $this->actingAs(User::factory()->seller()->create())->get('/settings/test-receipt')->assertForbidden();
    }

    public function test_device_screens_render(): void
    {
        $owner = User::factory()->owner()->create();
        $sale = $this->sell($owner);

        $this->actingAs($owner)->get(route('sales.show', $sale))->assertOk()->assertSee('sales\/'.$sale->id.'\/receipt', false);
        $this->actingAs($owner)->get('/settings')->assertOk()->assertSee('receipt_width', false)->assertSee('ShopKeyboard.setPref', false);
        $this->actingAs($owner)->get('/sales/new')->assertOk()->assertSee('onScan', false);
        $this->actingAs($owner)->get('/stock/add')->assertOk()->assertSee('onScan', false);
        $this->actingAs($owner)->get('/')->assertOk()->assertSee('name="shop-devices"', false);
    }

    public function test_customer_display_opens_without_login_and_shows_the_shop(): void
    {
        Setting::put(['shop_name' => 'Duka la Mama']);

        $this->get('/display')->assertOk()->assertSee('Duka la Mama')->assertSee('customerDisplay', false)
            ->assertSee('Your order')->assertSee('Last item')->assertSee('lastLine', false);
    }

    public function test_till_sends_order_details_for_the_customer_display(): void
    {
        $seller = User::factory()->seller()->create(['name' => 'Asha']);
        $product = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($seller)->get('/sales/new')->assertOk()->assertSee('\u0022cashier\u0022:\u0022Asha\u0022', false);

        $sale = $this->actingAs($seller)->postJson('/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]], 'payment_method' => 'cash',
        ])->assertOk()->json();
        $this->assertSame(Sale::findOrFail($sale['id'])->invoice_no, $sale['invoice_no']);

        // The till stays open and prints the receipt itself.
        $this->assertSame(route('sales.receipt', $sale['id']), $sale['receipt_url']);
        $this->assertFalse($sale['auto_print']);
        $this->actingAs($seller)->get('/sales')->assertDontSee(__('Sale completed.'));
    }

    public function test_till_mirrors_the_cart_to_the_customer_display(): void
    {
        $this->actingAs(User::factory()->seller()->create())->get('/sales/new')
            ->assertOk()
            ->assertSee('x-effect="broadcast()"', false)
            ->assertSee('cashSuggestions', false);
    }

    private function sell(User $seller): Sale
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $this->actingAs($seller)->postJson('/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }
}
