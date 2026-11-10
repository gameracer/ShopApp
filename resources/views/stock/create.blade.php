@extends('layouts.app')
@section('title', __('Add stock'))

@section('content')
@php $isOwner = auth()->user()->isOwner(); @endphp
<x-page-header :title="__('Add stock')" :subtitle="__('Search items and register new stock received.')">
    <x-slot:actions>
        <a href="{{ route('stock.index') }}" class="btn-secondary">{{ __('Stock history') }}</a>
        @if ($isOwner)<a href="{{ route('products.create') }}" class="btn-secondary"><x-icon name="plus" class="h-4 w-4" /> {{ __('New product') }}</a>@endif
    </x-slot:actions>
</x-page-header>

<div x-data="stockApp(@js([
        'searchUrl' => route('products.search'),
        'storeUrl' => route('stock.store'),
        'currency' => \App\Support\Shop::currency(),
        'canEditPrice' => $isOwner,
        'preselected' => $preselected ? [
            'id' => $preselected->id, 'name' => $preselected->name, 'unit' => $preselected->unit,
            'quantity' => (float) $preselected->quantity, 'buying_price' => $isOwner ? (float) $preselected->buying_price : null,
            'selling_price' => (float) $preselected->selling_price,
        ] : null,
        'i18n' => ['failed' => __('Something went wrong. Please try again.')],
    ]))" class="grid gap-6 lg:grid-cols-5">
    <div class="lg:col-span-2">
        <x-product-picker :tags="$tags" />
    </div>

    <div class="card lg:col-span-3">
        <div class="card-header">
            <h2 class="card-title">{{ __('Items to receive') }} (<span x-text="lines.length"></span>)</h2>
        </div>
        <div class="space-y-3 p-4">
            <template x-for="(line, i) in lines" :key="line.product_id">
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium" x-text="line.name"></p>
                            <p class="text-xs text-slate-500">{{ __('Current stock') }}: <span x-text="line.current + ' ' + line.unit"></span></p>
                        </div>
                        <button type="button" @click="remove(i)" class="text-slate-400 hover:text-rose-600"><x-icon name="x" class="h-4 w-4" /></button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <label class="text-xs text-slate-500">{{ __('Quantity') }}
                            <input type="number" step="0.01" min="0.01" x-model.number="line.quantity" class="input mt-0.5">
                        </label>
                        @if ($isOwner)
                            <label class="text-xs text-slate-500">{{ __('Buying price') }}
                                <input type="number" step="0.01" min="0" x-model.number="line.buying_price" class="input mt-0.5">
                            </label>
                            <label class="text-xs text-slate-500">{{ __('Selling price') }}
                                <input type="number" step="0.01" min="0" x-model.number="line.selling_price" class="input mt-0.5">
                            </label>
                        @endif
                        <label class="text-xs text-slate-500">{{ __('Expiry date') }}
                            <input type="date" x-model="line.expiry_date" class="input mt-0.5">
                        </label>
                    </div>
                </div>
            </template>
            <p x-show="!lines.length" class="py-8 text-center text-sm text-slate-500">{{ __('Click a product on the left to add it.') }}</p>

            <div x-show="lines.length" class="space-y-3 border-t border-slate-100 pt-3">
                <div>
                    <label class="label">{{ __('Supplier') }}</label>
                    <input x-model="supplier" class="input" placeholder="{{ __('Optional') }}">
                </div>
                @if ($isOwner)
                    <p class="text-sm text-slate-600">{{ __('Total cost') }}: <strong x-text="money(totalCost)"></strong></p>
                @endif
                <ul x-show="errorList.length" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">
                    <template x-for="e in errorList"><li x-text="e"></li></template>
                </ul>
                <button type="button" @click="save()" :disabled="submitting" class="btn-primary w-full">{{ __('Save stock') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection
