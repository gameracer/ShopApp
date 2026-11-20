<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockEntry;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $entries = StockEntry::with('product', 'user')
            ->when($request->input('q'), fn ($q, $term) => $q->whereHas('product', fn ($p) => $p->search($term)))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('stock.index', ['entries' => $entries]);
    }

    public function create(Request $request)
    {
        return view('stock.create', [
            'tags' => Tag::orderBy('name')->get(),
            'preselected' => $request->filled('product')
                ? Product::find($request->integer('product'))
                : null,
        ]);
    }

    /**
     * Receive goods: one or many lines, each increasing a product's stock.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.buying_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                $buying = isset($item['buying_price']) ? (float) $item['buying_price'] : (float) $product->buying_price;
                $selling = isset($item['selling_price']) ? (float) $item['selling_price'] : (float) $product->selling_price;

                $product->stockEntries()->create([
                    'user_id' => $request->user()->id,
                    'quantity' => $item['quantity'],
                    'buying_price' => $buying,
                    'selling_price' => $selling,
                    'expiry_date' => $item['expiry_date'] ?? null,
                    'supplier' => $data['supplier'] ?? null,
                ]);

                // Keep the earliest expiry while older stock is still on the shelf,
                // so near-expiry alerts are not hidden by a fresher batch.
                if (! empty($item['expiry_date'])) {
                    $newExpiry = Carbon::parse($item['expiry_date']);
                    $hasOldStock = (float) $product->quantity > 0 && $product->expiry_date !== null;
                    $product->expiry_date = $hasOldStock && $product->expiry_date->lt($newExpiry)
                        ? $product->expiry_date
                        : $newExpiry;
                }

                $product->quantity = round((float) $product->quantity + (float) $item['quantity'], 2);
                $product->buying_price = $buying;
                $product->selling_price = $selling;
                $product->save();
            }
        });

        session()->flash('success', __('Stock added successfully.'));

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('stock.index')]);
        }

        return redirect()->route('stock.index');
    }
}
