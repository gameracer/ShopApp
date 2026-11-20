{{-- "ShopApp v1.2.3" (config app.version: the desktop app's version, or desktop/package.json). --}}
@if ($version = config('app.version'))
    <span {{ $attributes->class('select-all tabular-nums') }} title="{{ __('Version') }} {{ $version }}">{{ config('app.name') }} v{{ $version }}</span>
@endif
