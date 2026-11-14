{{--
    Customer-facing display for a second screen. Public on purpose: it holds no data itself,
    it only shows what the till broadcasts (resources/js/display.js), so it keeps working
    while the cashier is logged out or switching users.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Customer display') }} · {{ \App\Support\Shop::name() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden bg-slate-900 font-sans text-white antialiased select-none"
      x-data="customerDisplay(@js(['currency' => \App\Support\Shop::currency()]))">

<div class="flex h-full flex-col">
    <header class="flex items-center justify-between bg-slate-950 px-8 py-4">
        <span class="text-2xl font-bold">{{ \App\Support\Shop::name() }}</span>
        <span class="text-2xl tabular-nums text-slate-400" x-text="clock"></span>
    </header>

    {{-- Idle: welcome --}}
    <main x-show="state === 'idle'" class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
        <span class="flex h-24 w-24 items-center justify-center rounded-3xl bg-emerald-600"><x-icon name="cart" class="h-12 w-12" /></span>
        <h1 class="text-6xl font-bold">{{ __('Welcome') }}</h1>
        <p class="text-2xl text-slate-400">{{ \App\Support\Shop::name() }}</p>
        @if ($phone = \App\Models\Setting::get('shop_phone'))<p class="text-xl text-slate-500">{{ __('Tel') }}: {{ $phone }}</p>@endif
    </main>

    {{-- Selling: items and total --}}
    <main x-cloak x-show="state === 'cart'" class="grid min-h-0 flex-1 grid-cols-5">
        <section class="col-span-3 flex min-h-0 flex-col border-r border-slate-800">
            <div class="grid grid-cols-12 gap-4 px-8 py-3 text-sm font-semibold tracking-wide text-slate-400 uppercase">
                <span class="col-span-6">{{ __('Item') }}</span>
                <span class="col-span-3 text-right">{{ __('Qty') }}</span>
                <span class="col-span-3 text-right">{{ __('Subtotal') }}</span>
            </div>
            <ul x-ref="lines" class="min-h-0 flex-1 divide-y divide-slate-800 overflow-y-auto">
                <template x-for="(line, i) in cart.lines" :key="i">
                    <li class="grid grid-cols-12 items-center gap-4 px-8 py-4 text-2xl"
                        :class="i === cart.lines.length - 1 && 'bg-emerald-900/40'">
                        <span class="col-span-6">
                            <span class="block truncate font-medium" x-text="line.name"></span>
                            <span class="block text-base text-slate-400" x-text="money(line.price) + ' / ' + line.unit"></span>
                        </span>
                        <span class="col-span-3 text-right tabular-nums" x-text="qty(line.quantity)"></span>
                        <span class="col-span-3 text-right font-semibold tabular-nums" x-text="money(line.subtotal)"></span>
                    </li>
                </template>
            </ul>
        </section>
        <section class="col-span-2 flex flex-col justify-center gap-8 px-10">
            <div>
                <p class="text-2xl text-slate-400">{{ __('Total') }}</p>
                <p class="text-6xl font-bold tabular-nums text-emerald-400" x-text="money(cart.total)"></p>
                <p class="mt-2 text-lg text-slate-400"><span x-text="cart.lines.length"></span> {{ __('items') }}</p>
            </div>
            <div x-show="cart.method === 'cash' && Number(cart.cashGiven) > 0" class="space-y-2 text-3xl">
                <p class="flex justify-between"><span class="text-slate-400">{{ __('Cash received') }}</span><span class="tabular-nums" x-text="money(cart.cashGiven)"></span></p>
                <p class="flex justify-between font-bold"><span class="text-slate-400">{{ __('Change') }}</span><span class="tabular-nums text-amber-300" x-text="money(cart.change)"></span></p>
            </div>
        </section>
    </main>

    {{-- Paid: thank you --}}
    <main x-cloak x-show="state === 'paid'" class="flex flex-1 flex-col items-center justify-center gap-8 text-center">
        <span class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-600 text-6xl">✓</span>
        <h1 class="text-6xl font-bold">{{ __('Thank you!') }}</h1>
        <div class="grid grid-cols-2 gap-x-10 gap-y-3 text-left text-3xl">
            <span class="text-slate-400">{{ __('Total') }}</span><span class="text-right font-semibold tabular-nums" x-text="money(paid?.total)"></span>
            <span class="text-slate-400">{{ __('Paid') }}</span><span class="text-right tabular-nums" x-text="money(paid?.paid)"></span>
            <template x-if="paid?.change > 0">
                <span class="text-slate-400">{{ __('Change') }}</span>
            </template>
            <template x-if="paid?.change > 0">
                <span class="text-right font-bold tabular-nums text-amber-300" x-text="money(paid?.change)"></span>
            </template>
            <template x-if="paid?.balance > 0">
                <span class="text-slate-400">{{ __('Balance') }}</span>
            </template>
            <template x-if="paid?.balance > 0">
                <span class="text-right font-bold tabular-nums text-rose-300" x-text="money(paid?.balance)"></span>
            </template>
        </div>
        @if ($footer = \App\Models\Setting::get('receipt_footer'))<p class="text-xl text-slate-400">{{ $footer }}</p>@endif
    </main>
</div>
</body>
</html>
