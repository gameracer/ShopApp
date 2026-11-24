@php
$styles = [
    'cash' => 'bg-emerald-100 text-emerald-800',
    'mobile' => 'bg-sky-100 text-sky-800',
    'bank' => 'bg-violet-100 text-violet-800',
    'credit' => 'bg-amber-100 text-amber-800',
    'split' => 'bg-teal-100 text-teal-800',
];
@endphp
<span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$sale->payment_method] ?? 'bg-slate-100' }}">{{ __(ucfirst($sale->payment_method)) }}</span>
@if ($sale->payment_method === 'credit' && $sale->isPaid())
    <span class="ml-1 text-xs text-emerald-700">✓ {{ __('Paid') }}</span>
@endif
