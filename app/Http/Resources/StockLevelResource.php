<?php

namespace App\Http\Resources;

use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item in one warehouse: how much is there, and whether that's at or below its reorder level.
 *
 * @mixin StockLevel
 */
class StockLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item_code' => $this->product->item_code,
            'item_name' => $this->product->item_name,
            'warehouse' => $this->warehouse->name,
            'qty' => (float) $this->qty,
            'reorder_level' => $this->reorder_level === null ? null : (float) $this->reorder_level,
            'reorder_qty' => $this->reorder_qty === null ? null : (float) $this->reorder_qty,
            'low' => $this->isLow(),
        ];
    }
}
