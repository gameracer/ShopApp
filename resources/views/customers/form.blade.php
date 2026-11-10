@extends('layouts.app')
@section('title', $customer->exists ? __('Edit customer') : __('New customer'))

@section('content')
<x-page-header :title="$customer->exists ? __('Edit customer') : __('New customer')" />

<form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="card max-w-xl space-y-4 p-6">
    @csrf
    @if ($customer->exists) @method('PUT') @endif
    <div>
        <label class="label">{{ __('Name') }} *</label>
        <input name="name" class="input" value="{{ old('name', $customer->name) }}" required autofocus>
    </div>
    <div>
        <label class="label">{{ __('Phone number') }}</label>
        <input name="phone" class="input" value="{{ old('phone', $customer->phone) }}">
    </div>
    <div>
        <label class="label">{{ __('Address') }}</label>
        <input name="address" class="input" value="{{ old('address', $customer->address) }}">
    </div>
    <div class="flex gap-2">
        <button class="btn-primary">{{ __('Save') }}</button>
        <a href="{{ route('customers.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
