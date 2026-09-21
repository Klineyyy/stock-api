<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Database\Seeder;

/**
 * Demo data: three users (one per role), two warehouses, and the same 12 items, barcodes and
 * quantities as the Inventory Hub and Stock Scanner demos, so a barcode answers the same everywhere.
 * Safe to run twice: anything that already exists is left alone.
 */
class DatabaseSeeder extends Seeder
{
    private const STORES = 'Stores - DRC';

    private const SHELF = 'Display Shelf - DRC';

    /** code, name, barcode, [warehouse => [qty, reorder level, reorder qty]] */
    private function catalogue(): array
    {
        return [
            ['BOND-A4', 'Bond Paper A4 (ream)', '4800010000016', [self::STORES => [45, 20, 60]]],
            ['PEN-BLU-12', 'Ballpen Blue (box of 12)', '4800010000023', [self::STORES => [32, 15, 40], self::SHELF => [4, 6, 12]]],
            ['PEN-BLK-12', 'Ballpen Black (box of 12)', '4800010000030', [self::STORES => [6, 15, 40]]],
            ['MRK-BLK', 'Marker Permanent Black', '4800010000047', [self::STORES => [18, 24, 48]]],
            ['NB-80', 'Notebook 80 Leaves', '4800010000054', [self::STORES => [120, 30, 100], self::SHELF => [12, 5, 12]]],
            ['STP-35', 'Stapler No. 35', '4800010000061', [self::STORES => [9, 5, 12]]],
            ['STPW-35', 'Staple Wire No. 35 (box)', '4800010000078', [self::STORES => [50, 20, 60]]],
            ['TAPE-MSK', 'Masking Tape 1 inch', '4800010000085', [self::STORES => [4, 12, 36]]],
            ['ENV-L50', 'Envelope Long (pack of 50)', '4800010000092', [self::STORES => [22, 10, 30]]],
            ['HL-YEL', 'Highlighter Yellow', '4800010000108', [self::STORES => [40, 20, 60], self::SHELF => [15, 6, 12]]],
            ['CT-5MM', 'Correction Tape 5mm', '4800010000115', [self::STORES => [30, 12, 36]]],
            ['ALC-500', 'Alcohol 70% 500ml', '4800010000122', [self::STORES => [3, 10, 24]]],
        ];
    }

    public function run(StockService $stock): void
    {
        foreach ([[Role::Admin, 'Demo Admin', 'admin@example.com'], [Role::Staff, 'Demo Staff', 'staff@example.com'], [Role::Viewer, 'Demo Viewer', 'viewer@example.com']] as [$role, $name, $email]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            if (! $user->exists) {
                $user->fill(['name' => $name, 'password' => 'password']);
                $user->role = $role;
                $user->save();
            }
        }

        $warehouses = collect([self::STORES, self::SHELF])
            ->mapWithKeys(fn (string $name) => [$name => Warehouse::query()->firstOrCreate(['name' => $name])]);

        foreach ($this->catalogue() as [$code, $name, $barcode, $levels]) {
            $product = Product::query()->firstOrCreate(['item_code' => $code], ['item_name' => $name, 'barcode' => $barcode]);

            foreach ($levels as $warehouseName => [$qty, $reorderLevel, $reorderQty]) {
                $warehouse = $warehouses[$warehouseName];

                if (StockLevel::query()->where(['product_id' => $product->id, 'warehouse_id' => $warehouse->id])->exists()) {
                    continue;
                }

                // Go through the service so every unit has an "Opening stock" line in the audit trail.
                $stock->adjust(null, $product, $warehouse, $qty, 'Opening stock');
                StockLevel::query()
                    ->where(['product_id' => $product->id, 'warehouse_id' => $warehouse->id])
                    ->update(['reorder_level' => $reorderLevel, 'reorder_qty' => $reorderQty]);
            }
        }
    }
}
