<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item_code' => $this->item_code,
            'item_name' => $this->item_name,
            'barcode' => $this->barcode,
            'stock_uom' => $this->stock_uom,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
