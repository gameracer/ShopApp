<?php

namespace App\Models;

use App\Support\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'sku', 'unit', 'buying_price', 'selling_price',
        'quantity', 'reorder_level', 'expiry_date', 'description',
    ];

    protected function casts(): array
    {
        return [
            'buying_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'quantity' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }

    /**
     * Canonical form of a product name used to keep names unique:
     * trimmed, inner whitespace collapsed and lower-cased.
     */
    public static function normalizeName(?string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $name)));
    }

    /**
     * Whether another product already uses this name (compared via normalizeName,
     * so "Coca  Cola" and "coca cola" count as the same product).
     */
    public static function nameTaken(string $name, ?int $ignoreId = null): bool
    {
        $normalized = static::normalizeName($name);

        return static::query()
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->pluck('name')
            ->contains(fn ($existing) => static::normalizeName($existing) === $normalized);
    }

    /**
     * Search by name, SKU/barcode or tag name.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = '%'.$term.'%';
            $q->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like));
        });
    }

    /**
     * Only products carrying ALL the given tag ids.
     */
    public function scopeWithTags(Builder $query, array $tagIds): Builder
    {
        foreach (array_filter($tagIds) as $tagId) {
            $query->whereHas('tags', fn (Builder $t) => $t->whereKey($tagId));
        }

        return $query;
    }

    public function scopeNearExpiry(Builder $query, ?int $days = null): Builder
    {
        $days ??= Shop::expiryAlertDays();

        return $query->whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'reorder_level');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }

    public function daysToExpiry(): ?int
    {
        return $this->expiry_date === null ? null : (int) today()->diffInDays($this->expiry_date, false);
    }

    public function isLowStock(): bool
    {
        return (float) $this->quantity <= (float) $this->reorder_level;
    }
}
