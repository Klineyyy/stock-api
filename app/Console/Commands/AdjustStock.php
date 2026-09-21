<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Services\StockService;
use Illuminate\Console\Command;

class AdjustStock extends Command
{
    protected $signature = 'stock:adjust {item : Item code or barcode} {warehouse : Warehouse name} {qty : Units to add (positive) or remove (negative)} {--note= : A note for the audit log}';

    protected $description = 'Add or remove stock from the command line (uses the same code path as the API)';

    public function handle(StockService $stock): int
    {
        try {
            $movement = $stock->adjust(
                null,
                $stock->findProduct($this->argument('item')),
                $stock->findWarehouse($this->argument('warehouse')),
                $this->argument('qty'),
                $this->option('note'),
            );
        } catch (ApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line((string) (float) $movement->qty_after);

        return self::SUCCESS;
    }
}
