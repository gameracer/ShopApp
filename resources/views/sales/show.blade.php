@extends('layouts.app')
@section('title', $sale->invoice_no)

@section('content')
<x-page-header :title="__('Receipt').' '.$sale->invoice_no">
    <x-slot:actions>
        {{-- Thermal receipt: silent print in the desktop app, print dialog in a browser. ?print=1 (auto-print) prints once on arrival. --}}
        <div class="flex items-center gap-2" x-data="{
                status: null,
                async print() {
                    this.status = { ok: true, text: @js(__('Printing…')) };
                    const r = await ShopDevices.printReceipt(@js(route('sales.receipt', $sale)));
                    this.status = !ShopDevices.desktop && r.ok ? null : r.ok
                        ? { ok: true, text: @js(__('Printed')) + (r.printer ? ' · ' + r.printer : '') }
                        : { ok: false, text: @js(__('Printing failed')) + (r.error ? ': ' + r.error : '') };
                    if (this.status?.ok) setTimeout(() => this.status = null, 4000);
                },
             }"
             x-init="if (new URLSearchParams(location.search).has('print')) { history.replaceState(null, '', location.pathname); print(); }">
            <span x-cloak x-show="status" x-text="status?.text" :class="status?.ok ? 'text-emerald-700' : 'text-rose-700'" class="text-sm"></span>
            <button type="button" @click="print()" class="btn-secondary"><x-icon name="print" class="h-4 w-4" /> {{ __('Print receipt') }}</button>
            <button type="button" onclick="window.print()" class="btn-secondary" title="{{ __('Print this page on A4 paper') }}">A4</button>
        </div>
        <a href="{{ route('sales.create') }}" class="btn-primary"><x-icon name="cart" class="h-4 w-4" /> {{ __('New sale') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-6 lg:col-span-2">
        <div class="mb-4 text-center">
            <h2 class="text-lg font-bold">{{ \App\Support\Shop::name() }}</h2>
            @if ($address = \App\Models\Setting::get('shop_address'))<p class="text-xs text-slate-500">{{ $address }}</p>@endif
            @if ($phone = \App\Models\Setting::get('shop_phone'))<p class="text-xs text-slate-500">{{ __('Tel') }}: {{ $phone }}</p>@endif
        </div>
        <div class="mb-4 grid grid-cols-2 gap-2 text-sm">
            <div><span class="text-slate-500">{{ __('Invoice') }}:</span> {{ $sale->invoice_no }}</div>
            <div class="text-right"><span class="text-slate-500">{{ __('Date') }}:</span> {{ $sale->sold_at->format('d/m/Y H:i') }}</div>
            <div><span class="text-slate-500">{{ __('Seller') }}:</span> {{ $sale->user?->name }}</div>
            <div class="text-right"><span class="text-slate-500">{{ __('Customer') }}:</span> {{ $sale->customer?->name ?? __('Walk-in') }}</div>
        </div>
        <table class="table">
            <thead><tr><th>{{ __('Item') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Price') }}</th><th class="text-right">{{ __('Subtotal') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-right">{{ qty($item->quantity) }}</td>
                    <td class="text-right">{{ money($item->unit_price, false) }}</td>
                    <td class="text-right">{{ money($item->subtotal, false) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="text-sm">
                <tr><td colspan="3" class="px-3 pt-3 text-right font-semibold">{{ __('Total') }}</td><td class="px-3 pt-3 text-right text-lg font-bold">{{ money($sale->total) }}</td></tr>
                <tr><td colspan="3" class="px-3 text-right text-slate-500">{{ __('Paid') }} ({{ __(ucfirst($sale->payment_method)) }})</td><td class="px-3 text-right">{{ money($sale->amount_paid) }}</td></tr>
                @if ($sale->balance() > 0)
                    <tr><td colspan="3" class="px-3 text-right font-semibold text-rose-700">{{ __('Balance') }}</td><td class="px-3 text-right font-semibold text-rose-700">{{ money($sale->balance()) }}</td></tr>
                    @if ($sale->due_date)<tr><td colspan="3" class="px-3 text-right text-slate-500">{{ __('Due date') }}</td><td class="px-3 text-right">{{ $sale->due_date->format('d/m/Y') }}</td></tr>@endif
                @endif
            </tfoot>
        </table>
        <p class="mt-6 text-center text-xs text-slate-500">{{ __('Thank you for shopping with us!') }}</p>
    </div>

    <div class="no-print space-y-4">
        @if (auth()->user()->isOwner())
            <div class="card p-4 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">{{ __('Cost') }}</span><span>{{ money($sale->cost_total) }}</span></div>
                <div class="mt-1 flex justify-between font-semibold"><span>{{ __('Profit') }}</span><span class="text-emerald-700">{{ money($sale->profit()) }}</span></div>
            </div>
        @endif

        @if ($sale->balance() > 0)
            <form method="POST" action="{{ route('sales.payments.store', $sale) }}" class="card space-y-3 p-4">
                @csrf
                <h3 class="card-title">{{ __('Record payment') }}</h3>
                <input type="number" step="0.01" min="0.01" max="{{ $sale->balance() }}" name="amount" value="{{ $sale->balance() }}" class="input" required>
                <select name="method" class="input">
                    @foreach (['cash', 'mobile', 'bank'] as $m)<option value="{{ $m }}">{{ __(ucfirst($m)) }}</option>@endforeach
                </select>
                <input name="note" class="input" placeholder="{{ __('Note (optional)') }}">
                <button class="btn-primary w-full">{{ __('Save payment') }}</button>
            </form>
        @endif

        @if ($sale->payments->isNotEmpty())
            <div class="card">
                <div class="card-header"><h3 class="card-title">{{ __('Payments') }}</h3></div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @foreach ($sale->payments as $payment)
                        <li class="flex justify-between px-4 py-2">
                            <span>{{ $payment->paid_at->format('d/m/Y H:i') }}<span class="block text-xs text-slate-500">{{ __(ucfirst($payment->method)) }} · {{ $payment->user?->name }} {{ $payment->note ? '· '.$payment->note : '' }}</span></span>
                            <span class="font-medium text-emerald-700">{{ money($payment->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (auth()->user()->isOwner())
            <form method="POST" action="{{ route('sales.destroy', $sale) }}" onsubmit="return confirm('{{ __('Delete this sale? Items will be returned to stock.') }}')">
                @csrf @method('DELETE')
                <button class="btn-danger btn-sm"><x-icon name="trash" class="h-4 w-4" /> {{ __('Delete sale') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
