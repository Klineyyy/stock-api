<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Sign in and get a JSON Web Token. Send it on every other request as `Authorization: Bearer <token>`.
 */
class AuthController extends Controller
{
    /**
     * Create an account
     *
     * New accounts are read-only viewers; an admin has to promote them.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->validated());
        $user->role = Role::Viewer;
        $user->save();

        return $this->token(auth('api')->login($user), 201);
    }

    /**
     * Sign in
     *
     * With email and password. Limited to 5 attempts a minute per email and address.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = auth('api')->attempt($request->validated());

        if (! $token) {
            throw new ApiException('Invalid email or password.', 401, 'invalid_credentials');
        }

        return $this->token($token);
    }

    /**
     * Who am I
     *
     * The signed-in user and their role.
     */
    public function me(): UserResource
    {
        return new UserResource(auth('api')->user());
    }

    /**
     * Refresh the token
     *
     * Swaps the current token for a fresh one; the old one stops working. An expired token can
     * still be refreshed for up to two weeks.
     */
    public function refresh(): JsonResponse
    {
        return $this->token(auth('api')->refresh());
    }

    /**
     * Sign out
     *
     * The token is blacklisted and stops working immediately.
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Logged out.']);
    }

    private function token(string $token, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ], $status);
    }
}
