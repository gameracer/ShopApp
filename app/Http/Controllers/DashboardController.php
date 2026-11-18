<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports)
    {
        $user = $request->user();
        $today = Sale::query()
            ->whereDate('sold_at', today())
            ->when($user->isSeller(), fn ($q) => $q->where('user_id', $user->id));

        $month = Sale::query()->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()]);

        $stats = [
            'today_sales' => (float) (clone $today)->sum('total'),
            'today_count' => (clone $today)->count(),
            'today_profit' => (float) (clone $today)->sum('total') - (float) (clone $today)->sum('cost_total'),
            'today_expenses' => (float) Expense::whereDate('expense_date', today())->sum('amount'),
            'month_sales' => (float) (clone $month)->sum('total'),
            'month_profit' => (float) (clone $month)->sum('total') - (float) (clone $month)->sum('cost_total'),
            'month_expenses' => (float) Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'credit_outstanding' => (float) Sale::unpaid()->sum('total') - (float) Sale::unpaid()->sum('amount_paid'),
            'products' => Product::count(),
            'stock_value' => (float) Product::where('quantity', '>', 0)->selectRaw('SUM(quantity * buying_price) as v')->value('v'),
        ];

        // Sellers get a launcher of big cards instead of the owner's figures.
        if ($user->isSeller()) {
            return view('seller-home', [
                'stats' => $stats,
                'lowStock' => Product::lowStock()->count(),
                'debtors' => Customer::withBalance()->get()->filter(fn ($c) => $c->balance > 0)->count(),
            ]);
        }

        return view('dashboard', [
            'stats' => $stats,
            'trend' => $user->isOwner() ? $reports->salesTrend() : null,
            'nearExpiry' => Product::nearExpiry()->orderBy('expiry_date')->limit(8)->get(),
            'lowStock' => Product::lowStock()->orderBy('quantity')->limit(8)->get(),
            'recentSales' => Sale::with('customer', 'user')
                ->when($user->isSeller(), fn ($q) => $q->where('user_id', $user->id))
                ->latest('sold_at')->limit(8)->get(),
            'topDebtors' => Customer::withBalance()->get()->filter(fn ($c) => $c->balance > 0)->sortByDesc('balance')->take(5),
        ]);
    }
}
