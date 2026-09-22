<?php

namespace Tests\Unit\DTOs;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\System\HealthStatusDTO;
use App\DTOs\User\UserProfileDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDTOTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_dto_normalizes_email_and_defaults_role(): void
    {
        $dto = RegisterDTO::fromArray([
            'name' => 'Alice Admin',
            'email' => '  ALICE@BAMCOM.AI  ',
            'password' => 'secret123',
        ]);

        $this->assertSame('Alice Admin', $dto->name);
        $this->assertSame('alice@bamcom.ai', $dto->email);
        $this->assertSame('secret123', $dto->password);
        $this->assertSame(UserRole::SalesExecutive, $dto->role);

        $array = $dto->toArray();
        $this->assertSame('Alice Admin', $array['name']);
        $this->assertSame('alice@bamcom.ai', $array['email']);
        $this->assertSame('Sales Executive', $array['role']);
    }

    public function test_register_dto_accepts_custom_role(): void
    {
        $dto = RegisterDTO::fromArray([
            'name' => 'Bob Manager',
            'email' => 'bob@bamcom.ai',
            'password' => 'secret123',
            'role' => 'Sales Manager',
        ]);

        $this->assertSame(UserRole::SalesManager, $dto->role);
    }

    public function test_login_dto_from_array(): void
    {
        $dto = LoginDTO::fromArray([
            'email' => ' USER@BAMCOM.AI ',
            'password' => 'secret',
            'remember' => true,
        ]);

        $this->assertSame('user@bamcom.ai', $dto->email);
        $this->assertSame('secret', $dto->password);
        $this->assertTrue($dto->remember);
    }

    public function test_user_profile_dto_from_model(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@bamcom.ai',
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $dto = UserProfileDTO::fromModel($user);

        $this->assertSame($user->id, $dto->id);
        $this->assertSame('John Doe', $dto->name);
        $this->assertSame('john@bamcom.ai', $dto->email);
        $this->assertSame('Admin', $dto->role);
        $this->assertSame('active', $dto->status);
    }

    public function test_health_status_dto_json_serialization(): void
    {
        $dto = HealthStatusDTO::fromArray([
            'status' => 'healthy',
            'environment' => 'testing',
            'timestamp' => now()->toIso8601String(),
            'database' => ['status' => 'healthy', 'message' => 'ok', 'latency_ms' => 1.5],
            'redis' => ['status' => 'healthy', 'message' => 'ok', 'latency_ms' => 0.8],
            'cache' => ['status' => 'healthy', 'message' => 'ok', 'driver' => 'array'],
            'queue' => ['status' => 'healthy', 'message' => 'ok', 'driver' => 'sync'],
        ]);

        $array = $dto->toArray();
        $this->assertSame('healthy', $array['status']);
        $this->assertArrayHasKey('services', $array);
        $this->assertJson($dto->toJson());
    }
}
