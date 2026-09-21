<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockService
{
    /** Find an item by barcode, or by item code (ignoring case and stray spaces). */
    public function findProduct(string $code): Product
    {
        $code = trim($code);

        $product = Product::query()
            ->where('barcode', $code)
            ->orWhereRaw('lower(item_code) = ?', [mb_strtolower($code)])
            ->first();

        return $product ?? throw ApiException::notFound("No item found for {$code}.");
    }

    public function findWarehouse(string $name): Warehouse
    {
        return Warehouse::query()->where('name', trim($name))->first()
            ?? throw ApiException::notFound("Warehouse {$name} does not exist.");
    }

    /**
     * Add stock (positive change) or remove it (negative), and write the audit line, atomically.
     *
     * The stock row is locked with SELECT ... FOR UPDATE while it changes, so two people scanning at
     * the same moment can't both take the last unit: the second one waits, then sees the real number.
     * Returns the audit line it wrote, which holds the change and the resulting quantity.
     *
     * A row that doesn't exist yet (first stock of an item in a warehouse) is created first with
     * INSERT ... ON CONFLICT DO NOTHING, so two first-time callers can't collide either.
     *
     * @throws ApiException when the change would take the stock below zero
     */
    public function adjust(?User $user, Product $product, Warehouse $warehouse, string|float|int $change, ?string $note = null): StockMovement
    {
        $change = $this->decimal($change);

        return DB::transaction(function () use ($user, $product, $warehouse, $change, $note) {
            DB::table('stock_levels')->insertOrIgnore([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'qty' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $level = StockLevel::query()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->firstOrFail();

            $after = bcadd((string) $level->qty, $change, 3);

            if (bccomp($after, '0', 3) < 0) {
                throw ApiException::insufficientStock(sprintf(
                    'Not enough stock: %s has %s in %s, cannot remove %s.',
                    $product->item_name,
                    $this->trim($level->qty),
                    $warehouse->name,
                    $this->trim(ltrim($change, '-')),
                ));
            }

            $level->qty = $after;
            $level->save();

            return StockMovement::query()->create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'user_id' => $user?->id,
                'qty_change' => $change,
                'qty_after' => $after,
                'note' => $note,
            ]);
        });
    }

    /** "12.500" -> "12.5", "3.000" -> "3", for messages people read. */
    private function trim(string|float|int $number): string
    {
        return rtrim(rtrim(number_format((float) $number, 3, '.', ''), '0'), '.');
    }

    /** A plain fixed-point string, so the arithmetic is exact (no float rounding surprises). */
    private function decimal(string|float|int $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
