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

{{-- Automatic backups. Times are in the timezone of the computer the settings were saved on. --}}
@php
    $fmt = fn ($date) => $date?->locale(app()->getLocale())->isoFormat('ddd D MMM YYYY, HH:mm');
    $weekdays = [__('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday')];
@endphp
<div id="backups" class="card mt-6 max-w-xl space-y-4 p-6"
     x-data="{
        desktop: !!window.ShopDevices?.desktop?.chooseBackupFolder,
        enabled: @js(old('backup_enabled', $settings['backup_enabled']) === '1'),
        frequency: @js(old('backup_frequency', $settings['backup_frequency'])),
        copyPath: @js(old('backup_copy_path', $settings['backup_copy_path'])),
        async chooseFolder() {
            const folder = await ShopDevices.desktop.chooseBackupFolder(this.copyPath || null);
            if (folder) this.copyPath = folder;
        },
     }">
    <div>
        <h2 class="card-title">{{ __('Backups') }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ __('A backup is a full copy of the shop data: products, sales, customers, credit, expenses, users and settings.') }}</p>
    </div>

    <dl class="grid gap-x-4 gap-y-1 rounded-lg bg-slate-50 px-4 py-3 text-sm sm:grid-cols-[auto_1fr]">
        <dt class="text-slate-500">{{ __('Last backup') }}</dt>
        <dd>
            @if ($backup['last'])
                {{ $fmt($backup['last']) }} <span class="text-slate-500">({{ $backup['last']->locale(app()->getLocale())->diffForHumans() }})</span>
            @else
                <span class="text-amber-700">{{ __('No backup yet') }}</span>
            @endif
        </dd>
        <dt class="text-slate-500">{{ __('Next automatic backup') }}</dt>
        <dd>{{ $backup['next'] ? $fmt($backup['next']) : __('Off') }}</dd>
        <dt class="text-slate-500">{{ __('Backup folder') }}</dt>
        <dd class="break-all font-mono text-xs leading-5">{{ $backup['folder'] }}</dd>
    </dl>
    @if ($backup['error'])
        <p class="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
            <x-icon name="warning" class="mt-0.5 h-4 w-4 shrink-0" /> <span>{{ __('Last backup problem') }}: {{ $backup['error'] }}</span>
        </p>
    @endif

    <form method="POST" action="{{ route('backups.settings') }}" class="space-y-4">
        @csrf @method('PUT')
        <input type="hidden" name="backup_timezone" x-init="try { $el.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '' } catch {}">

        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="backup_enabled" value="1" x-model="enabled" class="mt-0.5">
            <span>{{ __('Back up automatically') }}</span>
        </label>

        {{-- Hidden fields are still sent, so the schedule is kept while automatic backups are off. --}}
        <div x-show="enabled" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label">{{ __('How often') }}</label>
                    <select name="backup_frequency" x-model="frequency" class="input">
                        <option value="hourly">{{ __('Every hour') }}</option>
                        <option value="daily">{{ __('Every day') }}</option>
                        <option value="weekly">{{ __('Every week') }}</option>
                    </select>
                </div>
                <div x-show="frequency === 'weekly'">
                    <label class="label">{{ __('Day') }}</label>
                    <select name="backup_weekday" class="input">
                        @foreach ($weekdays as $i => $day)
                            <option value="{{ $i }}" @selected((string) old('backup_weekday', $settings['backup_weekday']) === (string) $i)>{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="frequency !== 'hourly'">
                    <label class="label">{{ __('Time') }}</label>
                    <input type="time" name="backup_time" class="input" value="{{ old('backup_time', $settings['backup_time']) }}" required>
                    @error('backup_time')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="text-xs text-slate-500">{{ __('If the computer is off at that time, the backup is made as soon as ShopApp is opened again.') }}</p>

            <div>
                <label class="label">{{ __('Number of automatic backups to keep') }}</label>
                <input type="number" name="backup_keep" min="1" max="365" class="input w-32" value="{{ old('backup_keep', $settings['backup_keep']) }}" required>
                <p class="mt-1 text-xs text-slate-500">{{ __('Older automatic backups are deleted. Manual backups and backups made before updates are kept separately (the newest 30).') }}</p>
                @error('backup_keep')<p class="error">{{ $message }}</p>@enderror
            </div>

            <label x-show="desktop" class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="backup_on_close" value="1" class="mt-0.5" @checked(old('backup_on_close', $settings['backup_on_close']) === '1')>
                <span>{{ __('Also back up when ShopApp is closed') }}</span>
            </label>
        </div>

        <div>
            <label class="label">{{ __('Also copy every backup to this folder (optional)') }}</label>
            <div class="flex gap-2">
                <input name="backup_copy_path" x-model="copyPath" class="input font-mono text-sm" maxlength="255" placeholder="{{ __('e.g. D:\ShopApp backups') }}">
                <button type="button" x-show="desktop" @click="chooseFolder()" class="btn-secondary shrink-0">{{ __('Choose…') }}</button>
            </div>
            <p class="mt-1 text-xs text-slate-500">{{ __('A USB drive, a second disk or a OneDrive / Google Drive folder keeps your data safe if this computer breaks or is stolen.') }}</p>
            @error('backup_copy_path')<p class="error">{{ $message }}</p>@enderror
        </div>

        <button class="btn-primary">{{ __('Save') }}</button>
    </form>

    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
        <form method="POST" action="{{ route('backups.store') }}">
            @csrf
            <button class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> {{ __('Back up now') }}</button>
        </form>
        <button type="button" x-show="desktop" x-cloak @click="ShopDevices.desktop.openBackupFolder()" class="btn-secondary">{{ __('Open backup folder') }}</button>
        <button type="button" x-show="desktop" x-cloak @click="ShopDevices.desktop.restoreBackup()" class="btn-secondary">{{ __('Restore a backup…') }}</button>
    </div>

    @if ($backup['files'])
        <div>
            <span class="label">{{ __('Recent backups') }}</span>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($backup['files'] as $file)
                    <li class="flex items-center justify-between gap-3 py-1.5">
                        <span>
                            {{ $fmt($file['at']) }}
                            <span class="text-xs text-slate-500">· {{ $file['automatic'] ? __('automatic') : __('manual / before update') }} · {{ \Illuminate\Support\Number::fileSize($file['size'], 1) }}</span>
                        </span>
                        <a href="{{ route('backups.download', $file['name']) }}" class="text-xs font-medium text-emerald-700 hover:underline" title="{{ $file['name'] }}">{{ __('Download') }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

{{-- Per-computer device setup. The printer choice is saved on this computer, not in the shop database. --}}
<div class="card mt-6 max-w-xl space-y-4 p-6"
     x-data="{
        desktop: !!window.ShopDevices?.desktop,
        printers: [],
        screens: [],
        screen: 'auto',
        keyboard: window.ShopKeyboard?.pref() ?? 'auto',
        printer: '',
        mode: 'escpos',
        cashDrawer: false,
        detected: null,
        status: null,
        scanned: null,
        match: undefined,
        async testScan(code) {
            this.scanned = code;
            this.match = null;
            const product = await ShopDevices.lookupBarcode(code).catch(() => null);
            this.match = product ? product.name + (product.sku !== code ? ' (' + @js(__('saved as')) + ' ' + product.sku + ')' : '') : false;
            ShopDevices.beep(!!product);
        },
        async init() {
            if (!this.desktop) return;
            const info = await ShopDevices.desktop.printers();
            this.printers = info.printers;
            this.printer = info.selected ?? '';
            this.detected = info.detected;
            this.mode = info.mode ?? 'escpos';
            this.cashDrawer = !!info.cashDrawer;
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
        async chooseMode() {
            await ShopDevices.desktop.setPrintMode(this.mode);
        },
        async chooseDrawer() {
            await ShopDevices.desktop.setCashDrawer(this.cashDrawer);
        },
        async test(dialog = false) {
            const width = document.querySelector('select[name=receipt_width]').value;
            const url = @js(route('settings.test-receipt')) + '?width=' + width;
            this.status = dialog ? { ok: true, text: @js(__('Printing…')) } : null;
            const r = dialog ? await ShopDevices.printReceipt(url, { dialog }) : await ShopDevices.previewReceipt(url);
            if (!r) return; // preview closed without printing
            this.status = r.ok
                ? (this.desktop ? { ok: true, text: @js(__('Printed')) + (r.printer ? ' · ' + r.printer : '') } : null)
                : { ok: false, text: @js(__('Printing failed')) + (r.error ? ': ' + r.error : '') };
        },
     }"
     @barcode.window.prevent="testScan($event.detail.code)">
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
                    <span x-show="!detected" class="text-rose-700">{{ __('No receipt printer found. Plug in the printer, install its driver and press ↻, or pick it from the list.') }}</span>
                </p>
                <label class="label mt-2">{{ __('Printing method') }}</label>
                <select x-model="mode" @change="chooseMode()" class="input">
                    <option value="escpos">{{ __('Direct to thermal printer (ESC/POS, recommended)') }}</option>
                    <option value="driver">{{ __('Windows driver, paper size from the printer settings') }}</option>
                    <option value="fit">{{ __('Windows driver, page cut to the receipt') }}</option>
                </select>
                <p class="text-xs text-slate-500">{{ __('Direct sends the receipt to the printer in its own language, which almost all 58/80 mm thermal printers understand. Use a Windows driver option only for a printer that is not a thermal receipt printer.') }}</p>
                <label x-show="mode === 'escpos'" class="flex items-start gap-2 text-sm">
                    <input type="checkbox" x-model="cashDrawer" @change="chooseDrawer()" class="mt-0.5">
                    <span>{{ __('Open the cash drawer with every receipt (drawer plugged into the printer)') }}</span>
                </label>
            </div>
        </template>
        <p x-show="!desktop" class="text-sm text-slate-600">{{ __('In a web browser the print window opens: choose the receipt printer there, set margins to "None", and tick "Remember". The ShopApp Windows app prints without asking.') }}</p>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" @click="test()" class="btn-secondary"><x-icon name="print" class="h-4 w-4" /> {{ __('Print test receipt') }}</button>
            <button type="button" x-show="desktop" @click="test(true)" class="btn-secondary" title="{{ __('Opens the Windows print window, to check the printer and its settings') }}">{{ __('Print with dialog') }}</button>
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
        <span class="label">{{ __('On-screen keyboard') }}</span>
        <select x-model="keyboard" @change="ShopKeyboard.setPref(keyboard)" class="input">
            <option value="auto">{{ __('Show when a field is touched') }}</option>
            <option value="off">{{ __('Off') }}</option>
        </select>
        <p class="text-xs text-slate-500">{{ __('For touch screens. It opens only when you tap a field with your finger, never with the mouse. Saved on this computer.') }}</p>
    </div>

    <div class="space-y-2 border-t border-slate-100 pt-4">
        <span class="label">{{ __('Barcode scanner') }}</span>
        <p class="text-sm text-slate-600">{{ __('Scan any barcode now to test the scanner.') }}</p>
        <div class="rounded-lg bg-slate-50 px-3 py-2 font-mono text-lg" :class="scanned ? 'text-emerald-700' : 'text-slate-400'"
             x-text="scanned ?? @js(__('Waiting for a scan…'))"></div>
        <p x-cloak x-show="scanned" class="text-sm">
            <span class="text-slate-500"><span x-text="scanned?.length"></span> {{ __('characters') }} ·</span>
            <span x-show="match === null" class="text-slate-500">{{ __('Searching…') }}</span>
            <span x-show="match" class="text-emerald-700">✓ <span x-text="match"></span></span>
            <span x-show="match === false" class="text-rose-700">✕ {{ __('No product has this barcode. Check the SKU / Barcode saved on the product.') }}</span>
        </p>
    </div>
</div>
@endsection
