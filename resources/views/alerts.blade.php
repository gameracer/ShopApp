@extends('layouts.app')
@section('title', __('Alerts'))

@section('content')
<x-page-header :title="__('Alerts')" :subtitle="__('Items expiring within :days days, low stock and overdue credit.', ['days' => $days])" />

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="card lg:col-span-2">
        <div class="card-header">
            <h2 class="card-title flex items-center gap-2 text-rose-700"><x-icon name="warning" class="h-4 w-4" /> {{ __('Expired items') }} ({{ $expired->count() }})</h2>
        </div>
        @include('alerts._products', ['items' => $expired, 'empty' => __('No expired items in stock.')])
    </div>

    <div class="card lg:col-span-2">
        <div class="card-header">
            <h2 class="card-title flex items-center gap-2 text-amber-700"><x-icon name="bell" class="h-4 w-4" /> {{ __('Near expiry') }} ({{ $expiring->count() }})</h2>
        </div>
        @include('alerts._products', ['items' => $expiring, 'empty' => __('No items are near expiry.')])
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">{{ __('Low stock') }} ({{ $lowStock->count() }})</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th class="text-right">{{ __('In stock') }}</th><th class="text-right">{{ __('Reorder level') }}</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($lowStock as $product)
                    <tr>
                        <td><a href="{{ route('products.show', $product) }}" class="hover:underline">{{ $product->name }}</a></td>
                        <td class="text-right font-medium {{ $product->quantity <= 0 ? 'text-rose-600' : 'text-amber-700' }}">{{ qty($product->quantity) }} {{ $product->unit }}</td>
                        <td class="text-right">{{ qty($product->reorder_level) }}</td>
                        <td class="text-right"><a href="{{ route('stock.create', ['product' => $product->id]) }}" class="btn-secondary btn-sm">{{ __('Restock') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty>{{ __('Stock levels look good.') }}</x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">{{ __('Overdue credit') }} ({{ $overdue->count() }})</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Due date') }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($overdue as $sale)
                    <tr class="cursor-pointer" onclick="location='{{ route('sales.show', $sale) }}'">
                        <td>{{ $sale->customer?->name }}<div class="text-xs text-slate-500">{{ $sale->invoice_no }}</div></td>
                        <td class="text-rose-700">{{ $sale->due_date->format('d/m/Y') }}</td>
                        <td class="text-right font-medium">{{ money($sale->balance()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty>{{ __('No overdue credit.') }}</x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
