<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MovementResource;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MovementController extends Controller
{
    /**
     * List the audit trail
     *
     * Every stock change with who made it, newest first, 15 a page (`per_page` up to 100).
     *
     * @queryParam item_code Only this item.
     * @queryParam warehouse Only this warehouse (its exact name).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'item_code' => ['nullable', 'string', 'max:64'],
            'warehouse' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $movements = StockMovement::query()
            ->with(['product', 'warehouse', 'user'])
            ->when($request->query('item_code'), fn ($q, $code) => $q->whereHas('product', fn ($p) => $p->where('item_code', $code)))
            ->when($request->query('warehouse'), fn ($q, $name) => $q->whereHas('warehouse', fn ($w) => $w->where('name', $name)))
            ->latest('id')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return MovementResource::collection($movements);
    }
}
