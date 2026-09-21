<?php

/*
 * One racing client for tests/Feature/ConcurrencyTest.php, run as its own PHP process.
 *
 *   php tests/Support/adjust-worker.php <item> <warehouse> <change> <start-at>
 *
 * It boots the app, waits until <start-at> (a unix time with decimals) so that all workers fire together,
 * then adjusts stock through StockService, exactly as the API does. Prints "ok <qty after>" or "refused".
 *
 * After every SELECT it pauses for a moment. With the row locked that only makes the workers queue up
 * (they take turns); without the lock it would make every one of them read the same stale quantity, so
 * the test fails every time instead of once in a hundred runs.
 */

use App\Exceptions\ApiException;
use App\Services\StockService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $item, $warehouse, $change, $startAt] = $argv;

$database = (string) config('database.connections.'.config('database.default').'.database');
if (! str_ends_with($database, '_test')) {
    fwrite(STDERR, "Refusing to run against \"{$database}\": not a test database.\n");
    exit(2);
}

$stock = app(StockService::class);
$product = $stock->findProduct($item);
$where = $stock->findWarehouse($warehouse);

DB::listen(function ($query) {
    if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'stock_levels')) {
        usleep(60_000);
    }
});

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    echo 'ok '.(float) $stock->adjust(null, $product, $where, $change)->qty_after."\n";
} catch (ApiException) {
    echo "refused\n";
    exit(1);
}
