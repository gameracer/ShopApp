@extends('layouts.app')
@section('title', __('New sale'))

@section('content')
@php $isOwner = auth()->user()->isOwner(); @endphp
<x-page-header :title="__('New sale')" :subtitle="__('Search and click items to add them to the cart. Use ↑ ↓ and Enter for quick selection.')" />

<div x-data="posApp(@js([
        'searchUrl' => route('products.search'),
        'storeUrl' => route('sales.store'),
        'currency' => \App\Support\Shop::currency(),
        'canEditPrice' => $isOwner,
        'i18n' => [
            'failed' => __('Something went wrong. Please try again.'),
            'notFound' => __('No product has the barcode :code.'),
            'outOfStock' => __(':name is out of stock.'),
            'noMoreStock' => __('No more :name in stock.'),
            'expired' => __(':name has expired and cannot be sold.'),
        ],
    ]))" class="grid gap-6 lg:grid-cols-5">
    <div class="lg:col-span-2">
        <x-product-picker :tags="$tags" />
    </div>

    <div class="space-y-4 lg:col-span-3">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">{{ __('Cart') }} (<span x-text="cart.length"></span>)</h2>
                <button type="button" x-show="cart.length" @click="cart = []" class="text-xs text-rose-600 hover:underline">{{ __('Clear') }}</button>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>{{ __('Item') }}</th><th class="w-28">{{ __('Qty') }}</th><th class="w-32 text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Subtotal') }}</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    <template x-for="(line, i) in cart" :key="line.product_id">
                        <tr>
                            <td>
                                <span class="font-medium" x-text="line.name"></span>
                                <span class="block text-xs text-slate-500">{{ __('Available') }}: <span x-text="line.stock + ' ' + line.unit"></span></span>
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="btn-secondary btn-sm" @click="line.quantity = Math.max(1, line.quantity - 1)">−</button>
                                    <input type="number" step="0.01" min="0.01" :max="line.stock" x-model.number="line.quantity" @change="clampQty(line)" class="input w-16 px-1 text-center">
                                    <button type="button" class="btn-secondary btn-sm" @click="line.quantity < line.stock && line.quantity++">+</button>
                                </div>
                            </td>
                            <td class="text-right">
                                @if ($isOwner)
                                    <input type="number" step="0.01" min="0" x-model.number="line.unit_price" class="input w-28 text-right">
                                @else
                                    <span x-text="money(line.unit_price)"></span>
                                @endif
                            </td>
                            <td class="text-right font-medium" x-text="money(line.unit_price * line.quantity)"></td>
                            <td><button type="button" @click="remove(i)" class="text-slate-400 hover:text-rose-600"><x-icon name="trash" class="h-4 w-4" /></button></td>
                        </tr>
                    </template>
                    <tr x-show="!cart.length"><td colspan="5" class="py-10 text-center text-slate-500">{{ __('Cart is empty.') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                <span class="text-sm font-medium text-slate-600">{{ __('Total') }}</span>
                <span class="text-2xl font-bold text-slate-900" x-text="money(total)"></span>
            </div>
        </div>

        <div class="card space-y-4 p-4" x-show="cart.length">
            <div>
                <span class="label">{{ __('Payment method') }}</span>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (\App\Models\Sale::METHODS as $method)
                        <label class="cursor-pointer">
                            <input type="radio" value="{{ $method }}" x-model="paymentMethod" class="peer hidden">
                            <span class="block rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-medium peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800">{{ __(ucfirst($method)) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div x-show="paymentMethod === 'cash'" class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">{{ __('Cash received') }}</label>
                    <input type="number" step="0.01" min="0" x-model="cashGiven" class="input">
                </div>
                <div>
                    <span class="label">{{ __('Change') }}</span>
                    <div class="rounded-lg bg-slate-50 px-3 py-2 text-lg font-semibold" x-text="money(change)"></div>
                </div>
            </div>

            <div class="space-y-3 rounded-lg border border-slate-200 p-3" :class="isCredit && 'border-amber-300 bg-amber-50/50'">
                <p class="text-sm font-medium text-slate-700">{{ __('Customer') }} <span x-show="isCredit" class="text-rose-600">*</span> <span x-show="!isCredit" class="text-xs font-normal text-slate-500">({{ __('Optional') }})</span></p>
                <select x-model="customerId" class="input">
                    <option value="">— {{ __('Walk-in / new customer') }} —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                    @endforeach
                </select>
                <div x-show="!customerId" class="grid gap-2 sm:grid-cols-2">
                    <input x-model="newCustomerName" class="input" placeholder="{{ __('New customer name') }}">
                    <input x-model="newCustomerPhone" class="input" placeholder="{{ __('Phone number') }}">
                </div>
                <div x-show="isCredit" class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label">{{ __('Amount paid now') }}</label>
                        <input type="number" step="0.01" min="0" x-model="amountPaid" class="input" placeholder="0.00">
                    </div>
                    <div>
                        <label class="label">{{ __('Due date') }}</label>
                        <input type="date" x-model="dueDate" class="input" min="{{ today()->toDateString() }}">
                    </div>
                    <div>
                        <span class="label">{{ __('Balance (credit)') }}</span>
                        <div class="rounded-lg bg-white px-3 py-2 font-semibold text-rose-700" x-text="money(balanceDue)"></div>
                    </div>
                </div>
            </div>

            <ul x-show="errorList.length" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">
                <template x-for="e in errorList"><li x-text="e"></li></template>
            </ul>

            <button type="button" @click="checkout()" :disabled="submitting || !cart.length" class="btn-primary w-full py-3 text-base">
                <x-icon name="cart" /> {{ __('Complete sale') }} · <span x-text="money(total)"></span>
            </button>
        </div>
    </div>
</div>
@endsection
