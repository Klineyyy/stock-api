<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a scanner wants after a scan: the item, its total, and the stock in every warehouse.
 * The same fields the Inventory Hub ERPNext API returns, so one app can talk to either.
 *
 * @mixin Product
 */
class ItemLookupResource extends JsonResource
{
    public function __construct($resource, private readonly string $scanned)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rows = $this->stockLevels
            ->sortBy(fn ($level) => $level->warehouse->name)
            ->values();

        return [
            'item_code' => $this->item_code,
            'item_name' => $this->item_name,
            'stock_uom' => $this->stock_uom,
            'scanned' => $this->scanned,
            'total_qty' => (float) $rows->sum(fn ($level) => (float) $level->qty),
            'low_stock' => $rows->contains(fn ($level) => $level->isLow()),
            'stock' => $rows->map(fn ($level) => [
                'warehouse' => $level->warehouse->name,
                'qty' => (float) $level->qty,
                'reorder_level' => $level->reorder_level === null ? null : (float) $level->reorder_level,
                'reorder_qty' => $level->reorder_qty === null ? null : (float) $level->reorder_qty,
                'low' => $level->isLow(),
            ])->all(),
        ];
    }
}
