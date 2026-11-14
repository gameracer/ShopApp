@extends('layouts.receipt')
@section('title', __('Test receipt'))

@section('content')
@include('sales._receipt-header')

<div class="center bold">{{ __('Test receipt') }}</div>
<div class="center small">{{ now()->format('d/m/Y H:i') }} · {{ $paper }} mm</div>
<hr>
<table>
    <tr class="item"><td colspan="2" class="bold">{{ __('Sample item') }} 1</td></tr>
    <tr><td class="small">2 × {{ money(1500, false) }}</td><td class="right nowrap">{{ money(3000, false) }}</td></tr>
    <tr class="item"><td colspan="2" class="bold">{{ __('Sample item with a much longer product name') }}</td></tr>
    <tr><td class="small">1 × {{ money(12500, false) }}</td><td class="right nowrap">{{ money(12500, false) }}</td></tr>
</table>
<hr>
<table><tr class="total"><td>{{ __('Total') }}</td><td class="right nowrap">{{ money(15500) }}</td></tr></table>
{{-- Edge-to-edge ruler: both ends should be visible if the width is right. --}}
<hr>
<div class="small" style="display:flex;justify-content:space-between"><span>|&lt;</span><span>{{ __('Edges') }}</span><span>&gt;|</span></div>

@include('sales._receipt-footer')
@endsection
