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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
