<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithStock;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), 'api');
    }

    public function test_products_are_listed_15_a_page_with_pagination_links(): void
    {
        Product::factory()->count(20)->create();

        $this->admin()->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonStructure(['links' => ['first', 'next'], 'data' => [['item_code', 'item_name', 'barcode', 'stock_uom']]]);
    }

    public function test_the_page_size_can_be_changed_but_not_beyond_100(): void
    {
        Product::factory()->count(5)->create();

        $this->admin()->getJson('/api/v1/products?per_page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->admin()->getJson('/api/v1/products?per_page=1000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_search_ignores_case_and_matches_code_name_or_barcode(): void
    {
        $this->product('BOND-A4', '4800010000016', 'Bond Paper A4 (ream)');
        $this->product('PEN-BLU', '4800010000023', 'Ballpen Blue');

        $codes = fn (string $query) => collect($this->admin()->getJson("/api/v1/products?{$query}")->assertOk()->json('data'))->pluck('item_code')->all();

        $this->assertSame(['BOND-A4'], $codes('search=bond'));
        $this->assertSame(['PEN-BLU'], $codes('search=BALLPEN'));
        $this->assertSame(['PEN-BLU'], $codes('search=0023'));
        $this->assertSame(['BOND-A4'], $codes('barcode=4800010000016'));
        $this->assertSame([], $codes('search=nothing-like-this'));
    }

    public function test_a_search_with_wildcard_characters_is_treated_literally(): void
    {
        $this->product('BOND-A4', '4800010000016');

        $this->admin()->getJson('/api/v1/products?search=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_product_is_shown_by_its_item_code(): void
    {
        $this->product('BOND-A4');

        $this->admin()->getJson('/api/v1/products/BOND-A4')->assertOk()->assertJsonPath('data.item_name', 'Bond Paper A4 (ream)');
        $this->admin()->getJson('/api/v1/products/NOPE')->assertNotFound()->assertJson(['code' => 'not_found']);
    }

    public function test_an_admin_can_create_a_product(): void
    {
        $this->admin()->postJson('/api/v1/products', ['item_code' => 'NB-80', 'item_name' => 'Notebook 80 Leaves', 'barcode' => '4800010000054'])
            ->assertCreated()
            ->assertJsonPath('data.item_code', 'NB-80')
            ->assertJsonPath('data.stock_uom', 'Nos');

        $this->assertDatabaseHas('products', ['item_code' => 'NB-80']);
    }

    public function test_creating_validates_and_refuses_duplicates(): void
    {
        $this->product('BOND-A4', '4800010000016');

        $this->admin()->postJson('/api/v1/products', [])->assertUnprocessable()->assertJsonValidationErrors(['item_code', 'item_name']);
        $this->admin()->postJson('/api/v1/products', ['item_code' => 'bad code!', 'item_name' => 'x'])->assertJsonValidationErrors(['item_code']);
        $this->admin()->postJson('/api/v1/products', ['item_code' => 'BOND-A4', 'item_name' => 'Again'])->assertJsonValidationErrors(['item_code']);
        $this->admin()->postJson('/api/v1/products', ['item_code' => 'NEW-1', 'item_name' => 'Again', 'barcode' => '4800010000016'])->assertJsonValidationErrors(['barcode']);
    }

    public function test_updating_changes_the_name_and_barcode_but_never_the_code(): void
    {
        $this->product('BOND-A4', '4800010000016');
        $this->product('PEN-BLU', '4800010000023');

        $this->admin()->patchJson('/api/v1/products/BOND-A4', ['item_name' => 'Bond Paper A4 (500 sheets)', 'item_code' => 'HACKED'])
            ->assertOk()
            ->assertJsonPath('data.item_name', 'Bond Paper A4 (500 sheets)')
            ->assertJsonPath('data.item_code', 'BOND-A4');

        // keeping its own barcode is fine; taking another product's is not
        $this->admin()->patchJson('/api/v1/products/BOND-A4', ['barcode' => '4800010000016'])->assertOk();
        $this->admin()->patchJson('/api/v1/products/BOND-A4', ['barcode' => '4800010000023'])->assertJsonValidationErrors('barcode');
    }

    public function test_a_product_with_no_history_can_be_deleted(): void
    {
        $this->product('BOND-A4');

        $this->admin()->deleteJson('/api/v1/products/BOND-A4')->assertNoContent();
        $this->assertDatabaseMissing('products', ['item_code' => 'BOND-A4']);
    }

    public function test_a_product_with_stock_cannot_be_deleted(): void
    {
        $this->stock($this->product('BOND-A4'), $this->warehouse(), 5);

        $this->admin()->deleteJson('/api/v1/products/BOND-A4')
            ->assertStatus(409)
            ->assertJson(['code' => 'conflict']);
        $this->assertDatabaseHas('products', ['item_code' => 'BOND-A4']);
    }
}
