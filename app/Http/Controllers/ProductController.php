<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Tag;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $tagIds = array_map('intval', (array) $request->input('tags', []));

        $products = Product::query()
            ->with('tags')
            ->search($request->input('q'))
            ->withTags($tagIds)
            ->when($request->input('filter') === 'low', fn ($q) => $q->lowStock())
            ->when($request->input('filter') === 'expiring', fn ($q) => $q->nearExpiry())
            ->when($request->input('filter') === 'out', fn ($q) => $q->where('quantity', '<=', 0))
            ->orderBy($this->sortColumn($request), $request->input('dir') === 'desc' ? 'desc' : 'asc')
            ->paginate(25)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'tags' => Tag::withCount('products')->orderBy('name')->get(),
            'selectedTags' => $tagIds,
        ]);
    }

    /**
     * Live search used by the point of sale and stock-in screens.
     */
    public function search(Request $request)
    {
        $isOwner = $request->user()->isOwner();

        $products = Product::query()
            ->with('tags:id,name,color')
            ->search($request->input('q'))
            ->withTags(array_map('intval', (array) $request->input('tags', [])))
            ->when($request->boolean('in_stock'), fn ($q) => $q->where('quantity', '>', 0)
                ->where(fn ($e) => $e->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today()->toDateString())))
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json($products->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'unit' => $p->unit,
            'selling_price' => (float) $p->selling_price,
            'buying_price' => $isOwner ? (float) $p->buying_price : null,
            'quantity' => (float) $p->quantity,
            'expiry_date' => $p->expiry_date?->toDateString(),
            'tags' => $p->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color]),
        ]));
    }

    public function create()
    {
        return view('products.form', ['product' => new Product(['unit' => 'pcs']), 'tags' => Tag::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            $product->tags()->sync($this->tagIds($request));

            if ((float) $product->quantity > 0) {
                $product->stockEntries()->create([
                    'user_id' => $request->user()->id,
                    'quantity' => $product->quantity,
                    'buying_price' => $product->buying_price,
                    'selling_price' => $product->selling_price,
                    'expiry_date' => $product->expiry_date,
                    'note' => __('Opening stock'),
                ]);
            }

            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', __('Product saved.'));
    }

    public function show(Product $product)
    {
        $product->load(['tags', 'stockEntries' => fn ($q) => $q->with('user')->latest()->limit(20)]);

        $sold = $product->saleItems()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sold_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('sale_items.quantity');

        return view('products.show', ['product' => $product, 'soldThisMonth' => (float) $sold]);
    }

    public function edit(Product $product)
    {
        return view('products.form', ['product' => $product->load('tags'), 'tags' => Tag::orderBy('name')->get()]);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));
        $product->tags()->sync($this->tagIds($request));

        return redirect()->route('products.show', $product)->with('success', __('Product saved.'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', __('Product deleted.'));
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) use ($product) {
                if (is_string($value) && Product::nameTaken($value, $product?->id)) {
                    $fail(__('A product with this name already exists.'));
                }
            }],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product)],
            'unit' => ['required', 'string', 'max:30'],
            'buying_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'expiry_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'string', 'max:255'],
        ]) + ['reorder_level' => 0];
    }

    /**
     * Selected tag ids plus any comma separated new tags typed by the user.
     */
    private function tagIds(Request $request): array
    {
        $ids = array_map('intval', (array) $request->input('tags', []));

        return array_values(array_unique([
            ...$ids,
            ...Tag::idsForNames(explode(',', (string) $request->input('new_tags'))),
        ]));
    }

    private function sortColumn(Request $request): string
    {
        return in_array($request->input('sort'), ['name', 'quantity', 'selling_price', 'expiry_date', 'created_at'], true)
            ? $request->input('sort')
            : 'name';
    }
}
