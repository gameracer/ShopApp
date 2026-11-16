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
    <header class="flex items-center justify-between gap-6 bg-slate-950 px-8 py-4">
        <div class="min-w-0">
            <p class="truncate text-2xl font-bold">{{ \App\Support\Shop::name() }}</p>
            @php $contact = array_filter([\App\Models\Setting::get('shop_address'), \App\Models\Setting::get('shop_phone')]); @endphp
            @if ($contact)<p class="truncate text-base text-slate-400">{{ implode(' · ', $contact) }}</p>@endif
        </div>
        <div class="shrink-0 text-right">
            <p class="text-2xl tabular-nums" x-text="clock"></p>
            <p class="text-base text-slate-400" x-text="date"></p>
        </div>
    </header>

    {{-- Idle: welcome --}}
    <main x-show="state === 'idle'" class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
        <span class="flex h-24 w-24 items-center justify-center rounded-3xl bg-emerald-600"><x-icon name="cart" class="h-12 w-12" /></span>
        <h1 class="text-6xl font-bold">{{ __('Welcome') }}</h1>
        <p class="text-2xl text-slate-400">{{ \App\Support\Shop::name() }}</p>
        @if ($phone = \App\Models\Setting::get('shop_phone'))<p class="text-xl text-slate-500">{{ __('Tel') }}: {{ $phone }}</p>@endif
    </main>

    {{-- Selling or just paid: the order on the left, totals and payment on the right --}}
    <main x-cloak x-show="state !== 'idle'" class="grid min-h-0 flex-1 grid-cols-5">
        <section class="col-span-3 flex min-h-0 flex-col border-r border-slate-800">
            <div class="flex items-center justify-between gap-4 border-b border-slate-800 px-8 py-3 text-lg">
                <span class="shrink-0 font-semibold">
                    {{ __('Your order') }}
                    <span x-show="state === 'paid' && paid?.invoice" class="text-slate-400" x-text="'· ' + paid?.invoice"></span>
                </span>
                <span class="truncate text-slate-400">
                    <span x-show="order.customer">{{ __('Customer') }}: <span class="text-white" x-text="order.customer"></span></span>
                    <span x-show="order.customer && order.cashier"> · </span>
                    <span x-show="order.cashier">{{ __('Served by') }}: <span class="text-white" x-text="order.cashier"></span></span>
                </span>
            </div>
            <div class="grid grid-cols-12 gap-4 px-8 py-2 text-sm font-semibold tracking-wide text-slate-400 uppercase">
                <span class="col-span-1">#</span>
                <span class="col-span-5">{{ __('Item') }}</span>
                <span class="col-span-2 text-right">{{ __('Qty') }}</span>
                <span class="col-span-2 text-right">{{ __('Price') }}</span>
                <span class="col-span-2 text-right">{{ __('Amount') }}</span>
            </div>
            <ul x-ref="lines" class="min-h-0 flex-1 divide-y divide-slate-800 overflow-y-auto">
                <template x-for="(line, i) in order.lines" :key="i">
                    <li class="grid grid-cols-12 items-center gap-4 px-8 py-3 text-2xl transition-colors"
                        :class="state === 'cart' && i === cart.last && 'bg-emerald-900/50'"
                        :data-last="state === 'cart' && i === cart.last ? '' : null">
                        <span class="col-span-1 text-lg text-slate-500 tabular-nums" x-text="i + 1"></span>
                        <span class="col-span-5 truncate font-medium" x-text="line.name"></span>
                        <span class="col-span-2 text-right tabular-nums">
                            <span x-text="qty(line.quantity)"></span>
                            <span class="text-base text-slate-400" x-text="line.unit"></span>
                        </span>
                        <span class="col-span-2 text-right text-xl text-slate-300 tabular-nums" x-text="money(line.price)"></span>
                        <span class="col-span-2 text-right font-semibold tabular-nums" x-text="money(line.subtotal)"></span>
                    </li>
                </template>
            </ul>
            <div class="flex justify-between gap-4 border-t border-slate-800 bg-slate-950/40 px-8 py-3 text-xl text-slate-300">
                <span><span x-text="order.lines.length"></span> {{ __('items') }} · <span x-text="qty(order.units ?? 0)"></span> {{ __('pcs / units') }}</span>
                <span>{{ __('Subtotal') }}: <span class="font-semibold text-white tabular-nums" x-text="money(order.total)"></span></span>
            </div>
        </section>

        {{-- Selling --}}
        <section x-show="state === 'cart'" class="col-span-2 flex min-h-0 flex-col gap-6 p-8">
            <div x-show="lastLine" class="rounded-2xl bg-emerald-900/40 p-5 ring-1 ring-emerald-700">
                <p class="text-sm font-semibold tracking-wide text-emerald-300 uppercase">{{ __('Last item') }}</p>
                <p class="mt-1 truncate text-3xl font-bold" x-text="lastLine?.name"></p>
                <p class="mt-1 flex justify-between text-2xl tabular-nums">
                    <span class="text-slate-300" x-text="qty(lastLine?.quantity) + ' × ' + money(lastLine?.price)"></span>
                    <span class="font-semibold" x-text="money(lastLine?.subtotal)"></span>
                </p>
            </div>

            <div class="mt-auto">
                <p class="text-2xl text-slate-400">{{ __('Total to pay') }}</p>
                <p class="text-7xl font-bold tabular-nums text-emerald-400" x-text="money(cart.total)"></p>
            </div>

            <div class="space-y-2 border-t border-slate-800 pt-5 text-2xl">
                <p class="flex justify-between"><span class="text-slate-400">{{ __('Payment') }}</span><span x-text="cart.methodLabel"></span></p>
                <div x-show="cart.method === 'cash' && cart.cashGiven > 0" class="space-y-2">
                    <p class="flex justify-between"><span class="text-slate-400">{{ __('Cash received') }}</span><span class="tabular-nums" x-text="money(cart.cashGiven)"></span></p>
                    <p x-show="cart.cashGiven >= cart.total" class="flex justify-between text-3xl font-bold"><span class="text-slate-400">{{ __('Change') }}</span><span class="tabular-nums text-amber-300" x-text="money(cart.change)"></span></p>
                    <p x-show="cart.cashGiven < cart.total" class="flex justify-between text-3xl font-bold"><span class="text-slate-400">{{ __('Still to pay') }}</span><span class="tabular-nums text-rose-300" x-text="money(cart.total - cart.cashGiven)"></span></p>
                </div>
                <div x-show="cart.method === 'split'" class="space-y-2">
                    <template x-for="p in cart.payments ?? []" :key="p.label">
                        <p class="flex justify-between"><span class="text-slate-400" x-text="p.label"></span><span class="tabular-nums" x-text="money(p.amount)"></span></p>
                    </template>
                    <p x-show="cart.splitChange > 0" class="flex justify-between text-3xl font-bold"><span class="text-slate-400">{{ __('Change') }}</span><span class="tabular-nums text-amber-300" x-text="money(cart.splitChange)"></span></p>
                    <p x-show="cart.balance > 0" class="flex justify-between text-3xl font-bold"><span class="text-slate-400">{{ __('Still to pay') }}</span><span class="tabular-nums text-rose-300" x-text="money(cart.balance)"></span></p>
                </div>
                <div x-show="cart.method === 'credit'" class="space-y-2">
                    <p class="flex justify-between"><span class="text-slate-400">{{ __('Paid now') }}</span><span class="tabular-nums" x-text="money(cart.amountPaid)"></span></p>
                    <p class="flex justify-between text-3xl font-bold"><span class="text-slate-400">{{ __('Balance (credit)') }}</span><span class="tabular-nums text-rose-300" x-text="money(cart.balance)"></span></p>
                </div>
            </div>
        </section>

        {{-- Paid: thank you, with the order still visible on the left --}}
        <section x-show="state === 'paid'" class="col-span-2 flex flex-col justify-center gap-8 p-8">
            <div class="text-center">
                <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-600 text-5xl">✓</span>
                <h1 class="mt-4 text-5xl font-bold">{{ __('Thank you!') }}</h1>
            </div>
            <div class="space-y-2 text-2xl">
                <p x-show="paid?.invoice" class="flex justify-between"><span class="text-slate-400">{{ __('Invoice') }}</span><span x-text="paid?.invoice"></span></p>
                <p x-show="paid?.methodLabel" class="flex justify-between"><span class="text-slate-400">{{ __('Payment') }}</span><span x-text="paid?.methodLabel"></span></p>
                <p class="flex justify-between text-3xl font-bold"><span class="text-slate-400">{{ __('Total') }}</span><span class="tabular-nums text-emerald-400" x-text="money(paid?.total)"></span></p>
                <template x-for="p in paid?.payments ?? []" :key="p.label">
                    <p class="flex justify-between"><span class="text-slate-400" x-text="p.label"></span><span class="tabular-nums" x-text="money(p.amount)"></span></p>
                </template>
                <p class="flex justify-between"><span class="text-slate-400">{{ __('Paid') }}</span><span class="tabular-nums" x-text="money(paid?.paid)"></span></p>
                <p x-show="paid?.change > 0" class="flex justify-between text-3xl font-bold"><span class="text-slate-400">{{ __('Change') }}</span><span class="tabular-nums text-amber-300" x-text="money(paid?.change)"></span></p>
                <p x-show="paid?.balance > 0" class="flex justify-between text-3xl font-bold"><span class="text-slate-400">{{ __('Balance (credit)') }}</span><span class="tabular-nums text-rose-300" x-text="money(paid?.balance)"></span></p>
            </div>
            @if ($footer = \App\Models\Setting::get('receipt_footer'))<p class="text-center text-xl text-slate-400">{{ $footer }}</p>@endif
        </section>
    </main>
</div>
</body>
</html>
