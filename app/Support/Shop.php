<?php

namespace App\Support;

use App\Models\Setting;

class Shop
{
    public const LOCALES = ['en' => 'English', 'sw' => 'Kiswahili'];

    public static function name(): string
    {
        return (string) Setting::get('shop_name');
    }

    public static function currency(): string
    {
        return (string) Setting::get('currency');
    }

    public static function expiryAlertDays(): int
    {
        return max(0, (int) Setting::get('expiry_alert_days'));
    }

    public static function money(float|string|null $amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) $amount, 2);

        return $withCurrency ? static::currency().' '.$formatted : $formatted;
    }

    /**
     * Format a quantity without trailing zeros (e.g. 5 instead of 5.00, 2.5 instead of 2.50).
     */
    public static function qty(float|string|null $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 2, '.', ','), '0'), '.');
    }
}
