@extends('layouts.app')
@section('title', __('Import products'))

@section('content')
<x-page-header :title="__('Import products')" :subtitle="__('Add new products, or new stock for your products, from a CSV or Excel file.')">
    <x-slot:actions>
        <a href="{{ route('products.export', ['format' => 'xlsx']) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> {{ __('Export products (Excel)') }}</a>
        <a href="{{ route('products.export', ['format' => 'csv']) }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> {{ __('Export products (CSV)') }}</a>
        <a href="{{ route('products.import.template') }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> {{ __('Download template') }}</a>
        <a href="{{ route('products.index') }}" class="btn-secondary">{{ __('Back to products') }}</a>
    </x-slot:actions>
</x-page-header>

@if ($result && (($result['stocked'] ?? 0) > 0 || ($result['unchanged'] ?? 0) > 0))
    <div class="card mb-4 p-4 text-sm text-slate-700">
        <ul class="space-y-1">
            <li>{{ trans_choice(':count product imported.|:count products imported.', $result['imported']) }}</li>
            <li>{{ trans_choice('Stock added to :count product.|Stock added to :count products.', $result['stocked'] ?? 0) }}</li>
            <li class="text-slate-500">{{ trans_choice(':count product already in the shop had no stock to add and was left unchanged.|:count products already in the shop had no stock to add and were left unchanged.', $result['unchanged'] ?? 0) }}</li>
        </ul>
    </div>
@endif

@if ($result && count($result['skipped']) > 0)
    <div class="card mb-4 overflow-x-auto">
        <div class="card-header">
            <h2 class="card-title flex items-center gap-2 text-amber-700">
                <x-icon name="warning" class="h-5 w-5" />
                {{ trans_choice(':count row was skipped|:count rows were skipped', $result['skipped_total'] ?? count($result['skipped'])) }}
            </h2>
        </div>
        @if (($result['skipped_total'] ?? 0) > count($result['skipped']))
            <p class="px-6 pb-3 text-sm text-slate-600">{{ __('Showing the first :shown. Fix these rows and import the file again to see the rest.', ['shown' => count($result['skipped'])]) }}</p>
        @endif
        <table class="table">
            <thead>
            <tr>
                <th>{{ __('Row') }}</th>
                <th>{{ __('Product name') }}</th>
                <th>{{ __('Reason') }}</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($result['skipped'] as $skip)
                <tr>
                    <td class="text-slate-500">{{ $skip['row'] }}</td>
                    <td class="font-medium">{{ $skip['name'] !== '' ? $skip['name'] : '—' }}</td>
                    <td class="text-rose-600">{{ $skip['reason'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="grid gap-4 lg:grid-cols-3">
    <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="card space-y-5 p-6 lg:col-span-2"
          x-data="{ busy: false, tooBig: false, maxBytes: {{ $maxFileKb * 1024 }} }"
          @submit="if (tooBig) { $event.preventDefault(); return; } busy = true">
        @csrf
        <div>
            <label class="label" for="file">{{ __('CSV or Excel file') }} *</label>
            <input id="file" name="file" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="input" required
                   @change="tooBig = ($event.target.files[0]?.size ?? 0) > maxBytes">
            <p class="mt-1 text-xs text-slate-500">{{ __('Up to :size MB.', ['size' => $maxFileKb / 1024]) }}</p>
            <p x-show="tooBig" x-cloak class="error">{{ __('The file must not be larger than :size MB.', ['size' => $maxFileKb / 1024]) }}</p>
            @error('file')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div class="space-y-2 text-sm text-slate-600">
            <p class="font-medium text-slate-800">{{ __('To add new stock:') }}</p>
            <ol class="list-decimal space-y-1 pl-5">
                <li>{{ __('Export your products (Excel is easiest).') }}</li>
                <li>{{ __('In the add_stock column, type how many arrived of each product. Leave it empty for products that did not arrive. Change the prices or expiry date on that row if they changed.') }}</li>
                <li>{{ __('Save the file and import it here.') }}</li>
            </ol>
            <p>{{ __('Rows with a new product name are added as new products. Each product name must be unique: a new name repeated in the file is skipped and listed so you can fix it.') }}</p>
        </div>
        <button class="btn-primary" :disabled="busy || tooBig">
            <x-icon name="plus" class="h-4 w-4" />
            <span x-text="busy ? @js(__('Importing… large files can take a minute')) : @js(__('Import'))">{{ __('Import') }}</span>
        </button>
    </form>

    <div class="card p-6 text-sm">
        <h2 class="card-title mb-3">{{ __('Columns') }}</h2>
        <ul class="space-y-1.5 text-slate-600">
            <li><code class="font-semibold text-slate-900">name</code> * — {{ __('unique product name') }}</li>
            <li><code class="font-semibold text-slate-900">buying_price</code> * , <code class="font-semibold text-slate-900">selling_price</code> *</li>
            <li><code>sku</code> — {{ __('SKU / Barcode (must be unique)') }}</li>
            <li><code>unit</code> — {{ __('defaults to pcs') }}</li>
            <li><code>quantity</code>, <code>reorder_level</code> — {{ __('default 0') }}</li>
            <li><code>add_stock</code> — {{ __('new stock received; added to the current stock') }}</li>
            <li><code>expiry_date</code> — {{ __('YYYY-MM-DD or DD/MM/YYYY') }}</li>
            <li><code>tags</code> — {{ __('separated by ;') }}</li>
            <li><code>description</code></li>
        </ul>
        <p class="mt-3 text-xs text-slate-500">{{ __('For products already in the shop, only add_stock, the prices and the expiry date are used. Change other details on the product page.') }}</p>
    </div>
</div>
@endsection
