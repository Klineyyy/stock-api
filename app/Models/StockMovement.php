<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the audit trail: who changed how much, and what was left. Never edited. */
#[Fillable(['product_id', 'warehouse_id', 'user_id', 'qty_change', 'qty_after', 'note'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'qty_change' => 'decimal:3',
            'qty_after' => 'decimal:3',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
