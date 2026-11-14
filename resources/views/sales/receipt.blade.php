@extends('layouts.receipt')
@section('title', $sale->invoice_no)

@section('content')
@include('sales._receipt-header')

<table class="small">
    <tr><td>{{ __('Invoice') }}</td><td class="right bold">{{ $sale->invoice_no }}</td></tr>
    <tr><td>{{ __('Date') }}</td><td class="right">{{ $sale->sold_at->format('d/m/Y H:i') }}</td></tr>
    <tr><td>{{ __('Seller') }}</td><td class="right">{{ $sale->user?->name }}</td></tr>
    <tr><td>{{ __('Customer') }}</td><td class="right">{{ $sale->customer?->name ?? __('Walk-in') }}</td></tr>
</table>
<hr>

<table>
    @foreach ($sale->items as $item)
        <tr class="item"><td colspan="2" class="bold">{{ $item->product_name }}</td></tr>
        <tr>
            <td class="small">{{ qty($item->quantity) }} × {{ money($item->unit_price, false) }}</td>
            <td class="right nowrap">{{ money($item->subtotal, false) }}</td>
        </tr>
    @endforeach
</table>
<hr>

<table>
    <tr class="total"><td>{{ __('Total') }}</td><td class="right nowrap">{{ money($sale->total) }}</td></tr>
    <tr><td>{{ __('Paid') }} ({{ __(ucfirst($sale->payment_method)) }})</td><td class="right nowrap">{{ money($sale->amount_paid) }}</td></tr>
    @if ($sale->balance() > 0)
        <tr class="bold"><td>{{ __('Balance') }}</td><td class="right nowrap">{{ money($sale->balance()) }}</td></tr>
        @if ($sale->due_date)<tr><td>{{ __('Due date') }}</td><td class="right">{{ $sale->due_date->format('d/m/Y') }}</td></tr>@endif
    @endif
</table>

@include('sales._receipt-footer')
@endsection
