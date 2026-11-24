<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockEntry;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

                $product->receiveStock(
                    (float) $item['quantity'],
                    isset($item['buying_price']) ? (float) $item['buying_price'] : (float) $product->buying_price,
                    isset($item['selling_price']) ? (float) $item['selling_price'] : (float) $product->selling_price,
                    $item['expiry_date'] ?? null,
                    $request->user(),
                    $data['supplier'] ?? null,
                );
            }
        });

        session()->flash('success', __('Stock added successfully.'));

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('stock.index')]);
        }

        return redirect()->route('stock.index');
    }

    public function adjust(Product $product)
    {
        return view('stock.adjust', ['product' => $product, 'reasons' => StockEntry::adjustmentReasons()]);
    }

    /**
     * Correct a product's stock to the quantity actually on the shelf, recording the
     * difference (plus or minus) and the reason in the stock history.
     */
    public function storeAdjustment(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', Rule::in(array_keys(StockEntry::adjustmentReasons()))],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $changed = DB::transaction(function () use ($data, $request, $product) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $reason = StockEntry::adjustmentReasons()[$data['reason']];

            return $product->adjustStock(
                (float) $data['quantity'],
                $request->user(),
                filled($data['note'] ?? null) ? $reason.': '.$data['note'] : $reason,
            );
        });

        if (! $changed) {
            return back()->withInput()->withErrors(['quantity' => __('The quantity is the same as the current stock.')]);
        }

        return redirect()->route('products.show', $product)->with('success', __('Stock adjusted.'));
    }
}
