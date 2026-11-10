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
}
