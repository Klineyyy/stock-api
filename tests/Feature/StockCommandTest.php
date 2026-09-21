<?php

namespace Tests\Feature;

use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithStock;
use Tests\TestCase;

class StockCommandTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    public function test_it_adds_and_removes_stock_and_prints_the_new_quantity(): void
    {
        $this->stock($this->product(), $this->warehouse(), 10);

        $this->artisan('stock:adjust', ['item' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => '5'])->expectsOutput('15')->assertSuccessful();
        $this->artisan('stock:adjust', ['item' => '4800010000016', 'warehouse' => 'Stores - DRC', 'qty' => '-12'])->expectsOutput('3')->assertSuccessful();
    }

    public function test_it_fails_with_a_clear_message_instead_of_going_negative(): void
    {
        $this->stock($this->product(), $this->warehouse(), 1);

        $this->artisan('stock:adjust', ['item' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => '-2'])
            ->expectsOutputToContain('Not enough stock')
            ->assertFailed();

        $this->assertEquals(1, StockLevel::query()->sole()->qty);
    }

    public function test_it_fails_for_an_unknown_item(): void
    {
        $this->warehouse();

        $this->artisan('stock:adjust', ['item' => 'NOPE', 'warehouse' => 'Stores - DRC', 'qty' => '1'])->expectsOutputToContain('No item found for NOPE.')->assertFailed();
    }
}
