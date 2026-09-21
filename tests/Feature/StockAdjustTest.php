<?php

namespace Tests\Feature;

use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStock;
use Tests\TestCase;

class StockAdjustTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->staff()->create();
    }

    private function adjust(array $body): TestResponse
    {
        return $this->actingAs($this->staff, 'api')->postJson('/api/v1/stock/adjust', $body);
    }

    public function test_adding_stock_changes_the_level_and_writes_an_audit_line(): void
    {
        $product = $this->product();
        $warehouse = $this->warehouse();
        $this->stock($product, $warehouse, 10);

        $response = $this->adjust(['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => 5, 'note' => 'Delivery'])
            ->assertCreated()
            ->assertJsonPath('data.item_code', 'BOND-A4')
            ->assertJsonPath('data.warehouse', 'Stores - DRC')
            ->assertJsonPath('data.change', 5)
            ->assertJsonPath('data.qty', 15);

        $this->assertEquals(15, StockLevel::query()->sole()->qty);
        $movement = StockMovement::query()->sole();
        $this->assertSame($movement->id, $response->json('data.movement_id'));
        $this->assertEquals(5, $movement->qty_change);
        $this->assertEquals(15, $movement->qty_after);
        $this->assertSame('Delivery', $movement->note);
        $this->assertSame($this->staff->id, $movement->user_id);
    }

    public function test_removing_stock_works_and_a_barcode_can_be_used_instead_of_a_code(): void
    {
        $this->stock($this->product(), $this->warehouse(), 10);

        $this->adjust(['item_code' => '4800010000016', 'warehouse' => 'Stores - DRC', 'qty' => -4])
            ->assertCreated()
            ->assertJsonPath('data.qty', 6);
    }

    public function test_removing_the_exact_amount_on_hand_leaves_zero(): void
    {
        $this->stock($this->product(), $this->warehouse(), 3);

        $this->adjust(['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => -3])->assertCreated()->assertJsonPath('data.qty', 0);
    }

    public function test_removing_more_than_is_on_hand_is_refused_and_changes_nothing(): void
    {
        $this->stock($this->product(), $this->warehouse(), 3);

        $this->adjust(['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => -4])
            ->assertUnprocessable()
            ->assertJson([
                'code' => 'insufficient_stock',
                'message' => 'Not enough stock: Bond Paper A4 (ream) has 3 in Stores - DRC, cannot remove 4.',
            ]);

        $this->assertEquals(3, StockLevel::query()->sole()->qty);
        $this->assertSame(0, StockMovement::query()->count(), 'a refused change must leave no audit line');
    }

    public function test_adding_to_a_warehouse_that_has_none_of_the_item_creates_the_row(): void
    {
        $this->product();
        $this->warehouse();

        $this->adjust(['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => 7])->assertCreated()->assertJsonPath('data.qty', 7);

        $this->assertSame(1, StockLevel::query()->count());
    }

    public function test_removing_from_a_warehouse_that_has_none_is_refused_without_leaving_a_row_behind(): void
    {
        $this->product();
        $this->warehouse();

        $this->adjust(['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => -1])->assertUnprocessable()->assertJsonPath('code', 'insufficient_stock');

        $this->assertSame(0, StockLevel::query()->count());
    }

    public function test_decimal_quantities_are_exact(): void
    {
        $this->stock($this->product(), $this->warehouse(), '0.100');

        // 0.1 + 0.2 is 0.30000000000000004 in floating point; here it must be exactly 0.3
        $this->adjust(['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => 0.2])->assertCreated()->assertJsonPath('data.qty', 0.3);

        $this->assertSame('0.300', StockLevel::query()->sole()->getRawOriginal('qty'));

        $this->adjust(['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => -0.3])->assertCreated()->assertJsonPath('data.qty', 0);
    }

    public function test_an_unknown_item_or_warehouse_is_a_404(): void
    {
        $this->product();
        $this->warehouse();

        $this->adjust(['item_code' => 'NOPE', 'warehouse' => 'Stores - DRC', 'qty' => 1])
            ->assertNotFound()->assertJson(['code' => 'not_found', 'message' => 'No item found for NOPE.']);
        $this->adjust(['item_code' => 'BOND-A4', 'warehouse' => 'Moon Base', 'qty' => 1])
            ->assertNotFound()->assertJson(['code' => 'not_found', 'message' => 'Warehouse Moon Base does not exist.']);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function badBodies(): array
    {
        $ok = ['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => 1];

        return [
            'no item' => [array_diff_key($ok, ['item_code' => 1]), 'item_code'],
            'no warehouse' => [array_diff_key($ok, ['warehouse' => 1]), 'warehouse'],
            'no qty' => [array_diff_key($ok, ['qty' => 1]), 'qty'],
            'qty is not a number' => [[...$ok, 'qty' => 'lots'], 'qty'],
            'qty is zero' => [[...$ok, 'qty' => 0], 'qty'],
            'qty has four decimals' => [[...$ok, 'qty' => 1.2345], 'qty'],
            'qty is absurdly large' => [[...$ok, 'qty' => 5_000_000_000], 'qty'],
            'note is too long' => [[...$ok, 'note' => str_repeat('x', 256)], 'note'],
        ];
    }

    #[DataProvider('badBodies')]
    public function test_bad_input_is_a_422_that_names_the_field(array $body, string $field): void
    {
        $this->stock($this->product(), $this->warehouse(), 10);

        $this->adjust($body)->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors([$field]);

        $this->assertSame(0, StockMovement::query()->count());
    }
}
