<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const METHODS = ['cash', 'mobile', 'bank', 'credit'];

    protected $fillable = [
        'invoice_no', 'user_id', 'customer_id', 'payment_method',
        'total', 'cost_total', 'amount_paid', 'due_date', 'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'due_date' => 'date',
            'sold_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereColumn('amount_paid', '<', 'total');
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function isPaid(): bool
    {
        return $this->balance() <= 0;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date !== null && $this->due_date->lt(today());
    }

    public function profit(): float
    {
        return round((float) $this->total - (float) $this->cost_total, 2);
    }

    public static function nextInvoiceNo(): string
    {
        $prefix = 'INV-'.now()->format('ymd').'-';
        $last = static::where('invoice_no', 'like', $prefix.'%')->orderByDesc('invoice_no')->value('invoice_no');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
