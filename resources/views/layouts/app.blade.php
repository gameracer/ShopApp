<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Barcode scanner / receipt printer settings for resources/js/devices.js --}}
    <meta name="shop-devices" content="{{ json_encode([
        'lookupUrl' => route('products.barcode'),
        'beep' => \App\Models\Setting::get('scanner_beep') === '1',
        'i18n' => [
            'preview' => __('Receipt preview'), 'print' => __('Print'), 'close' => __('Close'),
            'printing' => __('Printing…'), 'failed' => __('Printing failed'),
        ],
    ]) }}">
    <title>@yield('title', __('Dashboard')) · {{ $shopName ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased" x-data="{ menu: false }">
@php
    $user = auth()->user();
    $nav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home', 'label' => __('Dashboard')],
        ['route' => 'sales.create', 'match' => 'sales.create', 'icon' => 'cart', 'label' => __('New sale')],
        ['route' => 'sales.index', 'match' => 'sales.index|sales.show', 'icon' => 'receipt', 'label' => __('Sales')],
        ['route' => 'products.index', 'match' => 'products.*', 'icon' => 'box', 'label' => __('Products')],
        ['route' => 'stock.create', 'match' => 'stock.*', 'icon' => 'truck', 'label' => __('Add stock')],
        ['route' => 'credit.index', 'match' => 'credit.*', 'icon' => 'credit', 'label' => __('Credit buyers')],
        ['route' => 'customers.index', 'match' => 'customers.*', 'icon' => 'user', 'label' => __('Customers')],
    ];
    if ($user->isOwner()) {
        $nav = array_merge($nav, [
            ['route' => 'expenses.index', 'match' => 'expenses.*', 'icon' => 'wallet', 'label' => __('Expenses')],
            ['route' => 'reports.index', 'match' => 'reports.*', 'icon' => 'chart', 'label' => __('Reports')],
            ['route' => 'tags.index', 'match' => 'tags.*', 'icon' => 'tag', 'label' => __('Tags')],
            ['route' => 'users.index', 'match' => 'users.*', 'icon' => 'users', 'label' => __('Users')],
            ['route' => 'settings.edit', 'match' => 'settings.*', 'icon' => 'cog', 'label' => __('Settings')],
        ]);
    }
    $nav[] = ['route' => 'help', 'match' => 'help', 'icon' => 'help', 'label' => __('Help')];
@endphp

@if ($user->isSeller())
{{-- Sellers: a till-style panel with a slim top bar and only the screens they need. --}}
@php
    $sellerNav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'home', 'label' => __('Home')],
        ['route' => 'sales.create', 'match' => 'sales.create', 'icon' => 'cart', 'label' => __('Sell')],
        ['route' => 'sales.index', 'match' => 'sales.index|sales.show', 'icon' => 'receipt', 'label' => __('My sales')],
        ['route' => 'credit.index', 'match' => 'credit.*|customers.*', 'icon' => 'credit', 'label' => __('Credit buyers')],
        ['route' => 'stock.create', 'match' => 'stock.*', 'icon' => 'truck', 'label' => __('Add stock')],
    ];
@endphp
<div class="flex min-h-screen flex-col">
    <header class="no-print sticky top-0 z-20 flex items-center gap-2 bg-slate-900 px-2 py-1.5 text-white sm:px-3">
        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2 pr-1">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600"><x-icon name="cart" /></span>
            <span class="hidden max-w-40 truncate font-semibold lg:block">{{ $shopName }}</span>
        </a>
        <nav class="flex min-w-0 flex-1 gap-1 overflow-x-auto">
            @foreach ($sellerNav as $item)
                <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                   @class(['panel-tab', 'active' => request()->routeIs(...explode('|', $item['match']))])>
                    <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" /> <span class="hidden sm:inline">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <a href="{{ route('alerts') }}" class="relative rounded-lg p-2 text-slate-300 hover:bg-slate-800 hover:text-white" title="{{ __('Alerts') }}">
            <x-icon name="bell" />
            @if (($alertCount ?? 0) > 0)
                <span class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">{{ $alertCount > 99 ? '99+' : $alertCount }}</span>
            @endif
        </a>
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-2 rounded-lg px-1.5 py-1 hover:bg-slate-800">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="hidden max-w-28 truncate text-sm md:block">{{ $user->name }}</span>
            </button>
            <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-56 rounded-lg border border-slate-200 bg-white py-1 text-slate-800 shadow-lg">
                <a href="{{ route('products.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Products') }}</a>
                <a href="{{ route('customers.index') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Customers') }}</a>
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('My profile') }}</a>
                <a href="{{ route('help') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Help') }}</a>
                <div class="px-4 py-2"><x-lang-switch /></div>
                <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                    @csrf
                    <button class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-rose-600 hover:bg-slate-50"><x-icon name="logout" class="h-4 w-4" /> {{ __('Log out') }}</button>
                </form>
                <div class="border-t border-slate-100 px-4 pt-2 pb-1 text-xs text-slate-400">
                    <x-app-version class="block" />
                    <x-credit class="block" />
                </div>
            </div>
        </div>
    </header>

    <main @class(['w-full flex-1', 'p-2 sm:p-3' => View::hasSection('panel'), 'mx-auto max-w-7xl p-4 sm:p-6' => ! View::hasSection('panel')])>
        @include('layouts._flash')
        @yield('content')
    </main>
</div>
@else
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <div x-cloak x-show="menu" x-transition.opacity class="no-print fixed inset-0 z-30 bg-slate-900/50 lg:hidden" @click="menu = false"></div>
    <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
           class="no-print fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-slate-900 transition-transform lg:static lg:translate-x-0">
        <div class="flex items-center justify-between px-4 py-4">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-white">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600"><x-icon name="cart" /></span>
                <span class="truncate font-semibold">{{ $shopName }}</span>
            </a>
            <button class="text-slate-400 lg:hidden" @click="menu = false"><x-icon name="x" /></button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}"
                   @class(['nav-link', 'active' => request()->routeIs(...explode('|', $item['match']))])>
                    <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
        <div class="space-y-2 border-t border-slate-800 p-4 text-xs text-slate-400">
            <div class="flex items-center justify-between gap-2">
                <x-lang-switch :dark="true" />
                <x-app-version class="text-slate-500" />
            </div>
            <x-credit class="block text-slate-500" />
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="no-print sticky top-0 z-20 flex items-center gap-3 border-b border-slate-200 bg-white/90 px-4 py-2.5 backdrop-blur">
            <button class="text-slate-600 lg:hidden" @click="menu = true"><x-icon name="menu" class="h-6 w-6" /></button>
            <form action="{{ route('products.index') }}" class="hidden max-w-sm flex-1 sm:block">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
                    <input name="q" class="input pl-9" placeholder="{{ __('Search products, SKU or #tag…') }}" value="{{ request()->routeIs('products.index') ? request('q') : '' }}">
                </div>
            </form>
            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('alerts') }}" class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100" title="{{ __('Alerts') }}">
                    <x-icon name="bell" />
                    @if (($alertCount ?? 0) > 0)
                        <span class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">{{ $alertCount > 99 ? '99+' : $alertCount }}</span>
                    @endif
                </a>
                <x-lang-switch class="hidden sm:inline-flex" />
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="hidden text-left text-sm sm:block">
                            <span class="block leading-tight font-medium">{{ $user->name }}</span>
                            <span class="block text-xs leading-tight text-slate-500">{{ __(ucfirst($user->role)) }}</span>
                        </span>
                    </button>
                    <div x-cloak x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('My profile') }}</a>
                        <a href="{{ route('help') }}" class="block px-4 py-2 text-sm hover:bg-slate-50">{{ __('Help') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-rose-600 hover:bg-slate-50"><x-icon name="logout" class="h-4 w-4" /> {{ __('Log out') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main @class(['w-full flex-1', 'p-2 sm:p-3' => View::hasSection('panel'), 'mx-auto max-w-7xl p-4 sm:p-6' => ! View::hasSection('panel')])>
            @include('layouts._flash')
            @yield('content')
        </main>
    </div>
</div>
@endif
</body>
</html>
