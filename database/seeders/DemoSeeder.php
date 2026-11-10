<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample products, sales, credit and expenses so the app can be explored right away.
 */
class DemoSeeder extends Seeder
{
    public function run(SaleService $sales): void
    {
        if (Product::exists()) {
            return;
        }

        $tags = collect([
            'drinks' => 'sky', 'food' => 'amber', 'dairy' => 'teal', 'cosmetics' => 'rose',
            'cleaning' => 'violet', 'grains' => 'orange', 'snacks' => 'emerald',
        ])->map(fn ($color, $name) => Tag::create(['name' => $name, 'color' => $color]));

        $owner = User::where('role', User::ROLE_OWNER)->first();
        $seller = User::where('role', User::ROLE_SELLER)->first();

        $items = [
            // name, sku, unit, buy, sell, qty, reorder, expiry (days), tags
            ['Coca-Cola 500ml', '5449000000996', 'bottle', 800, 1000, 96, 24, 120, ['drinks']],
            ['Azam Juice Embe 1L', '6201100013118', 'bottle', 2200, 3000, 30, 10, 12, ['drinks']],
            ['Kilimanjaro Water 1.5L', '6203001500015', 'bottle', 700, 1000, 60, 12, 300, ['drinks']],
            ['Fresh Milk 500ml', '6201234500017', 'packet', 1100, 1500, 18, 10, 3, ['dairy', 'drinks']],
            ['Yoghurt Strawberry 250ml', '6201234500024', 'cup', 900, 1300, 12, 6, -2, ['dairy', 'snacks']],
            ['Unga wa Sembe 2kg', '6209000200019', 'packet', 3400, 4000, 40, 10, 150, ['food', 'grains']],
            ['Mchele Super 1kg', null, 'kg', 2600, 3200, 75, 20, null, ['food', 'grains']],
            ['Sukari 1kg', null, 'kg', 2700, 3200, 50, 15, null, ['food']],
            ['Mafuta ya Kupikia 1L', '6201000300014', 'bottle', 5200, 6000, 24, 6, 200, ['food']],
            ['Bread (Mkate) 600g', '6201000400011', 'pcs', 1800, 2500, 10, 5, 2, ['food', 'snacks']],
            ['Biscuits Marie', '6201000500018', 'packet', 400, 600, 4, 12, 90, ['snacks']],
            ['Sabuni ya Kufulia Omo 500g', '6001087000012', 'packet', 2800, 3500, 20, 5, null, ['cleaning']],
            ['Colgate 100ml', '6001067000019', 'pcs', 2500, 3200, 15, 5, 400, ['cosmetics']],
            ['Vaseline Lotion 200ml', '6001085000016', 'bottle', 5500, 7000, 8, 3, 25, ['cosmetics']],
        ];

        foreach ($items as [$name, $sku, $unit, $buy, $sell, $qty, $reorder, $expiry, $tagNames]) {
            $product = Product::create([
                'name' => $name,
                'sku' => $sku,
                'unit' => $unit,
                'buying_price' => $buy,
                'selling_price' => $sell,
                'quantity' => $qty,
                'reorder_level' => $reorder,
                'expiry_date' => $expiry === null ? null : now()->addDays($expiry)->toDateString(),
            ]);
            $product->tags()->sync(collect($tagNames)->map(fn ($t) => $tags[$t]->id));
            $product->stockEntries()->create([
                'user_id' => $owner->id,
                'quantity' => $qty,
                'buying_price' => $buy,
                'selling_price' => $sell,
                'expiry_date' => $product->expiry_date,
                'note' => 'Opening stock',
            ]);
        }

        $customers = collect([
            ['Mama Neema', '0712345678', 'Sinza'],
            ['Juma Hamisi', '0754000111', 'Mwenge'],
            ['Asha Said', '0688123456', 'Kariakoo'],
        ])->map(fn ($c) => Customer::create(['name' => $c[0], 'phone' => $c[1], 'address' => $c[2]]));

        // A few weeks of sales history.
        for ($daysAgo = 20; $daysAgo >= 0; $daysAgo--) {
            Carbon::setTestNow(now()->subDays($daysAgo)->setTime(8, 0));

            foreach (range(1, rand(2, 4)) as $n) {
                Carbon::setTestNow(now()->addMinutes(rand(30, 150)));
                $products = Product::where('quantity', '>', 10)
                    ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now()->addDays(30)))
                    ->get();
                $lines = $products->random(rand(1, 3))->map(fn ($p) => ['product_id' => $p->id, 'quantity' => rand(1, 2)])->all();
                $sales->create($n % 2 ? $seller : $owner, $lines, ['cash', 'cash', 'mobile'][rand(0, 2)], 0);
            }

            Carbon::setTestNow();
        }

        // Credit sales
        $products = Product::where('quantity', '>', 10)->orderByDesc('quantity')->get();
        $sales->create($seller, [['product_id' => $products[0]->id, 'quantity' => 5]], 'credit', 1000, $customers[0], now()->addDays(7)->toDateString());
        $sale = $sales->create($owner, [['product_id' => $products[1]->id, 'quantity' => 3]], 'credit', 0, $customers[1], now()->addDays(3)->toDateString());
        $sale->update(['due_date' => now()->subDays(2)->toDateString()]); // overdue example
        $sales->create($seller, [['product_id' => $products[2]->id, 'quantity' => 2]], 'credit', 0, $customers[2]);

        foreach ([
            ['electricity', 'LUKU tokens', 50000, 15],
            ['food', 'Staff lunch', 15000, 1],
            ['salaries', 'Seller salary', 300000, 10],
            ['rent', 'Shop rent', 200000, 9],
            ['transport', 'Goods delivery from Kariakoo', 20000, 3],
        ] as [$category, $description, $amount, $daysAgo]) {
            Expense::create([
                'user_id' => $owner->id,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'expense_date' => now()->subDays($daysAgo)->toDateString(),
            ]);
        }
    }
}
