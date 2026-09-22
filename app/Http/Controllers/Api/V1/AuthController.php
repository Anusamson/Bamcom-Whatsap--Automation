<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * RESTful authentication controller for API v1 clients.
 */
class AuthController extends ApiController
{
    /**
     * Authenticate and issue an API Sanctum token.
     */
    public function login(Request $request, AuthenticationService $authService): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $dto = LoginDTO::fromArray($validated);
        $user = $authService->authenticate($dto);
        $token = $authService->createApiToken($user, $validated['device_name'] ?? 'bamcom-api-client');

        return $this->respondWithSuccess(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $authService->getUserProfile($user)->toArray(),
            ],
            message: 'Authentication successful.',
        );
    }

    /**
     * Register a new user account via the API.
     */
    public function register(Request $request, AuthenticationService $authService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
        ]);

        $dto = RegisterDTO::fromArray($validated);
        $user = $authService->register($dto);
        $token = $authService->createApiToken($user, 'bamcom-api-client');

        return $this->respondWithSuccess(
            data: [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $authService->getUserProfile($user)->toArray(),
            ],
            message: 'User registered successfully.',
            statusCode: 201,
        );
    }

    /**
     * Retrieve the authenticated user profile.
     */
    public function me(Request $request, AuthenticationService $authService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respondWithSuccess(
            data: $authService->getUserProfile($user)->toArray(),
            message: 'Profile retrieved successfully.',
        );
    }

    /**
     * Revoke tokens and log out the user from API.
     */
    public function logout(Request $request, AuthenticationService $authService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $authService->revokeApiTokens($user);

        return $this->respondWithSuccess(
            message: 'Logged out successfully and revoked tokens.',
        );
    }
}
