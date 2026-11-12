<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    public const COLORS = ['emerald', 'sky', 'amber', 'rose', 'violet', 'slate', 'orange', 'teal'];

    protected $fillable = ['name', 'color'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Ids of the tags with the given names, creating any that do not exist yet.
     *
     * @param  iterable<string>  $names
     * @return list<int>
     */
    public static function idsForNames(iterable $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = mb_strtolower(trim((string) $name));
            if ($name !== '') {
                $ids[] = static::firstOrCreate(['name' => $name], ['color' => self::COLORS[array_rand(self::COLORS)]])->id;
            }
        }

        return array_values(array_unique($ids));
    }
}
