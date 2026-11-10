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
    <button class="btn-primary">{{ __('Save') }}</button>
</form>
@endsection
