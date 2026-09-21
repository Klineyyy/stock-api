<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockMovement */
class MovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_code' => $this->product->item_code,
            'item_name' => $this->product->item_name,
            'warehouse' => $this->warehouse->name,
            'qty_change' => (float) $this->qty_change,
            'qty_after' => (float) $this->qty_after,
            'note' => $this->note,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
