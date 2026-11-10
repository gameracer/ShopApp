<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Tag;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $sales = Sale::with('customer', 'user')
            ->when($user->isSeller(), fn ($q) => $q->where('user_id', $user->id))
            ->when($user->isOwner() && $request->filled('seller'), fn ($q) => $q->where('user_id', $request->integer('seller')))
            ->when($request->input('q'), fn ($q, $term) => $q->where(function ($w) use ($term) {
                $w->where('invoice_no', 'like', "%$term%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%"));
            }))
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('sold_at', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('sold_at', '<=', $d))
            ->when($request->input('method'), fn ($q, $m) => $q->where('payment_method', $m))
            ->latest('sold_at')
            ->paginate(30)
            ->withQueryString();

        return view('sales.index', [
            'sales' => $sales,
            'sellers' => $user->isOwner() ? User::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function create()
    {
        return view('sales.create', [
            'tags' => Tag::orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    public function store(Request $request, SaleService $service)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(Sale::METHODS)],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'new_customer_name' => ['nullable', 'string', 'max:255'],
            'new_customer_phone' => ['nullable', 'string', 'max:30'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        // Sellers sell at the listed price; only the owner may override prices.
        if (! $request->user()->isOwner()) {
            foreach ($data['items'] as &$item) {
                unset($item['unit_price']);
            }
            unset($item);
        }

        $customer = null;
        if (! empty($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
        } elseif (! empty($data['new_customer_name'])) {
            $customer = Customer::create([
                'name' => $data['new_customer_name'],
                'phone' => $data['new_customer_phone'] ?? null,
            ]);
        }

        $sale = $service->create(
            $request->user(),
            $data['items'],
            $data['payment_method'],
            (float) ($data['amount_paid'] ?? 0),
            $customer,
            $data['due_date'] ?? null,
        );

        session()->flash('success', __('Sale completed.'));

        if ($request->expectsJson()) {
            return response()->json(['id' => $sale->id, 'redirect' => route('sales.show', $sale)]);
        }

        return redirect()->route('sales.show', $sale);
    }

    public function show(Request $request, Sale $sale)
    {
        $user = $request->user();
        abort_if($user->isSeller() && $sale->user_id !== $user->id && ! $sale->customer_id, 403);

        return view('sales.show', ['sale' => $sale->load('items', 'customer', 'user', 'payments.user')]);
    }

    public function destroy(Sale $sale, SaleService $service)
    {
        $service->void($sale->load('items'));

        return redirect()->route('sales.index')->with('success', __('Sale deleted and stock restored.'));
    }
}
