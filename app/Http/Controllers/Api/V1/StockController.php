<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Resources\StockLevelResource;
use App\Models\StockLevel;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    /**
     * List stock levels
     *
     * One row per item and warehouse, 15 a page (`per_page` up to 100).
     *
     * @queryParam warehouse Only this warehouse (its exact name).
     * @queryParam low Send `1` to see only rows at or below their reorder level.
     * @queryParam search Part of the item code, name or barcode.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'warehouse' => ['nullable', 'string', 'max:100'],
            'low' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $levels = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->when($request->query('warehouse'), fn ($q, $name) => $q->whereHas('warehouse', fn ($w) => $w->where('name', $name)))
            ->when($request->boolean('low'), fn ($q) => $q->low())
            ->when($request->query('search'), fn ($q, $term) => $q->whereHas('product', fn ($p) => $p->search($term)))
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->orderBy('products.item_code')
            ->select('stock_levels.*')
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        return StockLevelResource::collection($levels);
    }

    /**
     * List low stock
     *
     * Everything at or below its reorder level, worst first (the furthest below it).
     */
    public function low(): AnonymousResourceCollection
    {
        $levels = StockLevel::query()
            ->with(['product', 'warehouse'])
            ->low()
            ->orderByRaw('qty / NULLIF(reorder_level, 0) asc')
            ->limit(200)
            ->get();

        return StockLevelResource::collection($levels);
    }

    /**
     * Add or remove stock
     *
     * For staff and admins. A positive `qty` adds, a negative one removes.
     *
     * Removing more than is on hand answers 422 with `code: insufficient_stock` and changes nothing.
     * Every change is written to the audit trail with who made it.
     */
    public function adjust(AdjustStockRequest $request, StockService $stock): JsonResponse
    {
        $product = $stock->findProduct($request->validated('item_code'));
        $warehouse = $stock->findWarehouse($request->validated('warehouse'));

        $movement = $stock->adjust($request->user('api'), $product, $warehouse, $request->validated('qty'), $request->validated('note'));

        return response()->json(['data' => [
            'item_code' => $product->item_code,
            'warehouse' => $warehouse->name,
            'change' => (float) $request->validated('qty'),
            'qty' => (float) $movement->qty_after,
            'movement_id' => $movement->id,
        ]], 201);
    }

    /**
     * Set the reorder level
     *
     * For admins. Sets, or clears with `null`, the reorder level and quantity of an item in a warehouse.
     */
    public function reorder(ReorderRequest $request, StockService $stock): StockLevelResource
    {
        $product = $stock->findProduct($request->validated('item_code'));
        $warehouse = $stock->findWarehouse($request->validated('warehouse'));

        $level = StockLevel::query()->firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['qty' => 0],
        );
        $level->update([
            'reorder_level' => $request->validated('reorder_level'),
            'reorder_qty' => $request->validated('reorder_qty'),
        ]);

        return new StockLevelResource($level->load(['product', 'warehouse']));
    }
}
