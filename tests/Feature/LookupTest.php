<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\InteractsWithStock;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private function lookup(string $code): TestResponse
    {
        return $this->actingAs(User::factory()->viewer()->create(), 'api')->getJson('/api/v1/lookup?'.http_build_query(['code' => $code]));
    }

    public function test_a_barcode_returns_the_item_with_stock_in_every_warehouse(): void
    {
        $product = $this->product('PEN-BLU-12', '4800010000023', 'Ballpen Blue (box of 12)');
        $this->stock($product, $this->warehouse('Stores - DRC'), 32, 15, 40);
        $this->stock($product, $this->warehouse('Display Shelf - DRC'), 4, 6, 12);

        $this->lookup('4800010000023')->assertOk()->assertExactJson(['data' => [
            'item_code' => 'PEN-BLU-12',
            'item_name' => 'Ballpen Blue (box of 12)',
            'stock_uom' => 'Nos',
            'scanned' => '4800010000023',
            'total_qty' => 36,
            'low_stock' => true,
            'stock' => [
                ['warehouse' => 'Display Shelf - DRC', 'qty' => 4, 'reorder_level' => 6, 'reorder_qty' => 12, 'low' => true],
                ['warehouse' => 'Stores - DRC', 'qty' => 32, 'reorder_level' => 15, 'reorder_qty' => 40, 'low' => false],
            ],
        ]]);
    }

    public function test_an_item_code_works_too_ignoring_case_and_spaces(): void
    {
        $this->stock($this->product('BOND-A4'), $this->warehouse(), 45, 20, 60);

        $this->lookup('  bond-a4 ')->assertOk()
            ->assertJsonPath('data.item_code', 'BOND-A4')
            ->assertJsonPath('data.scanned', 'bond-a4')  // what was typed, echoed back, spaces removed by the trim middleware
            ->assertJsonPath('data.low_stock', false);
    }

    public function test_stock_exactly_at_the_reorder_level_counts_as_low(): void
    {
        $this->stock($this->product(), $this->warehouse(), 20, 20, 60);

        $this->lookup('4800010000016')->assertJsonPath('data.low_stock', true);
    }

    public function test_no_reorder_level_means_never_low(): void
    {
        $this->stock($this->product(), $this->warehouse(), 0);

        $this->lookup('4800010000016')->assertJsonPath('data.low_stock', false)->assertJsonPath('data.stock.0.reorder_level', null);
    }

    public function test_an_item_that_was_never_stocked_has_an_empty_stock_list(): void
    {
        $this->product();

        $this->lookup('4800010000016')->assertOk()->assertJsonPath('data.total_qty', 0)->assertJsonPath('data.stock', []);
    }

    public function test_an_unknown_barcode_is_a_404_with_the_scanned_value_in_the_message(): void
    {
        $this->lookup('999')->assertNotFound()->assertJson(['code' => 'not_found', 'message' => 'No item found for 999.']);
    }

    public function test_a_blank_code_is_a_validation_error(): void
    {
        $this->lookup('   ')->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/v1/lookup')->assertUnprocessable();
    }
}
