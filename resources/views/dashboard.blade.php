@extends('layouts.app')
@section('title', __('Dashboard'))

@section('content')
<x-page-header :title="__('Welcome, :name', ['name' => auth()->user()->name])" :subtitle="now()->translatedFormat('l, d F Y')">
    <x-slot:actions>
        <a href="{{ route('sales.create') }}" class="btn-primary"><x-icon name="cart" class="h-4 w-4" /> {{ __('New sale') }}</a>
        <a href="{{ route('stock.create') }}" class="btn-secondary"><x-icon name="truck" class="h-4 w-4" /> {{ __('Add stock') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat icon="cart" tone="emerald" :label="auth()->user()->isOwner() ? __('Sales today') : __('My sales today')" :value="money($stats['today_sales'])" :hint="trans_choice(':count sale|:count sales', $stats['today_count'])" />
    @if (auth()->user()->isOwner())
        <x-stat icon="chart" tone="sky" :label="__('Profit today')" :value="money($stats['today_profit'])" :hint="__('Expenses').': '.money($stats['today_expenses'])" />
        <x-stat icon="wallet" tone="violet" :label="__('This month net profit')" :value="money($stats['month_profit'] - $stats['month_expenses'])" :hint="__('Sales').': '.money($stats['month_sales'])" />
    @else
        <x-stat icon="box" tone="sky" :label="__('Products')" :value="number_format($stats['products'])" />
    @endif
    <x-stat icon="credit" tone="amber" :label="__('Credit outstanding')" :value="money($stats['credit_outstanding'])" />
</div>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-header">
            <h2 class="card-title">{{ __('Recent sales') }}</h2>
            <a href="{{ route('sales.index') }}" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('View all') }}</a>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Time') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Method') }}</th><th class="text-right">{{ __('Total') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($recentSales as $sale)
                    <tr class="cursor-pointer" onclick="location='{{ route('sales.show', $sale) }}'">
                        <td class="font-medium">{{ $sale->invoice_no }}</td>
                        <td class="text-slate-500">{{ $sale->sold_at->format('d/m H:i') }}</td>
                        <td>{{ $sale->customer?->name ?? '—' }}</td>
                        <td>@include('sales._method', ['sale' => $sale])</td>
                        <td class="text-right font-medium">{{ money($sale->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty>{{ __('No sales yet.') }}</x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2"><x-icon name="warning" class="h-4 w-4 text-rose-600" /> {{ __('Near expiry') }}</h2>
                <a href="{{ route('alerts') }}" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('View all') }}</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($nearExpiry as $product)
                    <li class="flex items-center justify-between gap-2 px-4 py-2 text-sm">
                        <a href="{{ route('products.show', $product) }}" class="truncate hover:underline">{{ $product->name }}</a>
                        @include('products._expiry', ['product' => $product])
                    </li>
                @empty
                    <li><x-empty>{{ __('No items are near expiry.') }}</x-empty></li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Low stock') }}</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($lowStock as $product)
                    <li class="flex items-center justify-between gap-2 px-4 py-2 text-sm">
                        <a href="{{ route('products.show', $product) }}" class="truncate hover:underline">{{ $product->name }}</a>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ qty($product->quantity) }} {{ $product->unit }}</span>
                    </li>
                @empty
                    <li><x-empty>{{ __('Stock levels look good.') }}</x-empty></li>
                @endforelse
            </ul>
        </div>

        @if ($topDebtors->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __('Top credit buyers') }}</h2>
                    <a href="{{ route('credit.index') }}" class="text-xs font-medium text-emerald-700 hover:underline">{{ __('View all') }}</a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($topDebtors as $customer)
                        <li class="flex items-center justify-between px-4 py-2 text-sm">
                            <a href="{{ route('customers.show', $customer) }}" class="hover:underline">{{ $customer->name }}</a>
                            <span class="font-medium text-rose-700">{{ money($customer->balance) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
