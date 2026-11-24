<?php

namespace App\Models;

use App\Support\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
     * Receive goods: record the delivery in the stock history and add it to the stock.
     * The delivery's prices become the product's prices.
     */
    public function receiveStock(float $quantity, float $buyingPrice, float $sellingPrice, ?string $expiryDate, ?User $user, ?string $supplier = null, ?string $note = null): void
    {
        $this->stockEntries()->create([
            'user_id' => $user?->id,
            'quantity' => $quantity,
            'buying_price' => $buyingPrice,
            'selling_price' => $sellingPrice,
            'expiry_date' => $expiryDate,
            'supplier' => $supplier,
            'note' => $note,
        ]);

        // Keep the earliest expiry while older stock is still on the shelf,
        // so near-expiry alerts are not hidden by a fresher batch.
        if (! empty($expiryDate)) {
            $newExpiry = Carbon::parse($expiryDate);
            $hasOldStock = (float) $this->quantity > 0 && $this->expiry_date !== null;
            $this->expiry_date = $hasOldStock && $this->expiry_date->lt($newExpiry)
                ? $this->expiry_date
                : $newExpiry;
        }

        $this->quantity = round((float) $this->quantity + $quantity, 2);
        $this->buying_price = $buyingPrice;
        $this->selling_price = $sellingPrice;
        $this->save();
    }

    /**
     * Set the stock to a counted quantity and record the difference in the stock
     * history, so hand corrections are never silent.
     * Returns false when the quantity did not change.
     */
    public function adjustStock(float $quantity, ?User $user, string $note): bool
    {
        $quantity = round($quantity, 2);
        $difference = round($quantity - (float) $this->quantity, 2);

        if ($difference == 0) {
            return false;
        }

        $this->stockEntries()->create([
            'user_id' => $user?->id,
            'quantity' => $difference,
            'buying_price' => $this->buying_price,
            'selling_price' => $this->selling_price,
            'note' => $note,
        ]);

        $this->quantity = $quantity;
        $this->save();

        return true;
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
     * Find the product for a scanned barcode. Tolerates what scanners and spreadsheets do
     * to codes: letter case, and leading zeros (UPC-A 12 digits vs EAN-13 "0…" vs GTIN-14
     * "00…", or zeros Excel dropped). An exact match always wins.
     */
    public static function findByBarcode(string $code): ?self
    {
        $code = mb_strtolower(trim($code));
        if ($code === '') {
            return null;
        }

        $candidates = [$code];
        if (ctype_digit($code)) {
            $bare = ltrim($code, '0');
            if ($bare !== '') {
                array_push($candidates, $bare, '0'.$bare, '00'.$bare);
            }
        }
        $candidates = array_values(array_unique($candidates));

        $matches = static::query()->whereIn(DB::raw('lower(sku)'), $candidates)->get();

        return $matches->first(fn (self $p) => mb_strtolower($p->sku) === $code)
            ?? $matches->sortBy(fn (self $p) => array_search(mb_strtolower($p->sku), $candidates, true))->first();
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
