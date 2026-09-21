<?php

namespace App\Models;

use Database\Factories\StockLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'warehouse_id', 'qty', 'reorder_level', 'reorder_qty'])]
class StockLevel extends Model
{
    /** @use HasFactory<StockLevelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** At or below the reorder level. Rows with no reorder level are never "low". */
    public function scopeLow(Builder $query): Builder
    {
        return $query->whereNotNull('reorder_level')->whereColumn('qty', '<=', 'reorder_level');
    }

    public function isLow(): bool
    {
        return $this->reorder_level !== null && bccomp((string) $this->qty, (string) $this->reorder_level, 3) <= 0;
    }
}
