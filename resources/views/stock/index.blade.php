@extends('layouts.app')
@section('title', __('Stock history'))

@section('content')
@php $isOwner = auth()->user()->isOwner(); @endphp
<x-page-header :title="__('Stock history')">
    <x-slot:actions>
        <a href="{{ route('stock.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('Add stock') }}</a>
    </x-slot:actions>
</x-page-header>

<form class="mb-4 flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input max-w-sm" placeholder="{{ __('Search by name, SKU/barcode or tag') }}">
    <button class="btn-secondary">{{ __('Search') }}</button>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Product') }}</th><th class="text-right">{{ __('Quantity') }}</th>@if ($isOwner)<th class="text-right">{{ __('Buying price') }}</th><th class="text-right">{{ __('Total cost') }}</th>@endif<th>{{ __('Expiry') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('By') }}</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($entries as $entry)
            <tr>
                <td class="whitespace-nowrap">{{ $entry->created_at->format('d/m/Y H:i') }}</td>
                <td>@if ($entry->product)<a href="{{ route('products.show', $entry->product) }}" class="hover:underline">{{ $entry->product->name }}</a>@endif</td>
                <td class="text-right font-medium text-emerald-700">+{{ qty($entry->quantity) }} {{ $entry->product?->unit }}</td>
                @if ($isOwner)
                    <td class="text-right">{{ money($entry->buying_price, false) }}</td>
                    <td class="text-right">{{ money($entry->buying_price * $entry->quantity, false) }}</td>
                @endif
                <td>{{ $entry->expiry_date?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $entry->supplier ?? $entry->note ?? '—' }}</td>
                <td class="text-slate-500">{{ $entry->user?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $entries->links() }}</div>
@endsection
