<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStock;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use InteractsWithStock;
    use RefreshDatabase;

    private const RANK = ['viewer' => 0, 'staff' => 1, 'admin' => 2];

    /** Every endpoint, and the lowest role that may use it. */
    private static function endpoints(): array
    {
        return [
            'read summary' => ['GET', '/api/v1/summary', [], 'viewer'],
            'read products' => ['GET', '/api/v1/products', [], 'viewer'],
            'read stock' => ['GET', '/api/v1/stock', [], 'viewer'],
            'read low stock' => ['GET', '/api/v1/stock/low', [], 'viewer'],
            'read movements' => ['GET', '/api/v1/movements', [], 'viewer'],
            'read warehouses' => ['GET', '/api/v1/warehouses', [], 'viewer'],
            'adjust stock' => ['POST', '/api/v1/stock/adjust', ['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'qty' => 1], 'staff'],
            'create product' => ['POST', '/api/v1/products', ['item_code' => 'NEW-1', 'item_name' => 'New thing'], 'admin'],
            'update product' => ['PATCH', '/api/v1/products/BOND-A4', ['item_name' => 'Renamed'], 'admin'],
            'delete product' => ['DELETE', '/api/v1/products/BOND-A4', [], 'admin'],
            'create warehouse' => ['POST', '/api/v1/warehouses', ['name' => 'Annex'], 'admin'],
            'set reorder level' => ['PUT', '/api/v1/stock/reorder', ['item_code' => 'BOND-A4', 'warehouse' => 'Stores - DRC', 'reorder_level' => 5, 'reorder_qty' => 10], 'admin'],
        ];
    }

    /** One test case per endpoint and role: 12 endpoints x 3 roles. */
    public static function everyEndpointForEveryRole(): array
    {
        $cases = [];
        foreach (self::endpoints() as $name => [$method, $uri, $body, $lowest]) {
            foreach (self::RANK as $role => $rank) {
                $cases["{$role} / {$name}"] = [$role, $method, $uri, $body, $rank >= self::RANK[$lowest]];
            }
        }

        return $cases;
    }

    public static function everyEndpoint(): array
    {
        return array_map(fn ($e) => array_slice($e, 0, 3), self::endpoints());
    }

    #[DataProvider('everyEndpointForEveryRole')]
    public function test_each_role_gets_exactly_the_access_it_should(string $role, string $method, string $uri, array $body, bool $allowed): void
    {
        $this->stock($this->product(), $this->warehouse(), 5);

        $response = $this->actingAs(User::factory()->{$role}()->create(), 'api')->json($method, $uri, $body);

        if ($allowed) {
            $this->assertNotContains($response->status(), [401, 403], "{$role} should be allowed to {$method} {$uri}");
        } else {
            $response->assertForbidden()->assertJson(['code' => 'forbidden']);
        }
    }

    #[DataProvider('everyEndpoint')]
    public function test_nothing_is_open_to_anonymous_callers(string $method, string $uri, array $body): void
    {
        $this->json($method, $uri, $body)->assertUnauthorized()->assertJson(['code' => 'unauthenticated']);
    }

    public function test_demoting_someone_takes_effect_at_once_even_with_their_old_token(): void
    {
        $this->stock($this->product(), $this->warehouse(), 5);
        $admin = User::factory()->admin()->create(['email' => 'boss@example.com']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'boss@example.com', 'password' => 'password'])->json('access_token');
        $this->withToken($token)->postJson('/api/v1/warehouses', ['name' => 'Annex'])->assertCreated();

        $admin->forceFill(['role' => 'viewer'])->save(); // role isn't mass-assignable, on purpose

        // the token still says "admin", but what counts is the role in the database
        $this->newRequestCycle();
        $this->withToken($token)->postJson('/api/v1/warehouses', ['name' => 'Annex 2'])->assertForbidden();
        $this->newRequestCycle();
        $this->withToken($token)->getJson('/api/v1/summary')->assertOk();
    }
}
