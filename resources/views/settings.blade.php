@extends('layouts.app')
@section('title', __('Settings'))

@section('content')
<x-page-header :title="__('Settings')" />

<form method="POST" action="{{ route('settings.update') }}" class="card max-w-xl space-y-4 p-6">
    @csrf @method('PUT')
    <div>
        <label class="label">{{ __('Shop name') }} *</label>
        <input name="shop_name" class="input" value="{{ old('shop_name', $settings['shop_name']) }}" required>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">{{ __('Phone number') }}</label>
            <input name="shop_phone" class="input" value="{{ old('shop_phone', $settings['shop_phone']) }}">
        </div>
        <div>
            <label class="label">{{ __('Currency') }} *</label>
            <input name="currency" class="input" value="{{ old('currency', $settings['currency']) }}" required>
        </div>
    </div>
    <div>
        <label class="label">{{ __('Address') }}</label>
        <input name="shop_address" class="input" value="{{ old('shop_address', $settings['shop_address']) }}">
    </div>
    <div>
        <label class="label">{{ __('Expiry alert (days before expiry)') }} *</label>
        <input name="expiry_alert_days" type="number" min="1" max="365" class="input" value="{{ old('expiry_alert_days', $settings['expiry_alert_days']) }}" required>
        <p class="mt-1 text-xs text-slate-500">{{ __('Items expiring within this many days will show an alert.') }}</p>
    </div>

    <div class="space-y-4 border-t border-slate-100 pt-4">
        <h2 class="card-title">{{ __('Receipt printer & barcode scanner') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">{{ __('Receipt paper width') }} *</label>
                <select name="receipt_width" class="input">
                    @foreach (\App\Models\Setting::RECEIPT_WIDTHS as $w)
                        <option value="{{ $w }}" @selected(old('receipt_width', $settings['receipt_width']) === $w)>{{ $w }} mm</option>
                    @endforeach
                </select>
                @error('receipt_width')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">{{ __('Receipt footer') }}</label>
                <input name="receipt_footer" class="input" maxlength="255" value="{{ old('receipt_footer', $settings['receipt_footer']) }}" placeholder="{{ __('e.g. Goods once sold are not returnable') }}">
            </div>
        </div>
        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="auto_print_receipt" value="1" class="mt-0.5" @checked(old('auto_print_receipt', $settings['auto_print_receipt']) === '1')>
            <span>{{ __('Print the receipt automatically after every sale') }}</span>
        </label>
        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="scanner_beep" value="1" class="mt-0.5" @checked(old('scanner_beep', $settings['scanner_beep']) === '1')>
            <span>{{ __('Beep when a barcode is scanned') }}</span>
        </label>
    </div>
    <button class="btn-primary">{{ __('Save') }}</button>
</form>

{{-- Per-computer device setup. The printer choice is saved on this computer, not in the shop database. --}}
<div class="card mt-6 max-w-xl space-y-4 p-6"
     x-data="{
        desktop: !!window.ShopDevices?.desktop,
        printers: [],
        screens: [],
        screen: 'auto',
        printer: '',
        detected: null,
        status: null,
        scanned: null,
        async init() {
            if (!this.desktop) return;
            const info = await ShopDevices.desktop.printers();
            this.printers = info.printers;
            this.printer = info.selected ?? '';
            this.detected = info.detected;
            const screens = await ShopDevices.desktop.displays();
            this.screens = screens.displays;
            this.screen = screens.selected;
        },
        async chooseScreen() {
            await ShopDevices.desktop.setDisplay(this.screen);
        },
        async choose() {
            await ShopDevices.desktop.setPrinter(this.printer || null);
            this.detected = (await ShopDevices.desktop.printers()).detected;
        },
        async test() {
            const width = document.querySelector('select[name=receipt_width]').value;
            this.status = { ok: true, text: @js(__('Printing…')) };
            const r = await ShopDevices.printReceipt(@js(route('settings.test-receipt')) + '?width=' + width);
            this.status = r.ok
                ? (this.desktop ? { ok: true, text: @js(__('Printed')) + (r.printer ? ' · ' + r.printer : '') } : null)
                : { ok: false, text: @js(__('Printing failed')) + (r.error ? ': ' + r.error : '') };
        },
     }"
     @barcode.window.prevent="scanned = $event.detail.code; ShopDevices.beep()">
    <div>
        <h2 class="card-title">{{ __('Devices on this computer') }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ __('Plug in the USB receipt printer and barcode scanner. Install the printer driver from its maker if Windows does not find it.') }}</p>
    </div>

    <div class="space-y-2">
        <span class="label">{{ __('Receipt printer') }}</span>
        <template x-if="desktop">
            <div class="space-y-2">
                <div class="flex gap-2">
                    <select x-model="printer" @change="choose()" class="input">
                        <option value="">{{ __('Automatic (find receipt printer)') }}</option>
                        <template x-for="p in printers" :key="p.name">
                            <option :value="p.name" x-text="p.displayName + (p.isDefault ? ' (' + @js(__('Windows default')) + ')' : '')" :selected="p.name === printer"></option>
                        </template>
                    </select>
                    <button type="button" @click="init()" class="btn-secondary" title="{{ __('Refresh list') }}">↻</button>
                </div>
                <p x-show="!printer" class="text-xs text-slate-500">
                    <span x-show="detected">{{ __('Will print to') }}: <strong x-text="detected"></strong></span>
                    <span x-show="!detected" class="text-rose-700">{{ __('No printer found. Plug in the printer and press ↻.') }}</span>
                </p>
            </div>
        </template>
        <p x-show="!desktop" class="text-sm text-slate-600">{{ __('In a web browser the print window opens: choose the receipt printer there, set margins to "None", and tick "Remember". The ShopApp Windows app prints without asking.') }}</p>
        <div class="flex items-center gap-3">
            <button type="button" @click="test()" class="btn-secondary"><x-icon name="print" class="h-4 w-4" /> {{ __('Print test receipt') }}</button>
            <span x-cloak x-show="status" x-text="status?.text" :class="status?.ok ? 'text-emerald-700' : 'text-rose-700'" class="text-sm"></span>
        </div>
    </div>

    <div class="space-y-2 border-t border-slate-100 pt-4">
        <span class="label">{{ __('Customer display (second screen)') }}</span>
        <template x-if="desktop">
            <div class="space-y-2">
                <select x-model="screen" @change="chooseScreen()" class="input">
                    <option value="auto">{{ __('Automatic (any second screen)') }}</option>
                    <option value="off">{{ __('Off') }}</option>
                    <template x-for="s in screens.filter((s) => !s.till)" :key="s.id">
                        <option :value="s.id" x-text="s.label" :selected="s.id === screen"></option>
                    </template>
                </select>
                <p class="text-xs text-slate-500">
                    <span x-text="screens.length"></span> {{ __('screen(s) connected.') }}
                    <span x-show="screens.length < 2">{{ __('Connect a second screen to show customers their items and total.') }}</span>
                </p>
            </div>
        </template>
        <div x-show="!desktop" class="space-y-2">
            <p class="text-sm text-slate-600">{{ __('Open the customer display, drag it to the second screen and press F11') }}.</p>
            <button type="button" class="btn-secondary" onclick="window.open('{{ route('display') }}', 'shopapp-display', 'popup,width=1024,height=768')">
                <x-icon name="user" class="h-4 w-4" /> {{ __('Customer display') }}
            </button>
        </div>
    </div>

    <div class="space-y-2 border-t border-slate-100 pt-4">
        <span class="label">{{ __('Barcode scanner') }}</span>
        <p class="text-sm text-slate-600">{{ __('Scan any barcode now to test the scanner.') }}</p>
        <div class="rounded-lg bg-slate-50 px-3 py-2 font-mono text-lg" :class="scanned ? 'text-emerald-700' : 'text-slate-400'"
             x-text="scanned ?? @js(__('Waiting for a scan…'))"></div>
    </div>
</div>
@endsection
