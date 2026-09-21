<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email = 'ana@example.com', string $password = 'password'): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_registering_creates_a_read_only_viewer_and_returns_a_working_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-pass'])
            ->assertCreated()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

        $this->assertSame('viewer', User::query()->firstWhere('email', 'ana@example.com')->role->value);

        $this->newRequestCycle();
        $this->withToken($response->json('access_token'))->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.role', 'viewer');
    }

    public function test_a_new_account_cannot_choose_its_own_role(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'secret-pass', 'role' => 'admin'])
            ->assertCreated();

        $this->assertSame(Role::Viewer, User::query()->firstWhere('email', 'eve@example.com')->role);
    }

    public function test_registering_validates_the_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', ['name' => '', 'email' => 'not-an-email', 'password' => 'short'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/api/v1/auth/register', ['name' => 'Dup', 'email' => 'taken@example.com', 'password' => 'secret-pass'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logging_in_returns_a_bearer_token(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->login()->assertOk()
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('expires_in', 3600);
    }

    public function test_a_wrong_password_is_a_401_with_a_stable_code(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->login('ana@example.com', 'wrong')->assertUnauthorized()
            ->assertJson(['code' => 'invalid_credentials', 'message' => 'Invalid email or password.']);
    }

    public function test_logging_in_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        foreach (range(1, 5) as $_) {
            $this->login('ana@example.com', 'wrong')->assertUnauthorized();
        }

        $this->login('ana@example.com', 'wrong')->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
        // even the right password has to wait
        $this->login('ana@example.com', 'password')->assertStatus(429);
    }

    public function test_protected_routes_need_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJson(['code' => 'unauthenticated']);
        $this->withToken('not.a.token')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_the_token_carries_the_role_but_permissions_come_from_the_database(): void
    {
        User::factory()->staff()->create(['email' => 'ana@example.com']);
        $token = $this->login()->json('access_token');

        $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
        $this->assertSame('staff', $payload['role']);
    }

    public function test_refreshing_swaps_the_token_and_the_old_one_stops_working(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $old = $this->login()->json('access_token');

        $new = $this->withToken($old)->postJson('/api/v1/auth/refresh')->assertOk()->json('access_token');

        $this->assertNotSame($old, $new);
        $this->newRequestCycle();
        $this->withToken($new)->getJson('/api/v1/auth/me')->assertOk();

        $this->newRequestCycle();
        $this->withToken($old)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logging_out_invalidates_the_token_immediately(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $token = $this->login()->json('access_token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk()->assertJson(['message' => 'Logged out.']);

        $this->newRequestCycle();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
