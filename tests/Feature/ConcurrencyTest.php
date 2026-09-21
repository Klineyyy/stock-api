<?php

namespace Tests\Feature;

use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Tests\Support\InteractsWithStock;
use Tests\TestCase;

/**
 * Real parallel clients against real PostgreSQL. The transaction that RefreshDatabase wraps a test in
 * can't be seen from another process, so these tests commit (DatabaseMigrations) and start ten PHP
 * processes that all fire at the same instant.
 */
class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithStock;

    private const WORKERS = 10;

    /**
     * Run WORKERS processes at once, each adjusting BOND-A4 in Stores by $change.
     *
     * @return list<ProcessResult>
     */
    private function race(string $change): array
    {
        $startAt = microtime(true) + 4; // enough for every process to boot and then wait for the others

        $results = Process::pool(function (Pool $pool) use ($change, $startAt) {
            foreach (range(1, self::WORKERS) as $i) {
                $pool->as("worker-{$i}")
                    ->path(base_path())
                    ->timeout(60)
                    ->env([
                        'APP_ENV' => 'testing',
                        'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
                        'CACHE_STORE' => 'array',
                    ])
                    ->command([PHP_BINARY, 'tests/Support/adjust-worker.php', 'BOND-A4', 'Stores - DRC', $change, (string) $startAt]);
            }
        })->start()->wait();

        return array_values($results->collect()->all());
    }

    private function outcomes(array $results): array
    {
        $ok = array_filter($results, fn (ProcessResult $r) => $r->successful() && str_starts_with($r->output(), 'ok'));
        $refused = array_filter($results, fn (ProcessResult $r) => trim($r->output()) === 'refused');

        // anything else is a crash, and a crash must fail the test loudly rather than count as "refused"
        foreach ($results as $r) {
            $this->assertTrue($r->successful() || trim($r->output()) === 'refused', "worker crashed: {$r->errorOutput()}{$r->output()}");
        }

        return [count($ok), count($refused)];
    }

    public function test_ten_people_taking_the_last_three_units_get_exactly_three(): void
    {
        $this->stock($this->product(), $this->warehouse(), 3);

        [$succeeded, $refused] = $this->outcomes($this->race('-1'));

        $this->assertSame(3, $succeeded, 'exactly the three units on hand can be taken');
        $this->assertSame(7, $refused);
        $this->assertEquals(0, StockLevel::query()->sole()->qty, 'stock must end at zero, never negative');
        $this->assertSame(3, StockMovement::query()->count(), 'only the three real changes are in the audit trail');
    }

    public function test_ten_simultaneous_additions_are_all_counted(): void
    {
        $this->stock($this->product(), $this->warehouse(), 0);

        [$succeeded] = $this->outcomes($this->race('1'));

        $this->assertSame(10, $succeeded);
        $this->assertEquals(10, StockLevel::query()->sole()->qty, 'no addition may be lost');

        // each change saw the result of the one before it: after-quantities are exactly 1..10, no repeats
        $this->assertEquals(range(1, 10), StockMovement::query()->orderBy('id')->pluck('qty_after')->map(fn ($q) => (float) $q)->all());
    }

    public function test_ten_first_ever_additions_to_a_warehouse_create_one_row_and_lose_none(): void
    {
        $this->product();
        $this->warehouse(); // note: no stock row exists yet, so the workers race to create it

        [$succeeded] = $this->outcomes($this->race('1'));

        $this->assertSame(10, $succeeded, 'no worker may fail on a duplicate-row collision');
        $this->assertSame(1, StockLevel::query()->count());
        $this->assertEquals(10, StockLevel::query()->sole()->qty);
    }
}
