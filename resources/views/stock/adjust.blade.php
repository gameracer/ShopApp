@extends('layouts.app')
@section('title', __('Adjust stock'))

@section('content')
<x-page-header :title="__('Adjust stock')" :subtitle="$product->name">
    <x-slot:actions>
        <a href="{{ route('products.show', $product) }}" class="btn-secondary">{{ __('Back') }}</a>
    </x-slot:actions>
</x-page-header>

@php $current = (float) $product->quantity; @endphp
<form method="POST" action="{{ route('stock.adjust.store', $product) }}" class="card max-w-xl space-y-5 p-6"
      x-data="{ current: {{ $current }}, counted: {{ (float) old('quantity', $current) }} }">
    @csrf

    <p class="text-sm text-slate-600">{{ __('Enter the quantity that is really on the shelf. The difference is saved in the stock history with the reason.') }}</p>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <span class="label">{{ __('Current stock') }}</span>
            <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold">{{ qty($current) }} {{ $product->unit }}</div>
        </div>
        <div>
            <label class="label" for="quantity">{{ __('Correct quantity') }} *</label>
            <input id="quantity" name="quantity" type="number" step="0.01" min="0" class="input" x-model.number="counted" required autofocus>
            @error('quantity')<p class="error">{{ $message }}</p>@enderror
        </div>
        <div>
            <span class="label">{{ __('Difference') }}</span>
            <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold"
                 :class="counted - current < 0 ? 'text-rose-600' : (counted - current > 0 ? 'text-emerald-700' : 'text-slate-500')"
                 x-text="(counted - current > 0 ? '+' : '') + Math.round((counted - current) * 100) / 100 + ' {{ $product->unit }}'"></div>
        </div>
    </div>

    <div>
        <label class="label" for="reason">{{ __('Reason') }} *</label>
        <select id="reason" name="reason" class="input" required>
            <option value="">{{ __('Choose a reason') }}</option>
            @foreach ($reasons as $value => $label)
                <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('reason')<p class="error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="label" for="note">{{ __('Note') }}</label>
        <input id="note" name="note" class="input" maxlength="200" value="{{ old('note') }}" placeholder="{{ __('Optional') }}">
        @error('note')<p class="error">{{ $message }}</p>@enderror
    </div>

    <button class="btn-primary w-full">{{ __('Save adjustment') }}</button>
</form>
@endsection
