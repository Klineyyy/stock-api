<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['item_code', 'item_name', 'barcode', 'stock_uom'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** URLs use the item code (/products/BOND-A4), not the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'item_code';
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Matches part of the code, name or barcode, ignoring case. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($term)).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('item_code', 'ilike', $like)
            ->orWhere('item_name', 'ilike', $like)
            ->orWhere('barcode', 'ilike', $like));
    }
}
