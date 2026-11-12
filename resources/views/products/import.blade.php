@extends('layouts.app')
@section('title', __('Import products'))

@section('content')
<x-page-header :title="__('Import products')" :subtitle="__('Add many products at once from a CSV file (Excel: File → Save As → CSV).')">
    <x-slot:actions>
        <a href="{{ route('products.import.template') }}" class="btn-secondary"><x-icon name="download" class="h-4 w-4" /> {{ __('Download template') }}</a>
        <a href="{{ route('products.index') }}" class="btn-secondary">{{ __('Back to products') }}</a>
    </x-slot:actions>
</x-page-header>

@if ($result && count($result['skipped']) > 0)
    <div class="card mb-4 overflow-x-auto">
        <div class="card-header">
            <h2 class="card-title flex items-center gap-2 text-amber-700">
                <x-icon name="warning" class="h-5 w-5" />
                {{ trans_choice(':count row was skipped|:count rows were skipped', count($result['skipped'])) }}
            </h2>
        </div>
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
    <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="card space-y-5 p-6 lg:col-span-2">
        @csrf
        <div>
            <label class="label" for="file">{{ __('CSV file') }} *</label>
            <input id="file" name="file" type="file" accept=".csv,text/csv" class="input" required>
            @error('file')<p class="error">{{ $message }}</p>@enderror
        </div>
        <p class="text-sm text-slate-600">
            {{ __('Each product name must be unique. Rows whose name already exists (ignoring capital letters and extra spaces), or that repeat a name earlier in the file, are skipped and listed so you can fix them.') }}
        </p>
        <button class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('Import') }}</button>
    </form>

    <div class="card p-6 text-sm">
        <h2 class="card-title mb-3">{{ __('Columns') }}</h2>
        <ul class="space-y-1.5 text-slate-600">
            <li><code class="font-semibold text-slate-900">name</code> * — {{ __('unique product name') }}</li>
            <li><code class="font-semibold text-slate-900">buying_price</code> * , <code class="font-semibold text-slate-900">selling_price</code> *</li>
            <li><code>sku</code> — {{ __('SKU / Barcode (must be unique)') }}</li>
            <li><code>unit</code> — {{ __('defaults to pcs') }}</li>
            <li><code>quantity</code>, <code>reorder_level</code> — {{ __('default 0') }}</li>
            <li><code>expiry_date</code> — {{ __('YYYY-MM-DD or DD/MM/YYYY') }}</li>
            <li><code>tags</code> — {{ __('separated by ;') }}</li>
            <li><code>description</code></li>
        </ul>
    </div>
</div>
@endsection
