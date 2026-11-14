@extends('layouts.app')
@section('title', $product->exists ? __('Edit product') : __('New product'))

@section('content')
<x-page-header :title="$product->exists ? __('Edit product') : __('New product')" />

@php $selected = old('tags', $product->tags?->pluck('id')->all() ?? []); @endphp
<form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="card max-w-3xl space-y-5 p-6"
      x-data="{ buy: {{ (float) old('buying_price', $product->buying_price ?? 0) }}, sell: {{ (float) old('selling_price', $product->selling_price ?? 0) }} }"
      @barcode.window.prevent="$refs.sku.value = $event.detail.code; $refs.sku.focus(); ShopDevices.beep()">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label class="label">{{ __('Product name') }} *</label>
            <input name="name" class="input" value="{{ old('name', $product->name) }}" required autofocus>
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">{{ __('SKU / Barcode') }}</label>
            <input name="sku" x-ref="sku" class="input" value="{{ old('sku', $product->sku) }}" placeholder="{{ __('Scan or type') }}">
            @error('sku')<p class="error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label class="label">{{ __('Buying price') }} *</label>
            <input name="buying_price" type="number" step="0.01" min="0" class="input" x-model.number="buy" required>
            @error('buying_price')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">{{ __('Selling price') }} *</label>
            <input name="selling_price" type="number" step="0.01" min="0" class="input" x-model.number="sell" required>
            @error('selling_price')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <span class="label">{{ __('Profit per unit') }}</span>
            <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold" :class="sell - buy < 0 ? 'text-rose-600' : 'text-emerald-700'">
                <span x-text="(sell - buy).toFixed(2)"></span>
                <span class="text-xs font-normal text-slate-500" x-show="buy > 0" x-text="'(' + ((sell - buy) / buy * 100).toFixed(1) + '%)'"></span>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        <div>
            <label class="label">{{ __('Quantity in stock') }} *</label>
            <input name="quantity" type="number" step="0.01" min="0" class="input" value="{{ old('quantity', (float) ($product->quantity ?? 0)) }}" required>
            @error('quantity')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="label">{{ __('Unit') }} *</label>
            <input name="unit" class="input" list="units" value="{{ old('unit', $product->unit) }}" required>
            <datalist id="units">
                @foreach (['pcs', 'kg', 'g', 'litre', 'ml', 'box', 'carton', 'packet', 'bottle', 'dozen', 'bag'] as $u)<option value="{{ $u }}">@endforeach
            </datalist>
        </div>
        <div>
            <label class="label">{{ __('Reorder level') }}</label>
            <input name="reorder_level" type="number" step="0.01" min="0" class="input" value="{{ old('reorder_level', (float) ($product->reorder_level ?? 0)) }}">
        </div>
        <div>
            <label class="label">{{ __('Expiry date') }}</label>
            <input name="expiry_date" type="date" class="input" value="{{ old('expiry_date', $product->expiry_date?->toDateString()) }}">
        </div>
    </div>

    <div>
        <span class="label">{{ __('Tags') }}</span>
        <div class="flex flex-wrap gap-2">
            @foreach ($tags as $tag)
                <label class="cursor-pointer">
                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="peer hidden" @checked(in_array($tag->id, $selected))>
                    <x-tag :tag="$tag" class="opacity-50 peer-checked:opacity-100 peer-checked:ring-2" />
                </label>
            @endforeach
        </div>
        <input name="new_tags" class="input mt-2" value="{{ old('new_tags') }}" placeholder="{{ __('Add new tags, separated by commas (e.g. drinks, soda)') }}">
    </div>

    <div>
        <label class="label">{{ __('Description') }}</label>
        <textarea name="description" rows="2" class="input">{{ old('description', $product->description) }}</textarea>
    </div>

    <div class="flex items-center gap-2">
        <button class="btn-primary">{{ __('Save') }}</button>
        <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>

@if ($product->exists)
    <form method="POST" action="{{ route('products.destroy', $product) }}" class="mt-4" onsubmit="return confirm('{{ __('Delete this product?') }}')">
        @csrf @method('DELETE')
        <button class="btn-danger btn-sm"><x-icon name="trash" class="h-4 w-4" /> {{ __('Delete product') }}</button>
    </form>
@endif
@endsection
