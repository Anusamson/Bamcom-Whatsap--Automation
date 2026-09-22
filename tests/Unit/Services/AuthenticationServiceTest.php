<?php

namespace Tests\Unit\Services;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Auth\AuthenticationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AuthenticationService::class);
    }

    public function test_service_can_register_new_user(): void
    {
        $dto = RegisterDTO::fromArray([
            'name' => 'Jane Executive',
            'email' => 'jane@bamcom.ai',
            'password' => 'secret12345',
            'role' => 'Sales Executive',
        ]);

        $user = $this->service->register($dto);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Jane Executive', $user->name);
        $this->assertSame('jane@bamcom.ai', $user->email);
        $this->assertSame(UserRole::SalesExecutive, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertDatabaseHas('users', ['email' => 'jane@bamcom.ai']);
    }

    public function test_register_duplicate_email_throws_validation_exception(): void
    {
        User::factory()->create(['email' => 'existing@bamcom.ai']);

        $this->expectException(ValidationException::class);

        $this->service->register(RegisterDTO::fromArray([
            'name' => 'Existing Person',
            'email' => 'existing@bamcom.ai',
            'password' => 'password123',
        ]));
    }

    public function test_service_can_authenticate_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@bamcom.ai',
            'password' => bcrypt('correct-password'),
            'status' => UserStatus::Active,
        ]);

        $dto = LoginDTO::fromArray([
            'email' => 'login@bamcom.ai',
            'password' => 'correct-password',
        ]);

        $user = $this->service->authenticate($dto);
        $this->assertSame('login@bamcom.ai', $user->email);
    }

    public function test_service_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'wrong@bamcom.ai',
            'password' => bcrypt('correct-password'),
        ]);

        $this->expectException(ValidationException::class);

        $this->service->authenticate(LoginDTO::fromArray([
            'email' => 'wrong@bamcom.ai',
            'password' => 'incorrect-password',
        ]));
    }

    public function test_service_rejects_suspended_user(): void
    {
        User::factory()->create([
            'email' => 'suspended@bamcom.ai',
            'password' => bcrypt('password123'),
            'status' => UserStatus::Suspended,
        ]);

        $this->expectException(AuthenticationException::class);

        $this->service->authenticate(LoginDTO::fromArray([
            'email' => 'suspended@bamcom.ai',
            'password' => 'password123',
        ]));
    }

    public function test_create_and_revoke_api_tokens(): void
    {
        $user = User::factory()->create();

        $token = $this->service->createApiToken($user, 'mobile-test-token');
        $this->assertNotEmpty($token);
        $this->assertCount(1, $user->tokens);

        $this->service->revokeApiTokens($user);
        $this->assertCount(0, $user->fresh()->tokens);
    }
}
