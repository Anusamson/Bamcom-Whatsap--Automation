<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_v1_can_register_new_user(): void
    {
        $payload = [
            'name' => 'API Admin',
            'email' => 'api_admin@bamcom.ai',
            'password' => 'SecurePass123!',
        ];

        $response = $this->postJson(route('api.v1.auth.register'), $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email', 'role', 'status'],
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'api_admin@bamcom.ai']);
    }

    public function test_api_v1_can_login_and_retrieve_token(): void
    {
        $user = User::factory()->create([
            'email' => 'api_user@bamcom.ai',
            'password' => bcrypt('ValidPassword123'),
        ]);

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'api_user@bamcom.ai',
            'password' => 'ValidPassword123',
            'device_name' => 'postman-suite',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'email' => 'api_user@bamcom.ai',
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_api_v1_authenticated_me_endpoint(): void
    {
        $user = User::factory()->create([
            'email' => 'me_endpoint@bamcom.ai',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('api.v1.auth.me'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'me_endpoint@bamcom.ai',
                ],
            ]);
    }

    public function test_api_v1_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('logout-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('api.v1.auth.logout'));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(0, $user->fresh()->tokens);
    }
}
