@extends('layouts.app')
@section('title', $user->exists ? __('Edit user') : __('New user'))

@section('content')
<x-page-header :title="$user->exists ? __('Edit user') : __('New user')" />

<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card max-w-xl space-y-4 p-6">
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div>
        <label class="label">{{ __('Name') }} *</label>
        <input name="name" class="input" value="{{ old('name', $user->name) }}" required>
    </div>
    <div>
        <label class="label">{{ __('Email') }} *</label>
        <input name="email" type="email" class="input" value="{{ old('email', $user->email) }}" required>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">{{ __('Role') }} *</label>
            <select name="role" class="input">
                @foreach (\App\Models\User::ROLES as $role)<option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ __(ucfirst($role)) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label">{{ __('Language') }} *</label>
            <select name="locale" class="input">
                @foreach (\App\Support\Shop::LOCALES as $code => $label)<option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $label }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">{{ __('Password') }} {{ $user->exists ? '' : '*' }}</label>
            <input name="password" type="password" class="input" autocomplete="new-password" @required(! $user->exists)>
            @if ($user->exists)<p class="mt-1 text-xs text-slate-500">{{ __('Leave blank to keep the current password.') }}</p>@endif
        </div>
        <div>
            <label class="label">{{ __('Confirm password') }}</label>
            <input name="password_confirmation" type="password" class="input" autocomplete="new-password">
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600" @checked(old('is_active', $user->is_active))> {{ __('Active (can log in)') }}
    </label>
    <div class="flex gap-2">
        <button class="btn-primary">{{ __('Save') }}</button>
        <a href="{{ route('users.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
