@extends('layouts.app')
@section('title', __('Sales'))

@section('content')
<x-page-header :title="__('Sales')">
    <x-slot:actions>
        <a href="{{ route('sales.create') }}" class="btn-primary"><x-icon name="cart" class="h-4 w-4" /> {{ __('New sale') }}</a>
    </x-slot:actions>
</x-page-header>

<form class="card mb-4 grid gap-2 p-4 sm:grid-cols-6" x-data>
    <input name="q" value="{{ request('q') }}" class="input sm:col-span-2" placeholder="{{ __('Invoice, customer or phone') }}">
    <input type="date" name="from" value="{{ request('from') }}" class="input" title="{{ __('From') }}">
    <input type="date" name="to" value="{{ request('to') }}" class="input" title="{{ __('To') }}">
    <select name="method" class="input">
        <option value="">{{ __('All methods') }}</option>
        @foreach (\App\Models\Sale::METHODS as $m)<option value="{{ $m }}" @selected(request('method') === $m)>{{ __(ucfirst($m)) }}</option>@endforeach
    </select>
    @if ($sellers->isNotEmpty())
        <select name="seller" class="input">
            <option value="">{{ __('All sellers') }}</option>
            @foreach ($sellers as $s)<option value="{{ $s->id }}" @selected(request('seller') == $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    @endif
    <div class="flex gap-2 sm:col-span-6">
        <button class="btn-secondary">{{ __('Filter') }}</button>
        @if (request()->hasAny(['q', 'from', 'to', 'method', 'seller']))<a href="{{ route('sales.index') }}" class="btn-secondary">{{ __('Clear') }}</a>@endif
    </div>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Seller') }}</th><th>{{ __('Method') }}</th><th class="text-right">{{ __('Total') }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($sales as $sale)
            <tr class="cursor-pointer" onclick="location='{{ route('sales.show', $sale) }}'">
                <td class="font-medium">{{ $sale->invoice_no }}</td>
                <td class="whitespace-nowrap">{{ $sale->sold_at->format('d/m/Y H:i') }}</td>
                <td>{{ $sale->customer?->name ?? '—' }}</td>
                <td class="text-slate-500">{{ $sale->user?->name }}</td>
                <td>@include('sales._method')</td>
                <td class="text-right font-medium">{{ money($sale->total, false) }}</td>
                <td class="text-right {{ $sale->balance() > 0 ? 'font-medium text-rose-700' : 'text-slate-400' }}">{{ $sale->balance() > 0 ? money($sale->balance(), false) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty>{{ __('No sales yet.') }}</x-empty></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $sales->links() }}</div>
@endsection
