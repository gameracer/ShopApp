@extends('layouts.app')
@section('title', __('New sale'))
{{-- Full-width till: product tiles on the left, cart and payment on the right. --}}
@section('panel', '1')

@section('content')
@php $isOwner = auth()->user()->isOwner(); @endphp
<div x-data="posApp(@js([
        'searchUrl' => route('products.search'),
        'storeUrl' => route('sales.store'),
        'currency' => \App\Support\Shop::currency(),
        'canEditPrice' => $isOwner,
        // For the customer display.
        'cashier' => auth()->user()->name,
        'customers' => $customers->pluck('name', 'id'),
        'methods' => collect(\App\Models\Sale::METHODS)->mapWithKeys(fn ($m) => [$m => __(ucfirst($m))]),
        'today' => $today,
        'i18n' => [
            'failed' => __('Something went wrong. Please try again.'),
            'notFound' => __('No product has the barcode :code.'),
            'outOfStock' => __(':name is out of stock.'),
            'noMoreStock' => __('No more :name in stock.'),
            'expired' => __(':name has expired and cannot be sold.'),
            'printing' => __('Printing…'),
            'printed' => __('Printed'),
            'printFailed' => __('Printing failed'),
            'cashShort' => __('Cash received is less than the total.'),
        ],
    ]))" x-effect="broadcast()"
     @keydown.window.f2.prevent="$refs.search.focus()"
     @keydown.window.f9.prevent="done ? newSale() : checkout()"
     class="grid gap-3 lg:h-[calc(100dvh-5rem)] lg:grid-cols-[minmax(0,1fr)_25rem] xl:grid-cols-[minmax(0,1fr)_29rem]">

    {{-- Products --}}
    <section class="card flex min-h-0 flex-col" @barcode.window="onScan($event)">
        <div class="space-y-2 border-b border-slate-100 p-3">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input x-ref="search" x-model="query" @input="onQuery" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="enterPressed()"
                           class="input py-2.5 pl-10 text-base" placeholder="{{ __('Search or scan a barcode…') }} (F2)" autofocus autocomplete="off">
                </div>
                {{-- The desktop app opens the customer display on the second screen by itself. --}}
                <button type="button" x-data x-show="!window.ShopDevices?.desktop" class="btn-secondary shrink-0"
                        onclick="window.open('{{ route('display') }}', 'shopapp-display', 'popup,width=1024,height=768')"
                        title="{{ __('Open the customer display, drag it to the second screen and press F11') }}">
                    <x-icon name="user" class="h-4 w-4" /> <span class="hidden xl:inline">{{ __('Customer display') }}</span>
                </button>
            </div>
            @if ($tags->isNotEmpty())
                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1">
                    <button type="button" @click="pickTag(null)" class="pos-tag" :class="!selectedTags.length && 'active'">{{ __('All') }}</button>
                    @foreach ($tags as $tag)
                        <button type="button" @click="pickTag({{ $tag->id }})" class="pos-tag" :class="selectedTags.includes({{ $tag->id }}) && 'active'">{{ $tag->name }}</button>
                    @endforeach
                </div>
            @endif
            <div x-cloak x-show="scanNotice" x-transition.opacity
                 :class="scanNotice?.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700'"
                 class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm" role="status">
                <span><span x-text="scanNotice?.ok ? '✓' : '✕'"></span> <span x-text="scanNotice?.text"></span></span>
                @if ($isOwner)
                    <a x-show="scanNotice?.unknownCode" :href="'{{ route('products.create') }}?sku=' + encodeURIComponent(scanNotice?.unknownCode ?? '')"
                       class="font-medium underline">{{ __('Add product with this barcode') }}</a>
                @endif
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto bg-slate-50 p-3 max-lg:max-h-[50vh]">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                <template x-for="(p, i) in results" :key="p.id">
                    <button type="button" @click="add(p)" class="pos-tile" :class="i === highlighted && 'ring-2 ring-emerald-500'">
                        <span x-show="inCart(p)" x-text="qty(inCart(p))"
                              class="absolute -top-1.5 -right-1.5 flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-xs font-bold text-white"></span>
                        <span class="line-clamp-2 text-sm leading-snug font-semibold text-slate-900" x-text="p.name"></span>
                        <span class="mt-2 flex items-end justify-between gap-1">
                            <span class="text-base font-bold text-emerald-700" x-text="money(p.selling_price)"></span>
                            <span class="text-xs text-slate-500" x-text="qty(p.quantity) + ' ' + p.unit"></span>
                        </span>
                    </button>
                </template>
            </div>
            <div x-show="!loading && results.length === 0" class="space-y-2 py-12 text-center text-sm text-slate-500">
                <p>{{ __('No products found.') }}</p>
                @if ($isOwner)
                    <a href="{{ route('products.create') }}" class="btn-secondary inline-flex"><x-icon name="plus" class="h-4 w-4" /> {{ __('New product') }}</a>
                @else
                    <p class="text-xs">{{ __('Ask the shop owner to add products first.') }}</p>
                @endif
            </div>
            <p x-show="loading && !results.length" class="py-6 text-center text-xs text-slate-400">{{ __('Searching…') }}</p>
        </div>
    </section>

    {{-- Cart and payment --}}
    <section class="card flex min-h-0 flex-col">
        <div class="card-header py-2">
            <h2 class="card-title">{{ __('Cart') }} (<span x-text="cart.length"></span>)</h2>
            <span class="text-xs text-slate-500">{{ __('Today') }}: <span x-text="today.count"></span> · <span class="font-medium" x-text="money(today.total)"></span></span>
            <button type="button" x-show="cart.length" @click="clearCart()" class="text-xs font-medium text-rose-600 hover:underline">{{ __('Clear') }}</button>
        </div>

        <ul class="min-h-24 flex-1 divide-y divide-slate-100 overflow-y-auto">
            <template x-for="(line, i) in cart" :key="line.product_id">
                <li class="px-3 py-2" :class="line.product_id === lastId && 'bg-emerald-50/60'">
                    <div class="flex items-start justify-between gap-2">
                        <span class="min-w-0 text-sm leading-tight font-medium" x-text="line.name"></span>
                        <span class="shrink-0 text-sm font-semibold" x-text="money(line.unit_price * line.quantity)"></span>
                    </div>
                    <div class="mt-1 flex items-center gap-2">
                        <div class="flex items-center gap-1">
                            <button type="button" class="btn-secondary btn-sm" @click="line.quantity > 1 ? line.quantity-- : remove(i)">−</button>
                            <input type="number" inputmode="decimal" step="0.01" min="0.01" :max="line.stock" x-model.number="line.quantity" @change="clampQty(line)" class="input w-16 px-1 py-1 text-center">
                            <button type="button" class="btn-secondary btn-sm" @click="line.quantity < line.stock && line.quantity++">+</button>
                        </div>
                        <span class="text-xs text-slate-500">×
                            @if ($isOwner)
                                <input type="number" inputmode="decimal" step="0.01" min="0" x-model.number="line.unit_price" class="input inline-block w-24 px-1 py-1 text-right">
                            @else
                                <span x-text="money(line.unit_price)"></span>
                            @endif
                        </span>
                        <button type="button" @click="remove(i)" class="ml-auto p-1 text-slate-400 hover:text-rose-600" title="{{ __('Remove') }}"><x-icon name="trash" class="h-4 w-4" /></button>
                    </div>
                </li>
            </template>
            <li x-show="!cart.length" class="px-4 py-10 text-center text-sm text-slate-500">
                <x-icon name="cart" class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                {{ __('Tap a product or scan a barcode.') }}
            </li>
        </ul>

        <div class="space-y-3 border-t border-slate-200 bg-slate-50/70 p-3">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-medium text-slate-600">{{ __('Total') }} <span class="text-xs text-slate-400" x-show="cart.length">· <span x-text="qty(units)"></span> {{ __('items') }}</span></span>
                <span class="text-3xl font-bold text-slate-900" x-text="money(total)"></span>
            </div>

            <div x-show="cart.length" class="space-y-3">
                <div class="grid grid-cols-5 gap-1.5">
                    @foreach (\App\Models\Sale::METHODS as $method)
                        <label class="min-w-0 cursor-pointer">
                            <input type="radio" value="{{ $method }}" x-model="paymentMethod" class="peer hidden">
                            <span class="pos-chip">{{ __(ucfirst($method)) }}</span>
                        </label>
                    @endforeach
                </div>

                {{-- Cash: one tap on an amount, change shown at once. --}}
                <div x-show="paymentMethod === 'cash'" class="space-y-2">
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="(amount, i) in cashSuggestions" :key="amount">
                            <button type="button" @click="cashGiven = amount" class="btn-secondary btn-sm flex-1"
                                    :class="Number(cashGiven) === amount && 'border-emerald-600 bg-emerald-50 text-emerald-800'"
                                    x-text="i === 0 ? @js(__('Exact')) : money(amount)"></button>
                        </template>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" inputmode="decimal" step="0.01" min="0" x-model="cashGiven" class="input" placeholder="{{ __('Cash received') }}">
                        <div class="flex items-center justify-between rounded-lg px-3 py-2" :class="change > 0 ? 'bg-emerald-100 text-emerald-900' : 'bg-white text-slate-500'">
                            <span class="text-xs">{{ __('Change') }}</span>
                            <span class="text-lg font-bold" x-text="money(change)"></span>
                        </div>
                    </div>
                    <p x-show="cashShort > 0" class="text-xs text-rose-700">{{ __('Cash received is less than the total.') }}</p>
                </div>

                {{-- Split: part cash, part mobile money, part bank. Anything left becomes credit. --}}
                <div x-show="isSplit" class="space-y-2">
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (\App\Models\Sale::SPLIT_METHODS as $method)
                            <div>
                                <label class="mb-0.5 block text-xs font-medium text-slate-600">{{ __(ucfirst($method)) }}</label>
                                <input type="number" inputmode="decimal" step="0.01" min="0" x-model="split.{{ $method }}" class="input px-2" placeholder="0">
                                <button type="button" x-show="splitRemaining > 0" @click="fillSplit('{{ $method }}')" class="mt-1 w-full text-xs font-medium text-emerald-700 hover:underline">{{ __('Rest') }}</button>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-x-4 text-sm">
                        <p>{{ __('Paid') }}: <strong x-text="money(splitPaid)"></strong></p>
                        <p x-show="splitRemaining > 0" class="text-rose-700">{{ __('Remaining (credit)') }}: <strong x-text="money(splitRemaining)"></strong></p>
                        <p x-show="splitChange > 0" class="text-emerald-700">{{ __('Change') }}: <strong x-text="money(splitChange)"></strong></p>
                    </div>
                </div>

                <div x-show="isCredit" class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-0.5 block text-xs font-medium text-slate-600">{{ __('Amount paid now') }}</label>
                        <input type="number" inputmode="decimal" step="0.01" min="0" x-model="amountPaid" class="input" placeholder="0">
                    </div>
                    <div>
                        <span class="mb-0.5 block text-xs font-medium text-slate-600">{{ __('Balance (credit)') }}</span>
                        <div class="rounded-lg bg-white px-3 py-2 font-semibold text-rose-700" x-text="money(balanceDue)"></div>
                    </div>
                </div>

                {{-- Customer: hidden behind a link unless part of the bill is left unpaid. --}}
                <button type="button" x-show="!owes && !showCustomer" @click="showCustomer = true" class="text-sm font-medium text-emerald-700 hover:underline">
                    + {{ __('Customer') }} <span class="font-normal text-slate-500">({{ __('Optional') }})</span>
                </button>
                <div x-show="owes || showCustomer" class="space-y-2 rounded-lg border p-2" :class="owes ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white'">
                    <p class="text-xs font-medium text-slate-700">{{ __('Customer') }} <span x-show="owes" class="text-rose-600">* {{ __('Needed so the unpaid balance can be followed up.') }}</span></p>
                    <select x-model="customerId" class="input">
                        <option value="">— {{ __('Walk-in / new customer') }} —</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                        @endforeach
                    </select>
                    <div x-show="!customerId" class="grid grid-cols-2 gap-2">
                        <input x-model="newCustomerName" class="input" placeholder="{{ __('New customer name') }}">
                        <input x-model="newCustomerPhone" class="input" placeholder="{{ __('Phone number') }}">
                    </div>
                    <div x-show="owes" class="flex items-center gap-2">
                        <label class="shrink-0 text-xs font-medium text-slate-600">{{ __('Due date') }}</label>
                        <input type="date" x-model="dueDate" class="input" min="{{ today()->toDateString() }}">
                    </div>
                </div>
            </div>

            <ul x-show="errorList.length" class="rounded-lg bg-rose-50 p-2 text-sm text-rose-700">
                <template x-for="e in errorList"><li x-text="e"></li></template>
            </ul>

            <button type="button" @click="checkout()" :disabled="submitting || !cart.length"
                    class="btn-primary w-full py-4 text-lg disabled:opacity-50">
                <x-icon name="cart" /> <span x-text="submitting ? @js(__('Saving…')) : @js(__('Pay'))"></span> <span x-show="cart.length" x-text="money(total)"></span>
                <span class="ml-1 hidden text-xs font-normal opacity-70 lg:inline">F9</span>
            </button>
        </div>
    </section>

    {{-- Sale done: the till stays open for the next customer. --}}
    <div x-cloak x-show="done" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
         @keydown.escape.window="done && newSale()">
        <div class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 text-center shadow-xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-700">✓</div>
            <div>
                <h2 class="text-xl font-bold">{{ __('Sale completed.') }}</h2>
                <p class="text-sm text-slate-500"><span x-text="done?.invoice"></span> · <span x-text="done?.methodLabel"></span> · <span x-text="money(done?.total)"></span></p>
            </div>
            <div x-show="done?.change > 0" class="rounded-xl bg-emerald-50 p-4">
                <p class="text-sm text-emerald-800">{{ __('Change') }}</p>
                <p class="text-4xl font-bold text-emerald-700" x-text="money(done?.change)"></p>
            </div>
            <div x-show="done?.balance > 0" class="rounded-xl bg-amber-50 p-3 text-amber-900">
                {{ __('Balance (credit)') }}: <strong x-text="money(done?.balance)"></strong>
            </div>
            <p x-show="printStatus" x-text="printStatus?.text" :class="printStatus?.ok ? 'text-emerald-700' : 'text-rose-700'" class="text-sm"></p>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" @click="printDone()" class="btn-secondary py-3"><x-icon name="print" class="h-4 w-4" /> {{ __('Print receipt') }}</button>
                <button type="button" x-ref="next" @click="newSale()" class="btn-primary py-3">{{ __('New sale') }} ↵</button>
            </div>
            <a :href="done?.showUrl" class="block text-xs text-slate-500 hover:underline">{{ __('View sale') }}</a>
        </div>
    </div>
</div>
@endsection
