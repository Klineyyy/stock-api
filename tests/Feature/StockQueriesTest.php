<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\InteractsWithStock;
use Tests\TestCase;

class StockQueriesTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private function api(string $uri): TestResponse
    {
        return $this->actingAs(User::factory()->viewer()->create(), 'api')->getJson($uri);
    }

    /** Bond: 45 (ok) in Stores, 4 (low) on the shelf; Alcohol: 3 (low); Glue: 50 with no level (never low). */
    private function catalogue(): void
    {
        $stores = $this->warehouse('Stores - DRC');
        $shelf = $this->warehouse('Display Shelf - DRC');
        $bond = $this->product('BOND-A4', '4800010000016', 'Bond Paper A4 (ream)');
        $alcohol = $this->product('ALC-70-500', '4800010000122', 'Alcohol 70% 500ml');
        $glue = $this->product('GLUE-STK', '4800010000139', 'Glue Stick');

        $this->stock($bond, $stores, 45, 20, 60);
        $this->stock($bond, $shelf, 4, 6, 12);
        $this->stock($alcohol, $stores, 3, 10, 30);
        $this->stock($glue, $stores, 50);
    }

    public function test_summary_counts_items_units_and_low_stock(): void
    {
        $this->catalogue();

        $this->api('/api/v1/summary')->assertOk()->assertExactJson(['data' => ['items' => 3, 'units_on_hand' => 102, 'low_stock' => 2]]);
    }

    public function test_summary_of_an_empty_system_is_zeros(): void
    {
        $this->api('/api/v1/summary')->assertOk()->assertExactJson(['data' => ['items' => 0, 'units_on_hand' => 0, 'low_stock' => 0]]);
    }

    public function test_the_low_stock_list_has_only_low_rows_worst_first(): void
    {
        $this->catalogue();

        $rows = $this->api('/api/v1/stock/low')->assertOk()->json('data');

        // alcohol is at 30% of its reorder level, the shelf's bond paper at 67%
        $this->assertSame(['ALC-70-500', 'BOND-A4'], array_column($rows, 'item_code'));
        $this->assertSame(['Stores - DRC', 'Display Shelf - DRC'], array_column($rows, 'warehouse'));
        $this->assertSame([true, true], array_column($rows, 'low'));
    }

    public function test_stock_is_listed_per_warehouse_and_can_be_filtered(): void
    {
        $this->catalogue();

        $this->api('/api/v1/stock')->assertOk()->assertJsonCount(4, 'data')->assertJsonPath('meta.total', 4);

        $this->api('/api/v1/stock?warehouse='.urlencode('Display Shelf - DRC'))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.item_code', 'BOND-A4')->assertJsonPath('data.0.qty', 4);

        $this->api('/api/v1/stock?low=1')->assertOk()->assertJsonCount(2, 'data');
        $this->api('/api/v1/stock?search=glue')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.item_code', 'GLUE-STK');
        $this->api('/api/v1/stock?low=1&search=bond')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.warehouse', 'Display Shelf - DRC');
    }

    public function test_stock_rows_have_the_documented_shape(): void
    {
        $this->catalogue();

        $this->api('/api/v1/stock?search=alcohol')->assertOk()->assertJsonPath('data.0', [
            'item_code' => 'ALC-70-500',
            'item_name' => 'Alcohol 70% 500ml',
            'warehouse' => 'Stores - DRC',
            'qty' => 3,
            'reorder_level' => 10,
            'reorder_qty' => 30,
            'low' => true,
        ]);
    }

    public function test_stock_paginates_and_rejects_a_page_size_beyond_100(): void
    {
        $this->catalogue();

        $this->api('/api/v1/stock?per_page=2&page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
        $this->api('/api/v1/stock?per_page=101')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
    }

    public function test_the_audit_trail_is_newest_first_and_can_be_filtered(): void
    {
        $this->catalogue();
        $service = app(StockService::class);
        $user = User::factory()->staff()->create(['name' => 'Sam Staff']);

        $service->adjust($user, $service->findProduct('BOND-A4'), $service->findWarehouse('Stores - DRC'), -5, 'Sold');
        $service->adjust(null, $service->findProduct('GLUE-STK'), $service->findWarehouse('Stores - DRC'), 10);

        $rows = $this->api('/api/v1/movements')->assertOk()->json('data');
        $this->assertSame(['GLUE-STK', 'BOND-A4'], array_column($rows, 'item_code'));
        $this->assertSame(['id' => $user->id, 'name' => 'Sam Staff'], $rows[1]['user']);
        $this->assertNull($rows[0]['user'], 'a change made from the command line has no user');
        $this->assertSame(['qty_change' => -5, 'qty_after' => 40, 'note' => 'Sold'], array_intersect_key($rows[1], array_flip(['qty_change', 'qty_after', 'note'])));

        $this->api('/api/v1/movements?item_code=BOND-A4')->assertOk()->assertJsonCount(1, 'data');
        $this->api('/api/v1/movements?warehouse='.urlencode('Display Shelf - DRC'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_warehouses_are_listed(): void
    {
        $this->catalogue();

        $this->api('/api/v1/warehouses')->assertOk()->assertJsonCount(2, 'data');
    }
}
