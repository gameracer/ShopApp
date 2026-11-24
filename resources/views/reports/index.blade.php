@extends('layouts.app')
@section('title', __('Reports'))

@section('content')
@php
    $s = $report['summary'];
    $titles = ['daily' => __('Daily report'), 'monthly' => __('Monthly report'), 'yearly' => __('Yearly report')];
    $subtitle = match ($period) {
        'yearly' => $start->format('Y'),
        'monthly' => $start->translatedFormat('F Y'),
        default => $start->translatedFormat('l, d F Y'),
    };
    $inputValue = match ($period) {
        'yearly' => $start->format('Y'),
        'monthly' => $start->format('Y-m'),
        default => $start->toDateString(),
    };
    $maxRevenue = max(1, collect($report['breakdown'])->max('revenue') ?? 1);
@endphp

<x-page-header :title="$titles[$period]" :subtitle="$subtitle.' · '.\App\Support\Shop::name()">
    <x-slot:actions>
        <a href="{{ route('reports.export', ['format' => 'xlsx'] + request()->query()) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> Excel</a>
        <a href="{{ route('reports.export', ['format' => 'csv'] + request()->query()) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> CSV</a>
        <button onclick="window.print()" class="btn-secondary"><x-icon name="print" class="h-4 w-4" /> {{ __('Print') }}</button>
    </x-slot:actions>
</x-page-header>

<form class="no-print card mb-6 flex flex-wrap items-end gap-3 p-4" x-data="{ period: '{{ $period }}' }">
    <div class="inline-flex overflow-hidden rounded-lg border border-slate-300">
        @foreach ($titles as $key => $label)
            <label class="cursor-pointer">
                <input type="radio" name="period" value="{{ $key }}" x-model="period" class="peer hidden">
                <span class="block px-4 py-2 text-sm font-medium text-slate-600 peer-checked:bg-emerald-600 peer-checked:text-white">{{ __(ucfirst($key)) }}</span>
            </label>
        @endforeach
    </div>
    <div>
        <template x-if="period === 'daily'"><input type="date" name="date" value="{{ $period === 'daily' ? $inputValue : today()->toDateString() }}" class="input"></template>
        <template x-if="period === 'monthly'"><input type="month" name="date" value="{{ $period === 'monthly' ? $inputValue : today()->format('Y-m') }}" class="input"></template>
        <template x-if="period === 'yearly'"><input type="number" name="date" min="2000" max="2100" value="{{ $period === 'yearly' ? $inputValue : today()->year }}" class="input w-28"></template>
    </div>
    <select name="seller" class="input w-auto">
        <option value="">{{ __('All sellers') }}</option>
        @foreach ($sellers as $seller)<option value="{{ $seller->id }}" @selected($sellerId === $seller->id)>{{ $seller->name }}</option>@endforeach
    </select>
    <button class="btn-primary">{{ __('Show report') }}</button>
</form>

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat icon="cart" tone="emerald" :label="__('Revenue')" :value="money($s['revenue'])" :hint="trans_choice(':count sale|:count sales', $s['sales_count'])" />
    <x-stat icon="chart" tone="sky" :label="__('Gross profit')" :value="money($s['gross_profit'])" :hint="__('Cost of goods').': '.money($s['cost'])" />
    <x-stat icon="wallet" tone="amber" :label="__('Expenses')" :value="money($s['expenses'])" />
    <x-stat icon="receipt" :tone="$s['net_profit'] >= 0 ? 'violet' : 'rose'" :label="__('Net profit')" :value="money($s['net_profit'])" />
    <x-stat icon="download" tone="emerald" :label="__('Money received')" :value="money($s['cash_received'])" :hint="__('Includes credit repayments')" />
    <x-stat icon="credit" tone="rose" :label="__('Sold on credit (unpaid)')" :value="money($s['credit_given'])" />
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="card overflow-x-auto lg:col-span-2">
        <div class="card-header"><h2 class="card-title">{{ $period === 'daily' ? __('Sales by hour') : ($period === 'monthly' ? __('Day by day') : __('Month by month')) }}</h2></div>
        <table class="table">
            <thead><tr><th>{{ __('Period') }}</th><th class="text-right">{{ __('Sales') }}</th><th class="text-right">{{ __('Revenue') }}</th><th class="text-right">{{ __('Gross profit') }}</th>@if ($period !== 'daily')<th class="text-right">{{ __('Expenses') }}</th><th class="text-right">{{ __('Net profit') }}</th>@endif</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($report['breakdown'] as $row)
                <tr>
                    <td class="whitespace-nowrap">
                        {{ $row['label'] }}
                        <div class="no-print mt-1 h-1 w-24 rounded bg-slate-100"><div class="h-1 rounded bg-emerald-500" style="width: {{ round($row['revenue'] / $maxRevenue * 100) }}%"></div></div>
                    </td>
                    <td class="text-right">{{ $row['count'] }}</td>
                    <td class="text-right">{{ money($row['revenue'], false) }}</td>
                    <td class="text-right">{{ money($row['profit'], false) }}</td>
                    @if ($period !== 'daily')
                        <td class="text-right">{{ money($row['expenses'], false) }}</td>
                        <td class="text-right font-medium {{ $row['net'] < 0 ? 'text-rose-600' : '' }}">{{ money($row['net'], false) }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="6"><x-empty>{{ __('No sales in this period.') }}</x-empty></td></tr>
            @endforelse
            </tbody>
            @if (count($report['breakdown']))
                <tfoot class="border-t-2 border-slate-200 font-semibold">
                    <tr>
                        <td class="px-3 py-2">{{ __('Total') }}</td>
                        <td class="px-3 py-2 text-right">{{ $s['sales_count'] }}</td>
                        <td class="px-3 py-2 text-right">{{ money($s['revenue'], false) }}</td>
                        <td class="px-3 py-2 text-right">{{ money($s['gross_profit'], false) }}</td>
                        @if ($period !== 'daily')
                            <td class="px-3 py-2 text-right">{{ money($s['expenses'], false) }}</td>
                            <td class="px-3 py-2 text-right">{{ money($s['net_profit'], false) }}</td>
                        @endif
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Expenses by category') }}</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($report['expenses_by_category'] as $category => $amount)
                    <li class="flex justify-between px-4 py-2"><span>{{ __(ucfirst($category)) }}</span><span class="font-medium">{{ money($amount, false) }}</span></li>
                @empty
                    <li><x-empty>{{ __('No expenses recorded.') }}</x-empty></li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Payment methods') }}</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($report['by_method'] as $method => $row)
                    <li class="flex justify-between px-4 py-2"><span>{{ __(ucfirst($method)) }} <span class="text-xs text-slate-500">({{ $row['count'] }})</span></span><span class="font-medium">{{ money($row['total'], false) }}</span></li>
                @empty
                    <li><x-empty /></li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ __('Sales by seller') }}</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($report['by_seller'] as $row)
                    <li class="flex justify-between px-4 py-2"><span>{{ $row['name'] }} <span class="text-xs text-slate-500">({{ $row['count'] }})</span></span><span class="font-medium">{{ money($row['total'], false) }}</span></li>
                @empty
                    <li><x-empty /></li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="card overflow-x-auto lg:col-span-3">
        <div class="card-header"><h2 class="card-title">{{ __('Top products') }}</h2></div>
        <table class="table">
            <thead><tr><th>#</th><th>{{ __('Product') }}</th><th class="text-right">{{ __('Quantity') }}</th><th class="text-right">{{ __('Revenue') }}</th><th class="text-right">{{ __('Profit') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($report['top_products'] as $i => $p)
                <tr>
                    <td class="text-slate-400">{{ $i + 1 }}</td>
                    <td class="font-medium">{{ $p->product_name }}</td>
                    <td class="text-right">{{ qty($p->qty) }}</td>
                    <td class="text-right">{{ money($p->revenue, false) }}</td>
                    <td class="text-right text-emerald-700">{{ money($p->profit, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty>{{ __('No sales in this period.') }}</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
