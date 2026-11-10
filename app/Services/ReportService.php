<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public const PERIODS = ['daily', 'monthly', 'yearly'];

    /**
     * Resolve the start/end of a report period from a date string.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(string $period, ?string $date): array
    {
        try {
            $day = $date ? CarbonImmutable::parse($date) : CarbonImmutable::today();
        } catch (\Throwable) {
            $day = CarbonImmutable::today();
        }

        return match ($period) {
            'yearly' => [$day->startOfYear(), $day->endOfYear()],
            'monthly' => [$day->startOfMonth(), $day->endOfMonth()],
            default => [$day->startOfDay(), $day->endOfDay()],
        };
    }

    public function build(string $period, CarbonImmutable $start, CarbonImmutable $end, ?int $sellerId = null): array
    {
        $sales = Sale::query()
            ->whereBetween('sold_at', [$start, $end])
            ->when($sellerId, fn ($q) => $q->where('user_id', $sellerId))
            ->get(['id', 'user_id', 'total', 'cost_total', 'amount_paid', 'payment_method', 'sold_at']);

        $expenses = $sellerId ? collect() : Expense::query()
            ->whereBetween('expense_date', [$start->startOfDay(), $end->endOfDay()])
            ->get(['category', 'amount', 'expense_date']);

        $payments = Payment::query()
            ->whereBetween('paid_at', [$start, $end])
            ->when($sellerId, fn ($q) => $q->where('user_id', $sellerId))
            ->get(['amount', 'paid_at', 'sale_id']);

        $revenue = (float) $sales->sum('total');
        $cost = (float) $sales->sum('cost_total');
        $expenseTotal = (float) $expenses->sum('amount');
        $grossProfit = $revenue - $cost;

        return [
            'summary' => [
                'sales_count' => $sales->count(),
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'gross_profit' => round($grossProfit, 2),
                'expenses' => round($expenseTotal, 2),
                'net_profit' => round($grossProfit - $expenseTotal, 2),
                'cash_received' => round((float) $payments->sum('amount'), 2),
                'credit_given' => round((float) $sales->sum(fn ($s) => (float) $s->total - (float) $s->amount_paid), 2),
            ],
            'breakdown' => $this->breakdown($period, $start, $end, $sales, $expenses),
            'by_method' => $sales->groupBy('payment_method')
                ->map(fn (Collection $g) => ['count' => $g->count(), 'total' => round((float) $g->sum('total'), 2)])
                ->sortByDesc('total'),
            'expenses_by_category' => $expenses->groupBy('category')
                ->map(fn (Collection $g) => round((float) $g->sum('amount'), 2))
                ->sortDesc(),
            'top_products' => $this->topProducts($start, $end, $sellerId),
            'by_seller' => $this->bySeller($sales),
        ];
    }

    /**
     * Rows per hour (daily), per day (monthly) or per month (yearly).
     */
    private function breakdown(string $period, CarbonImmutable $start, CarbonImmutable $end, Collection $sales, Collection $expenses): array
    {
        [$keyFormat, $labelFormat, $interval] = match ($period) {
            'yearly' => ['Y-m', 'M Y', '1 month'],
            'monthly' => ['Y-m-d', 'D d M', '1 day'],
            default => ['H', 'H:00', '1 hour'],
        };

        $salesBy = $sales->groupBy(fn ($s) => $s->sold_at->format($keyFormat));
        $expBy = $period === 'daily'
            ? collect()
            : $expenses->groupBy(fn ($e) => $e->expense_date->format($keyFormat));

        $rows = [];
        // Don't list future days/months with empty rows.
        $until = $end->min(CarbonImmutable::now());

        foreach (CarbonPeriod::create($start, $interval, $until) as $point) {
            $key = $point->format($keyFormat);
            $s = $salesBy->get($key, collect());
            $revenue = (float) $s->sum('total');
            $cost = (float) $s->sum('cost_total');
            $exp = (float) $expBy->get($key, collect())->sum('amount');

            if ($period === 'daily' && $s->isEmpty()) {
                continue;
            }

            $rows[] = [
                'label' => $point->translatedFormat($labelFormat),
                'count' => $s->count(),
                'revenue' => round($revenue, 2),
                'profit' => round($revenue - $cost, 2),
                'expenses' => round($exp, 2),
                'net' => round($revenue - $cost - $exp, 2),
            ];
        }

        return $rows;
    }

    private function topProducts(CarbonImmutable $start, CarbonImmutable $end, ?int $sellerId): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sold_at', [$start, $end])
            ->when($sellerId, fn ($q) => $q->where('sales.user_id', $sellerId))
            ->groupBy('sale_items.product_name')
            ->select('sale_items.product_name')
            ->selectRaw('SUM(sale_items.quantity) as qty')
            ->selectRaw('SUM(sale_items.subtotal) as revenue')
            ->selectRaw('SUM(sale_items.subtotal - sale_items.buying_price * sale_items.quantity) as profit')
            ->orderByDesc(DB::raw('SUM(sale_items.subtotal)'))
            ->limit(10)
            ->get();
    }

    private function bySeller(Collection $sales): Collection
    {
        $names = User::whereIn('id', $sales->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        return $sales->groupBy('user_id')->map(fn (Collection $g, $userId) => [
            'name' => $names[$userId] ?? '—',
            'count' => $g->count(),
            'total' => round((float) $g->sum('total'), 2),
        ])->sortByDesc('total')->values();
    }
}
