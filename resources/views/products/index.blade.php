@extends('layouts.app')
@section('title', __('Products'))

@section('content')
@php $isOwner = auth()->user()->isOwner(); @endphp
<x-page-header :title="__('Products')" :subtitle="trans_choice(':count product|:count products', $products->total())">
    <x-slot:actions>
        @if ($isOwner)
            <a href="{{ route('stock.create') }}" class="btn-secondary"><x-icon name="truck" class="h-4 w-4" /> {{ __('Add stock') }}</a>
            <a href="{{ route('products.import') }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4 rotate-180" /> {{ __('Import') }}</a>
            <a href="{{ route('products.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('New product') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

<form method="GET" class="card mb-4 space-y-3 p-4" x-data>
    <div class="flex flex-wrap gap-2">
        <div class="relative min-w-56 flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
            <input name="q" value="{{ request('q') }}" class="input pl-9" placeholder="{{ __('Search by name, SKU/barcode or tag') }}" autofocus>
        </div>
        <select name="filter" class="input w-auto" @change="$el.form.submit()">
            <option value="">{{ __('All products') }}</option>
            <option value="low" @selected(request('filter') === 'low')>{{ __('Low stock') }}</option>
            <option value="out" @selected(request('filter') === 'out')>{{ __('Out of stock') }}</option>
            <option value="expiring" @selected(request('filter') === 'expiring')>{{ __('Near expiry') }}</option>
        </select>
        <select name="sort" class="input w-auto" @change="$el.form.submit()">
            <option value="name">{{ __('Sort: Name') }}</option>
            <option value="quantity" @selected(request('sort') === 'quantity')>{{ __('Sort: Quantity') }}</option>
            <option value="selling_price" @selected(request('sort') === 'selling_price')>{{ __('Sort: Price') }}</option>
            <option value="expiry_date" @selected(request('sort') === 'expiry_date')>{{ __('Sort: Expiry date') }}</option>
            <option value="created_at" @selected(request('sort') === 'created_at')>{{ __('Sort: Newest') }}</option>
        </select>
        <button class="btn-primary">{{ __('Search') }}</button>
        @if (request()->hasAny(['q', 'tags', 'filter', 'sort']))
            <a href="{{ route('products.index') }}" class="btn-secondary">{{ __('Clear') }}</a>
        @endif
    </div>
    @if ($tags->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-500">{{ __('Filter by tags') }}:</span>
            @foreach ($tags as $tag)
                <label class="cursor-pointer">
                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="peer hidden" @checked(in_array($tag->id, $selectedTags)) @change="$el.form.submit()">
                    <x-tag :tag="$tag" :active="in_array($tag->id, $selectedTags)" class="opacity-70 peer-checked:opacity-100 hover:opacity-100" />
                    <span class="sr-only">{{ $tag->products_count }}</span>
                </label>
            @endforeach
        </div>
    @endif
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead>
        <tr>
            <th>{{ __('Product') }}</th>
            <th>{{ __('Tags') }}</th>
            @if ($isOwner)<th class="text-right">{{ __('Buying price') }}</th>@endif
            <th class="text-right">{{ __('Selling price') }}</th>
            <th class="text-right">{{ __('In stock') }}</th>
            <th>{{ __('Expiry') }}</th>
            <th></th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($products as $product)
            <tr>
                <td>
                    <a href="{{ route('products.show', $product) }}" class="font-medium text-slate-900 hover:underline">{{ $product->name }}</a>
                    @if ($product->sku)<div class="text-xs text-slate-500">{{ $product->sku }}</div>@endif
                </td>
                <td><div class="flex flex-wrap gap-1">@foreach ($product->tags as $tag)<a href="{{ route('products.index', ['tags' => [$tag->id]]) }}"><x-tag :tag="$tag" /></a>@endforeach</div></td>
                @if ($isOwner)<td class="text-right text-slate-500">{{ money($product->buying_price, false) }}</td>@endif
                <td class="text-right font-medium">{{ money($product->selling_price, false) }}</td>
                <td class="text-right">
                    <span @class(['font-medium', 'text-rose-600' => $product->quantity <= 0, 'text-amber-700' => $product->quantity > 0 && $product->isLowStock()])>{{ qty($product->quantity) }}</span>
                    <span class="text-xs text-slate-500">{{ $product->unit }}</span>
                </td>
                <td>@include('products._expiry', ['product' => $product])</td>
                <td class="text-right whitespace-nowrap">
                    @if ($isOwner)
                        <a href="{{ route('stock.create', ['product' => $product->id]) }}" class="btn-secondary btn-sm" title="{{ __('Add stock') }}"><x-icon name="plus" class="h-3.5 w-3.5" /></a>
                        <a href="{{ route('products.edit', $product) }}" class="btn-secondary btn-sm">{{ __('Edit') }}</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty>{{ __('No products found.') }}</x-empty></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
@endsection
