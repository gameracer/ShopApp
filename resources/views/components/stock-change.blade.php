@props(['quantity', 'unit' => null])
{{-- A stock history quantity: green "+5" for stock in, red "−5" for stock taken off by an adjustment. --}}
@if ((float) $quantity < 0)
    <span {{ $attributes->merge(['class' => 'font-medium text-rose-600']) }}>−{{ qty(abs((float) $quantity)) }} {{ $unit }}</span>
@else
    <span {{ $attributes->merge(['class' => 'font-medium text-emerald-700']) }}>+{{ qty($quantity) }} {{ $unit }}</span>
@endif
