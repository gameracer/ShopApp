<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Log in') }} · {{ \App\Support\Shop::name() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-900 p-4 font-sans antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center text-white">
            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600"><x-icon name="cart" class="h-6 w-6" /></span>
            <h1 class="text-2xl font-semibold">{{ \App\Support\Shop::name() }}</h1>
            <p class="text-sm text-slate-300">{{ __('Shop management system') }}</p>
        </div>
        @php($email = old('email', $lastEmail))
        <form method="POST" action="{{ route('login') }}" class="card space-y-4 p-6" x-data="{ showPassword: false }">
            @csrf
            @if ($lastEmail && $email === $lastEmail)
                <p class="text-sm font-medium text-slate-700">{{ __('Welcome back') }}</p>
            @endif
            <div>
                <div class="flex items-center justify-between">
                    <label class="label" for="email">{{ __('Email') }}</label>
                    @if ($email)
                        <button type="button" class="text-xs text-emerald-700 hover:underline"
                                x-on:click="$refs.email.value = ''; $refs.password.value = ''; $refs.email.focus()">{{ __('Use another account') }}</button>
                    @endif
                </div>
                <input id="email" name="email" type="email" value="{{ $email }}" class="input" x-ref="email"
                       autocomplete="username" required @unless ($email) autofocus @endunless>
                @error('email')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password">{{ __('Password') }}</label>
                <div class="relative">
                    <input id="password" name="password" type="password" class="input pr-10" x-ref="password"
                           x-bind:type="showPassword ? 'text' : 'password'"
                           autocomplete="current-password" required @if ($email) autofocus @endif>
                    <button type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-500 hover:text-slate-700"
                            x-on:click="showPassword = !showPassword; $refs.password.focus()"
                            x-bind:aria-label="showPassword ? @js(__('Hide password')) : @js(__('Show password'))"
                            x-bind:title="showPassword ? @js(__('Hide password')) : @js(__('Show password'))"
                            aria-label="{{ __('Show password') }}">
                        <x-icon name="eye" class="h-5 w-5" x-show="!showPassword" />
                        <x-icon name="eye-off" class="h-5 w-5" x-show="showPassword" x-cloak />
                    </button>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-emerald-600"> {{ __('Remember me') }}
            </label>
            <button class="btn-primary w-full">{{ __('Log in') }}</button>
            <div class="flex justify-center"><x-lang-switch /></div>
        </form>
        <p class="mt-4 text-center text-xs text-slate-400"><x-app-version /> · <x-credit /></p>
    </div>
</body>
</html>
