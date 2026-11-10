<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'address'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Adds a `balance` attribute: total still owed across all sales.
     */
    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->withSum('sales as sales_total', 'total')
            ->withSum('sales as sales_paid', 'amount_paid');
    }

    public function getBalanceAttribute(): float
    {
        if (array_key_exists('sales_total', $this->attributes)) {
            return round((float) $this->sales_total - (float) $this->sales_paid, 2);
        }

        return round((float) $this->sales()->sum('total') - (float) $this->sales()->sum('amount_paid'), 2);
    }
}
