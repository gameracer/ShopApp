<?php

use App\Support\Shop;

if (! function_exists('money')) {
    function money(float|string|null $amount, bool $withCurrency = true): string
    {
        return Shop::money($amount, $withCurrency);
    }
}

if (! function_exists('qty')) {
    function qty(float|string|null $quantity): string
    {
        return Shop::qty($quantity);
    }
}
