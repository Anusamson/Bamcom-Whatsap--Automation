<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\User\UserProfileDTO;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Service managing user authentication, registration, and API tokens.
 */
class AuthenticationService extends BaseService
{
    /**
     * Register a new user from a RegisterDTO.
     *
     * @throws ValidationException
     */
    public function register(RegisterDTO $dto): User
    {
        return $this->transaction(function () use ($dto): User {
            if (User::where('email', $dto->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['The provided email is already registered.'],
                ]);
            }

            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'role' => $dto->role,
                'status' => UserStatus::Active,
            ]);

            $this->logInfo('New user registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role->value,
            ]);

            return $user;
        });
    }

    /**
     * Authenticate user credentials and return the user instance.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     */
    public function authenticate(LoginDTO $dto): User
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            throw new AuthenticationException('Your account is currently '.$user->status->label().'. Please contact support.');
        }

        $this->logInfo('User authenticated successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user;
    }

    /**
     * Generate an API Sanctum token for the user.
     */
    public function createApiToken(User $user, string $tokenName = 'bamcom-api-token'): string
    {
        $token = $user->createToken($tokenName, ['*']);

        return $token->plainTextToken;
    }

    /**
     * Revoke all API tokens for the given user.
     */
    public function revokeApiTokens(User $user): void
    {
        $user->tokens()->delete();

        $this->logInfo('User API tokens revoked', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Transform an Eloquent user into a typed UserProfileDTO.
     */
    public function getUserProfile(User $user): UserProfileDTO
    {
        return UserProfileDTO::fromModel($user);
    }
}
