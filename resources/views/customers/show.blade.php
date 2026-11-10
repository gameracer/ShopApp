@extends('layouts.app')
@section('title', $customer->name)

@section('content')
<x-page-header :title="$customer->name" :subtitle="collect([$customer->phone, $customer->address])->filter()->join(' · ')">
    <x-slot:actions>
        <a href="{{ route('customers.edit', $customer) }}" class="btn-secondary">{{ __('Edit') }}</a>
        @if (auth()->user()->isOwner() && $balance <= 0)
            <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('{{ __('Delete this customer?') }}')">
                @csrf @method('DELETE')
                <button class="btn-danger"><x-icon name="trash" class="h-4 w-4" /></button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="mb-4 grid gap-4 sm:grid-cols-3">
    <x-stat icon="receipt" tone="sky" :label="__('Purchases')" :value="$sales->count()" />
    <x-stat icon="cart" tone="emerald" :label="__('Total bought')" :value="money($sales->sum('total'))" />
    <x-stat icon="credit" :tone="$balance > 0 ? 'rose' : 'slate'" :label="__('Balance owed')" :value="money($balance)" />
</div>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th>{{ __('Due date') }}</th><th class="text-right">{{ __('Total') }}</th><th class="text-right">{{ __('Paid') }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($sales as $sale)
            <tr class="cursor-pointer" onclick="location='{{ route('sales.show', $sale) }}'">
                <td class="font-medium">{{ $sale->invoice_no }}</td>
                <td>{{ $sale->sold_at->format('d/m/Y') }}</td>
                <td>@include('sales._method')</td>
                <td @class(['text-rose-700 font-medium' => $sale->isOverdue()])>{{ $sale->due_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="text-right">{{ money($sale->total, false) }}</td>
                <td class="text-right">{{ money($sale->amount_paid, false) }}</td>
                <td class="text-right {{ $sale->balance() > 0 ? 'font-semibold text-rose-700' : 'text-slate-400' }}">{{ $sale->balance() > 0 ? money($sale->balance(), false) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
