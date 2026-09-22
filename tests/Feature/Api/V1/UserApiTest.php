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

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $salesExec;

    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::where('email', 'admin@bamcom.ai')->first();
        $this->salesExec = User::where('email', 'sales.exec@bamcom.ai')->first();
        $this->team = Team::first();

        Sanctum::actingAs($this->admin, ['*']);
    }

    public function test_api_can_list_users(): void
    {
        $response = $this->getJson(route('api.v1.users.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'total', 'per_page'],
            ]);
    }

    public function test_api_can_create_user_with_profile(): void
    {
        $response = $this->postJson(route('api.v1.users.store'), [
            'name' => 'API Created Agent',
            'email' => 'api.agent@bamcom.ai',
            'password' => 'Password123!',
            'role' => UserRole::SalesExecutive->value,
            'status' => UserStatus::Active->value,
            'team_id' => $this->team->id,
            'phone' => '+1 (555) 888-1111',
            'job_title' => 'Digital Closer',
            'department' => 'Sales',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'API Created Agent')
            ->assertJsonPath('data.email', 'api.agent@bamcom.ai');

        $this->assertDatabaseHas('users', ['email' => 'api.agent@bamcom.ai']);
    }

    public function test_api_can_show_user_details(): void
    {
        $response = $this->getJson(route('api.v1.users.show', $this->salesExec->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $this->salesExec->id)
            ->assertJsonStructure(['data' => ['user', 'has_crm_activity']]);
    }

    public function test_api_can_update_user(): void
    {
        $response = $this->putJson(route('api.v1.users.update', $this->salesExec->id), [
            'name' => 'Sam Exec Modified',
            'job_title' => 'Enterprise Executive',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Sam Exec Modified')
            ->assertJsonPath('data.profile.job_title', 'Enterprise Executive');
    }

    public function test_api_can_toggle_user_status(): void
    {
        $response = $this->patchJson(route('api.v1.users.status', $this->salesExec->id), [
            'status' => UserStatus::Inactive->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', UserStatus::Inactive->value);

        $this->assertSame(UserStatus::Inactive, $this->salesExec->fresh()->status);
    }

    public function test_api_can_assign_team(): void
    {
        $newTeam = Team::create([
            'name' => 'New Regional Unit',
            'type' => TeamType::Sales,
        ]);

        $response = $this->patchJson(route('api.v1.users.team', $this->salesExec->id), [
            'team_id' => $newTeam->id,
            'role_in_team' => 'member',
        ]);

        $response->assertOk();
        $this->assertSame($newTeam->id, $this->salesExec->fresh()->team_id);
    }

    public function test_api_delete_blocks_user_with_crm_activity(): void
    {
        // Give user CRM activity
        Team::create([
            'name' => 'Operations Squad',
            'type' => TeamType::General,
            'leader_id' => $this->salesExec->id,
        ]);

        $response = $this->deleteJson(route('api.v1.users.destroy', $this->salesExec->id));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.action', 'deactivated');

        $this->assertSame(UserStatus::Inactive, $this->salesExec->fresh()->status);
    }
}
