<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'shop_name' => 'My Shop',
        'shop_phone' => '',
        'shop_address' => '',
        'currency' => 'TZS',
        'expiry_alert_days' => '30',
        // Devices: thermal receipt printer + barcode scanner.
        'receipt_width' => '80',
        'receipt_footer' => '',
        'auto_print_receipt' => '0',
        'scanner_beep' => '1',
    ];

    /** Thermal paper roll widths in millimetres. */
    public const RECEIPT_WIDTHS = ['58', '80'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget('settings');
    }
}
