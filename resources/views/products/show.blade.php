@extends('layouts.app')
@section('title', $product->name)

@section('content')
@php $isOwner = auth()->user()->isOwner(); @endphp
<x-page-header :title="$product->name" :subtitle="$product->sku">
    <x-slot:actions>
        @if ($isOwner)
            <a href="{{ route('stock.create', ['product' => $product->id]) }}" class="btn-primary"><x-icon name="truck" class="h-4 w-4" /> {{ __('Add stock') }}</a>
            <a href="{{ route('products.edit', $product) }}" class="btn-secondary">{{ __('Edit') }}</a>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="mb-4 flex flex-wrap gap-1">@foreach ($product->tags as $tag)<x-tag :tag="$tag" />@endforeach</div>

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat icon="box" tone="emerald" :label="__('In stock')" :value="qty($product->quantity).' '.$product->unit" :hint="__('Reorder level').': '.qty($product->reorder_level)" />
    <x-stat icon="tag" tone="sky" :label="__('Selling price')" :value="money($product->selling_price)" />
    @if ($isOwner)
        <x-stat icon="wallet" tone="violet" :label="__('Buying price')" :value="money($product->buying_price)" :hint="__('Profit per unit').': '.money($product->selling_price - $product->buying_price)" />
    @endif
    <x-stat icon="chart" tone="amber" :label="__('Sold this month')" :value="qty($soldThisMonth).' '.$product->unit" />
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <div class="card p-4 text-sm">
        <dl class="space-y-2">
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Expiry date') }}</dt><dd>@include('products._expiry', ['product' => $product])</dd></div>
            @if ($product->expiry_date)<div class="flex justify-between"><dt class="text-slate-500"></dt><dd>{{ $product->expiry_date->format('d/m/Y') }}</dd></div>@endif
            @if ($isOwner)<div class="flex justify-between"><dt class="text-slate-500">{{ __('Stock value at cost') }}</dt><dd>{{ money($product->quantity * $product->buying_price) }}</dd></div>@endif
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Stock value at selling price') }}</dt><dd>{{ money($product->quantity * $product->selling_price) }}</dd></div>
        </dl>
        @if ($product->description)<p class="mt-4 text-slate-600">{{ $product->description }}</p>@endif
    </div>

    <div class="card lg:col-span-2">
        <div class="card-header"><h2 class="card-title">{{ __('Stock history') }}</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('Date') }}</th><th class="text-right">{{ __('Quantity') }}</th>@if ($isOwner)<th class="text-right">{{ __('Buying price') }}</th>@endif<th>{{ __('Expiry') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('By') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($product->stockEntries as $entry)
                    <tr>
                        <td>{{ $entry->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-right font-medium text-emerald-700">+{{ qty($entry->quantity) }}</td>
                        @if ($isOwner)<td class="text-right">{{ money($entry->buying_price, false) }}</td>@endif
                        <td>{{ $entry->expiry_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $entry->supplier ?? $entry->note ?? '—' }}</td>
                        <td class="text-slate-500">{{ $entry->user?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
