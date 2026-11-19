<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('products.csv', $contents);
    }

    public function test_owner_imports_products_with_tags_and_opening_stock(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/products/import', ['file' => $this->csv(
            "name,sku,unit,buying_price,selling_price,quantity,reorder_level,expiry_date,tags,description\n".
            "Soda 500ml,111,bottle,800,1000,24,6,2027-01-31,drinks; soda,Cold\n".
            "Rice 1kg,,kg,\"2,500\",3000,0,,15/06/2027,,\n"
        )])->assertRedirect('/products/import')->assertSessionHas('import_result.imported', 2);

        $soda = Product::where('name', 'Soda 500ml')->firstOrFail();
        $this->assertEquals(1000, $soda->selling_price);
        $this->assertSame('2027-01-31', $soda->expiry_date->toDateString());
        $this->assertEqualsCanonicalizing(['drinks', 'soda'], $soda->tags->pluck('name')->all());
        $this->assertDatabaseHas('stock_entries', ['product_id' => $soda->id, 'quantity' => 24]);

        $rice = Product::where('name', 'Rice 1kg')->firstOrFail();
        $this->assertEquals(2500, $rice->buying_price);
        $this->assertSame('2027-06-15', $rice->expiry_date->toDateString());
        $this->assertDatabaseMissing('stock_entries', ['product_id' => $rice->id]);
    }

    public function test_duplicate_names_are_skipped_against_database_and_within_file(): void
    {
        $owner = User::factory()->owner()->create();
        Product::factory()->create(['name' => 'Coca Cola']);

        $this->actingAs($owner)->post('/products/import', ['file' => $this->csv(
            "name,buying_price,selling_price\n".
            "  coca   COLA ,500,700\n".   // same as existing product
            "Fanta,500,700\n".
            "FANTA,500,700\n".            // repeats a row above
            ",500,700\n".                 // missing name
            "Sprite,abc,700\n"            // invalid price
        )])->assertRedirect();

        $result = session('import_result');
        $this->assertSame(1, $result['imported']);
        $this->assertSame([2, 4, 5, 6], array_column($result['skipped'], 'row'));
        $this->assertSame(1, Product::where('name', 'like', 'coca%')->count());
        $this->assertSame(['Coca Cola', 'Fanta'], Product::orderBy('name')->pluck('name')->all());
    }

    public function test_duplicate_skus_are_skipped(): void
    {
        $owner = User::factory()->owner()->create();
        Product::factory()->create(['sku' => 'ABC']);

        $this->actingAs($owner)->post('/products/import', ['file' => $this->csv(
            "name,sku,buying_price,selling_price\nNew Item,abc,1,2\n"
        )]);

        $this->assertSame(0, session('import_result.imported'));
        $this->assertDatabaseMissing('products', ['name' => 'New Item']);
    }

    public function test_semicolon_delimited_file_with_bom_and_header_aliases(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/products/import', ['file' => $this->csv(
            "\xEF\xBB\xBFProduct Name;Barcode;Cost;Price;Qty\nSugar;999;2000;2500;10\n"
        )]);

        $this->assertDatabaseHas('products', ['name' => 'Sugar', 'sku' => '999', 'quantity' => 10]);
    }

    public function test_file_without_name_column_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->from('/products/import')
            ->post('/products/import', ['file' => $this->csv("foo,bar\n1,2\n")])
            ->assertRedirect('/products/import')
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Product::count());
    }

    public function test_files_up_to_15_mb_are_accepted_and_larger_ones_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $ok = UploadedFile::fake()->createWithContent('ok.csv', "name,buying_price,selling_price\nSalt,1,2\n");
        $this->actingAs($owner)->post('/products/import', ['file' => $ok])->assertSessionHasNoErrors();

        $big = UploadedFile::fake()->create('big.csv', 15 * 1024 + 1, 'text/csv');
        $this->actingAs($owner)->from('/products/import')
            ->post('/products/import', ['file' => $big])
            ->assertRedirect('/products/import')
            ->assertSessionHasErrors('file');
    }

    public function test_large_import_caps_the_skipped_list_but_counts_everything(): void
    {
        $owner = User::factory()->owner()->create();

        $csv = "name,buying_price,selling_price,tags\n";
        for ($i = 1; $i <= 1200; $i++) {
            $csv .= "Item {$i},1,2,bulk;test\n";
        }
        for ($i = 1; $i <= 700; $i++) {
            $csv .= "Item {$i},1,2,\n"; // duplicates of rows above
        }

        $this->actingAs($owner)->post('/products/import', ['file' => $this->csv($csv)])->assertSessionHasNoErrors();

        $result = session('import_result');
        $this->assertSame(1200, $result['imported']);
        $this->assertSame(700, $result['skipped_total']);
        $this->assertCount(\App\Support\ProductImporter::MAX_SKIPPED_LISTED, $result['skipped']);
        $this->assertSame(2, \App\Models\Tag::count());
        $this->assertSame(1200, Product::count());
    }

    public function test_too_many_rows_rolls_back_the_whole_import(): void
    {
        $owner = User::factory()->owner()->create();

        $csv = "name,buying_price,selling_price\n";
        for ($i = 1; $i <= \App\Support\ProductImporter::MAX_ROWS + 1; $i++) {
            $csv .= "P{$i},1,2\n";
        }

        $this->actingAs($owner)->from('/products/import')
            ->post('/products/import', ['file' => $this->csv($csv)])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Product::count());
    }

    public function test_sellers_cannot_import(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)->get('/products/import')->assertForbidden();
        $this->actingAs($seller)->post('/products/import', ['file' => $this->csv("name\nX\n")])->assertForbidden();
    }

    public function test_template_download_and_import_page(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/products/import')->assertOk()->assertSee('Download template');
        $response = $this->actingAs($owner)->get('/products/import/template')->assertOk();
        $this->assertStringStartsWith('name,sku,unit,buying_price', $response->streamedContent());
    }

    public function test_product_form_rejects_duplicate_name_case_insensitively(): void
    {
        $owner = User::factory()->owner()->create();
        $existing = Product::factory()->create(['name' => 'Fresh Milk']);
        $other = Product::factory()->create(['name' => 'Bread']);

        $payload = ['unit' => 'pcs', 'buying_price' => 1, 'selling_price' => 2, 'quantity' => 0];

        $this->actingAs($owner)->post('/products', ['name' => 'fresh  milk'] + $payload)->assertSessionHasErrors('name');
        $this->actingAs($owner)->put("/products/{$other->id}", ['name' => 'FRESH MILK'] + $payload)->assertSessionHasErrors('name');

        // Saving a product under its own name is fine.
        $this->actingAs($owner)->put("/products/{$existing->id}", ['name' => 'Fresh Milk'] + $payload)->assertSessionHasNoErrors();
    }
}
