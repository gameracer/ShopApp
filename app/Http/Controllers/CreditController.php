<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditController extends Controller
{
    /**
     * Buyers who purchased on credit and still owe money.
     */
    public function index(Request $request)
    {
        $customers = Customer::withBalance()
            ->withCount(['sales as unpaid_count' => fn ($q) => $q->unpaid()])
            ->withMin(['sales as oldest_due' => fn ($q) => $q->unpaid()], 'due_date')
            ->when($request->input('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))
            ->orderBy('name')
            ->get()
            ->filter(fn (Customer $c) => $c->balance > 0)
            ->sortByDesc('balance');

        $unpaidSales = Sale::unpaid()->with('customer')
            ->whereNotNull('customer_id')
            ->orderByRaw('due_date IS NULL, due_date')
            ->orderBy('sold_at')
            ->get();

        return view('credit.index', [
            'customers' => $customers,
            'unpaidSales' => $unpaidSales,
            'totalOutstanding' => $customers->sum('balance'),
        ]);
    }

    public function pay(Request $request, Sale $sale, SaleService $service)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'mobile', 'bank'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $service->addPayment($sale, $request->user(), (float) $data['amount'], $data['method'], $data['note'] ?? null);

        return back()->with('success', __('Payment recorded.'));
    }
}
