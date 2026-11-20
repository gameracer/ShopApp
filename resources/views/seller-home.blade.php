@extends('layouts.app')
@section('title', __('Home'))
@section('panel', '1')

{{-- Seller home: one big card per job, sized for a finger on a touch screen. --}}
@section('content')
@php
    $cards = [
        ['route' => 'credit.index', 'icon' => 'credit', 'label' => __('Credit customers'), 'hint' => money($stats['credit_outstanding']).' '.__('owed'),
         'tone' => 'bg-amber-500 hover:bg-amber-600', 'badge' => $debtors ? trans_choice(':count customer|:count customers', $debtors) : null],
        ['route' => 'sales.index', 'icon' => 'receipt', 'label' => __('My sales'), 'hint' => __('Receipts and reprints'),
         'tone' => 'bg-violet-600 hover:bg-violet-700', 'badge' => null],
        ['route' => 'customers.index', 'icon' => 'user', 'label' => __('Customers'), 'hint' => __('Add or find a customer'),
         'tone' => 'bg-teal-600 hover:bg-teal-700', 'badge' => null],
        ['route' => 'products.index', 'icon' => 'box', 'label' => __('Products'), 'hint' => __('Check prices and stock'),
         'tone' => 'bg-slate-700 hover:bg-slate-800', 'badge' => $lowStock ? __(':count low on stock', ['count' => $lowStock]) : null],
        ['route' => 'alerts', 'icon' => 'bell', 'label' => __('Alerts'), 'hint' => __('Expiry, low stock, overdue credit'),
         'tone' => 'bg-rose-600 hover:bg-rose-700', 'badge' => ($alertCount ?? 0) > 0 ? $alertCount : null, 'wide' => true],
    ];
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-3 lg:min-h-[calc(100dvh-5rem)]">
    <div class="flex flex-wrap items-end justify-between gap-2 px-1 pt-1">
        <div>
            <h1 class="text-xl font-bold text-slate-900">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
            <p class="text-sm text-slate-500">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs text-slate-500">{{ __('My sales today') }}</p>
            <p class="text-2xl font-bold text-slate-900">{{ money($stats['today_sales']) }}</p>
            <p class="text-xs text-slate-500">{{ trans_choice(':count sale|:count sales', $stats['today_count']) }}</p>
        </div>
    </div>

    <div class="grid flex-1 grid-cols-2 gap-3 lg:grid-cols-5 lg:grid-rows-2">
        <a href="{{ route('sales.create') }}"
           class="home-card col-span-2 min-h-44 bg-emerald-600 hover:bg-emerald-700 lg:row-span-2">
            <x-icon name="cart" class="h-20 w-20 lg:h-28 lg:w-28" />
            <span class="text-3xl font-bold lg:text-5xl">{{ __('Sell') }}</span>
            <span class="text-sm text-white/80 lg:text-base">{{ __('Tap products or scan barcodes') }}</span>
        </a>
        @foreach ($cards as $card)
            <a href="{{ route($card['route']) }}" @class(['home-card', $card['tone'], 'pt-9' => $card['badge'], 'col-span-2' => $card['wide'] ?? false])>
                @if ($card['badge'])
                    <span class="absolute top-2 right-2 rounded-full bg-white/95 px-2 py-0.5 text-xs font-bold text-slate-800">{{ $card['badge'] }}</span>
                @endif
                <x-icon :name="$card['icon']" class="h-10 w-10 lg:h-12 lg:w-12" />
                <span class="text-lg leading-tight font-bold lg:text-xl">{{ $card['label'] }}</span>
                <span class="text-xs text-white/80 lg:text-sm">{{ $card['hint'] }}</span>
            </a>
        @endforeach
    </div>
</div>
@endsection
