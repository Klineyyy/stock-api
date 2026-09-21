<?php

namespace Tests\Support;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Warehouse;

trait InteractsWithStock
{
    /** A product with a known code and barcode, so tests can look it up. */
    protected function product(string $code = 'BOND-A4', string $barcode = '4800010000016', string $name = 'Bond Paper A4 (ream)'): Product
    {
        return Product::factory()->create(['item_code' => $code, 'item_name' => $name, 'barcode' => $barcode]);
    }

    protected function warehouse(string $name = 'Stores - DRC'): Warehouse
    {
        return Warehouse::query()->firstOrCreate(['name' => $name]);
    }

    /** Put `$qty` of a product into a warehouse, with an optional reorder level. */
    protected function stock(Product $product, Warehouse $warehouse, float|int $qty, float|int|null $reorderLevel = null, float|int|null $reorderQty = null): StockLevel
    {
        return StockLevel::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty' => $qty,
            'reorder_level' => $reorderLevel,
            'reorder_qty' => $reorderQty,
        ]);
    }
}
