@extends('layouts.app')
@section('title', __('My profile'))

@section('content')
<x-page-header :title="__('My profile')" :subtitle="$user->email" />

<form method="POST" action="{{ route('profile.update') }}" class="card max-w-xl space-y-4 p-6">
    @csrf @method('PUT')
    <div>
        <label class="label">{{ __('Name') }}</label>
        <input name="name" class="input" value="{{ old('name', $user->name) }}" required>
    </div>
    <div>
        <label class="label">{{ __('Language') }}</label>
        <select name="locale" class="input">
            @foreach (\App\Support\Shop::LOCALES as $code => $label)
                <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <hr class="border-slate-200">
    <p class="text-sm text-slate-500">{{ __('Leave blank to keep your current password.') }}</p>
    <div>
        <label class="label">{{ __('Current password') }}</label>
        <input name="current_password" type="password" class="input" autocomplete="current-password">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">{{ __('New password') }}</label>
            <input name="password" type="password" class="input" autocomplete="new-password">
        </div>
        <div>
            <label class="label">{{ __('Confirm password') }}</label>
            <input name="password_confirmation" type="password" class="input" autocomplete="new-password">
        </div>
    </div>
    <button class="btn-primary">{{ __('Save') }}</button>
</form>
@endsection
