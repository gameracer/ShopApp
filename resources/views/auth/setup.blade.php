<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Set up your shop') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-900 p-4 font-sans antialiased">
    <div class="w-full max-w-xl">
        <div class="mb-6 text-center text-white">
            <span class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-600"><x-icon name="cart" class="h-6 w-6" /></span>
            <h1 class="text-2xl font-semibold">{{ __('Set up your shop') }}</h1>
            <p class="text-sm text-slate-300">{{ __('Create the accounts that will use this shop. You only do this once.') }}</p>
        </div>

        @php($locales = \App\Support\Shop::LOCALES)

        <form method="POST" action="{{ route('setup.store') }}" class="card space-y-6 p-6"
              x-data="{ addSeller: {{ old('add_seller', '1') ? 'true' : 'false' }} }">
            @csrf

            <section class="space-y-3">
                <h2 class="font-semibold text-slate-800">{{ __('Shop') }}</h2>
                <div>
                    <label class="label" for="shop_name">{{ __('Shop name') }} <span class="font-normal text-slate-400">({{ __('optional') }})</span></label>
                    <input id="shop_name" name="shop_name" class="input" value="{{ old('shop_name') }}" placeholder="{{ \App\Models\Setting::DEFAULTS['shop_name'] }}" maxlength="100" autofocus>
                    @error('shop_name')<p class="error">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="space-y-3 border-t border-slate-200 pt-5">
                <h2 class="font-semibold text-slate-800">{{ __('Owner account') }}</h2>
                <p class="text-xs text-slate-500">{{ __('The owner can see buying prices and profit, and manage products, expenses, reports, users and settings.') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="owner_name">{{ __('Name') }}</label>
                        <input id="owner_name" name="owner_name" class="input" value="{{ old('owner_name') }}" required>
                        @error('owner_name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="owner_email">{{ __('Email') }}</label>
                        <input id="owner_email" name="owner_email" type="email" class="input" value="{{ old('owner_email') }}" required>
                        @error('owner_email')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="owner_password">{{ __('Password') }}</label>
                        <input id="owner_password" name="owner_password" type="password" class="input" required minlength="6" autocomplete="new-password">
                        @error('owner_password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="owner_password_confirmation">{{ __('Confirm password') }}</label>
                        <input id="owner_password_confirmation" name="owner_password_confirmation" type="password" class="input" required autocomplete="new-password">
                    </div>
                    <div>
                        <label class="label" for="owner_locale">{{ __('Language') }}</label>
                        <select id="owner_locale" name="owner_locale" class="input">
                            @foreach ($locales as $code => $label)
                                <option value="{{ $code }}" @selected(old('owner_locale', app()->getLocale()) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="space-y-3 border-t border-slate-200 pt-5">
                <input type="hidden" name="add_seller" value="0">
                <label class="flex items-center gap-2 font-semibold text-slate-800">
                    <input type="checkbox" name="add_seller" value="1" x-model="addSeller" class="rounded border-slate-300 text-emerald-600">
                    {{ __('Add a seller account') }}
                </label>
                <p class="text-xs text-slate-500">{{ __('Sellers can sell, add stock and manage customers and credit. You can add more users later under Users.') }}</p>
                <div class="grid gap-3 sm:grid-cols-2" x-show="addSeller" x-cloak>
                    <div>
                        <label class="label" for="seller_name">{{ __('Name') }}</label>
                        <input id="seller_name" name="seller_name" class="input" value="{{ old('seller_name') }}" x-bind:required="addSeller" x-bind:disabled="!addSeller">
                        @error('seller_name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="seller_email">{{ __('Email') }}</label>
                        <input id="seller_email" name="seller_email" type="email" class="input" value="{{ old('seller_email') }}" x-bind:required="addSeller" x-bind:disabled="!addSeller">
                        @error('seller_email')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="seller_password">{{ __('Password') }}</label>
                        <input id="seller_password" name="seller_password" type="password" class="input" minlength="6" autocomplete="new-password" x-bind:required="addSeller" x-bind:disabled="!addSeller">
                        @error('seller_password')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="seller_password_confirmation">{{ __('Confirm password') }}</label>
                        <input id="seller_password_confirmation" name="seller_password_confirmation" type="password" class="input" autocomplete="new-password" x-bind:required="addSeller" x-bind:disabled="!addSeller">
                    </div>
                    <div>
                        <label class="label" for="seller_locale">{{ __('Language') }}</label>
                        <select id="seller_locale" name="seller_locale" class="input" x-bind:disabled="!addSeller">
                            @foreach ($locales as $code => $label)
                                <option value="{{ $code }}" @selected(old('seller_locale', 'sw') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <button class="btn-primary w-full">{{ __('Finish setup') }}</button>
            <div class="flex justify-center"><x-lang-switch /></div>
        </form>
    </div>
</body>
</html>
