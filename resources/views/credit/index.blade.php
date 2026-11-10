@extends('layouts.app')
@section('title', __('Credit buyers'))

@section('content')
<x-page-header :title="__('Credit buyers')" :subtitle="__('Customers who bought on credit and still owe money.')" />

<div class="mb-4 grid gap-4 sm:grid-cols-3">
    <x-stat icon="credit" tone="rose" :label="__('Total outstanding')" :value="money($totalOutstanding)" />
    <x-stat icon="users" tone="amber" :label="__('Customers owing')" :value="$customers->count()" />
    <x-stat icon="receipt" tone="sky" :label="__('Unpaid invoices')" :value="$unpaidSales->count()" :hint="__('Overdue').': '.$unpaidSales->filter->isOverdue()->count()" />
</div>

<form class="mb-4 flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input max-w-sm" placeholder="{{ __('Search name or phone') }}">
    <button class="btn-secondary">{{ __('Search') }}</button>
</form>

<div class="grid gap-6 xl:grid-cols-2">
    <div class="card overflow-x-auto">
        <div class="card-header"><h2 class="card-title">{{ __('By customer') }}</h2></div>
        <table class="table">
            <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Phone') }}</th><th class="text-right">{{ __('Invoices') }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($customers as $customer)
                <tr class="cursor-pointer" onclick="location='{{ route('customers.show', $customer) }}'">
                    <td class="font-medium">{{ $customer->name }}</td>
                    <td>@if ($customer->phone)<a href="tel:{{ $customer->phone }}" class="text-emerald-700 hover:underline" onclick="event.stopPropagation()">{{ $customer->phone }}</a>@else — @endif</td>
                    <td class="text-right">{{ $customer->unpaid_count }}</td>
                    <td class="text-right font-semibold text-rose-700">{{ money($customer->balance, false) }}</td>
                </tr>
            @empty
                <tr><td colspan="4"><x-empty>{{ __('No one owes the shop money.') }}</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card overflow-x-auto">
        <div class="card-header"><h2 class="card-title">{{ __('Unpaid invoices') }}</h2></div>
        <table class="table">
            <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Due date') }}</th><th class="text-right">{{ __('Total') }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($unpaidSales as $sale)
                <tr class="cursor-pointer" onclick="location='{{ route('sales.show', $sale) }}'">
                    <td class="font-medium whitespace-nowrap">{{ $sale->invoice_no }}<div class="text-xs text-slate-500">{{ $sale->sold_at->format('d/m/Y') }}</div></td>
                    <td>{{ $sale->customer?->name }}</td>
                    <td>
                        @if ($sale->due_date)
                            <span @class(['font-medium text-rose-700' => $sale->isOverdue()])>{{ $sale->due_date->format('d/m/Y') }}</span>
                            @if ($sale->isOverdue())<span class="ml-1 rounded-full bg-rose-100 px-1.5 text-xs text-rose-700">{{ __('Overdue') }}</span>@endif
                        @else — @endif
                    </td>
                    <td class="text-right">{{ money($sale->total, false) }}</td>
                    <td class="text-right font-semibold text-rose-700">{{ money($sale->balance(), false) }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty>{{ __('No unpaid invoices.') }}</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
