<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockEntry extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'quantity', 'buying_price',
        'selling_price', 'expiry_date', 'supplier', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'buying_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Why stock was corrected by hand, keyed by the value the adjustment form sends.
     */
    public static function adjustmentReasons(): array
    {
        return [
            'mistake' => __('Entered by mistake'),
            'count' => __('Stock count'),
            'damaged' => __('Damaged'),
            'expired' => __('Expired'),
            'lost' => __('Lost or stolen'),
            'other' => __('Other'),
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
