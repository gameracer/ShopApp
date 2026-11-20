<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Product;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpenseAndReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_records_expenses(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/expenses', [
            'category' => 'electricity', 'description' => 'LUKU', 'amount' => 50000, 'expense_date' => today()->toDateString(),
        ])->assertRedirect('/expenses');

        $this->actingAs($owner)->post('/expenses', [
            'category' => 'not-a-category', 'amount' => 10, 'expense_date' => today()->toDateString(),
        ])->assertSessionHasErrors('category');

        $this->assertDatabaseCount('expenses', 1);
        $this->actingAs($owner)->get('/expenses')->assertSee('LUKU')->assertSee('50,000.00');
    }

    public function test_daily_monthly_and_yearly_reports_calculate_profit(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 100, 'buying_price' => 600, 'selling_price' => 1000]);
        $service = app(SaleService::class);

        // Today: 5 units => revenue 5000, cost 3000
        $service->create($owner, [['product_id' => $product->id, 'quantity' => 5]], 'cash', 0);
        // Earlier this month: 2 units => revenue 2000, cost 1200
        Carbon::setTestNow('2026-11-02 09:00:00');
        $service->create($owner, [['product_id' => $product->id, 'quantity' => 2]], 'cash', 0);
        // Earlier this year: 3 units => revenue 3000, cost 1800
        Carbon::setTestNow('2026-03-15 09:00:00');
        $service->create($owner, [['product_id' => $product->id, 'quantity' => 3]], 'cash', 0);
        Carbon::setTestNow('2026-11-10 12:00:00');

        Expense::factory()->create(['amount' => 500, 'expense_date' => '2026-11-10', 'category' => 'food']);
        Expense::factory()->create(['amount' => 1000, 'expense_date' => '2026-11-01', 'category' => 'electricity']);

        $daily = $this->actingAs($owner)->get('/reports?period=daily&date=2026-11-10')->assertOk()->viewData('report')['summary'];
        $this->assertEquals(5000, $daily['revenue']);
        $this->assertEquals(2000, $daily['gross_profit']);
        $this->assertEquals(500, $daily['expenses']);
        $this->assertEquals(1500, $daily['net_profit']);

        $monthly = $this->actingAs($owner)->get('/reports?period=monthly&date=2026-11')->viewData('report')['summary'];
        $this->assertEquals(7000, $monthly['revenue']);
        $this->assertEquals(2800, $monthly['gross_profit']);
        $this->assertEquals(1300, $monthly['net_profit']);

        $yearlyResponse = $this->actingAs($owner)->get('/reports?period=yearly&date=2026');
        $yearly = $yearlyResponse->viewData('report');
        $this->assertEquals(10000, $yearly['summary']['revenue']);
        $this->assertEquals(4000, $yearly['summary']['gross_profit']);
        $this->assertCount(11, $yearly['breakdown'], 'Jan..Nov, no future months');

        $this->actingAs($owner)->get('/reports/export?period=monthly&date=2026-11')
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename=report-monthly-2026-11.csv');

        Carbon::setTestNow();
    }

    public function test_owner_dashboard_charts_daily_monthly_and_yearly_sales(): void
    {
        $owner = User::factory()->owner()->create();
        $product = Product::factory()->create(['quantity' => 100, 'selling_price' => 1000]);
        $service = app(SaleService::class);

        foreach (['2024-06-01 10:00:00' => 4, '2026-03-15 09:00:00' => 3, '2026-11-02 09:00:00' => 2, '2026-11-10 08:00:00' => 1, '2026-11-10 11:00:00' => 5] as $at => $qty) {
            Carbon::setTestNow($at);
            $service->create($owner, [['product_id' => $product->id, 'quantity' => $qty]], 'cash', 0);
        }
        Carbon::setTestNow('2026-11-10 12:00:00');

        $trend = $this->actingAs($owner)->get('/')->assertOk()->assertSee('salesChart', false)->viewData('trend');

        // Last 30 days, ending today; empty days kept as zero.
        $this->assertCount(30, $trend['daily']);
        $this->assertEquals(['total' => 6000, 'count' => 2], array_intersect_key(end($trend['daily']), ['total' => 1, 'count' => 1]));
        $this->assertEquals(8000, collect($trend['daily'])->sum('total'));

        // Last 12 months: Dec 2025 .. Nov 2026.
        $this->assertCount(12, $trend['monthly']);
        $this->assertEquals(8000, end($trend['monthly'])['total']);
        $this->assertEquals(3000, $trend['monthly'][3]['total'], 'March 2026');

        // Every year since the first sale (at least five).
        $this->assertSame(['2022', '2023', '2024', '2025', '2026'], array_column($trend['yearly'], 'label'));
        $this->assertEquals([0, 0, 4000, 0, 11000], array_column($trend['yearly'], 'total'));

        // Sellers get their launcher, with no shop-wide chart.
        $this->actingAs(User::factory()->seller()->create())->get('/')->assertOk()->assertDontSee('salesChart', false);

        Carbon::setTestNow();
    }
}
