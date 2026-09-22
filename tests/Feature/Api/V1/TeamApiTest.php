<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $salesManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::where('email', 'admin@bamcom.ai')->first();
        $this->salesManager = User::where('email', 'sales.manager@bamcom.ai')->first();

        Sanctum::actingAs($this->admin, ['*']);
    }

    public function test_api_can_list_teams(): void
    {
        $response = $this->getJson(route('api.v1.teams.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    public function test_api_can_retrieve_sales_teams_specifically(): void
    {
        $response = $this->getJson(route('api.v1.teams.sales'));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertSame(TeamType::Sales->value, $item['type']);
        }
    }

    public function test_api_can_create_team(): void
    {
        $response = $this->postJson(route('api.v1.teams.store'), [
            'name' => 'API Mid-Market Sales',
            'description' => 'Targeting growth accounts.',
            'type' => TeamType::Sales->value,
            'leader_id' => $this->salesManager->id,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'API Mid-Market Sales');

        $this->assertDatabaseHas('teams', ['name' => 'API Mid-Market Sales']);
    }

    public function test_api_can_assign_team_members(): void
    {
        $team = Team::first();
        $newUser = User::factory()->create([
            'role' => UserRole::SalesExecutive,
            'status' => UserStatus::Active,
        ]);

        $response = $this->postJson(route('api.v1.teams.members.assign', $team->id), [
            'user_ids' => [$newUser->id],
            'role_in_team' => 'member',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue($team->fresh()->members()->where('users.id', $newUser->id)->exists());
    }
}
