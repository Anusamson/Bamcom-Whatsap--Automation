<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $salesManager;

    protected User $salesExec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::where('email', 'admin@bamcom.ai')->first();
        $this->salesManager = User::where('email', 'sales.manager@bamcom.ai')->first();
        $this->salesExec = User::where('email', 'sales.exec@bamcom.ai')->first();
    }

    public function test_admin_can_view_teams_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('teams.index'));

        $response->assertOk();
    }

    public function test_admin_can_create_sales_team_with_leader_and_members(): void
    {
        $response = $this->actingAs($this->admin)->post(route('teams.store'), [
            'name' => 'High-Velocity Sales Pod',
            'description' => 'Fast inbound lead qualification and conversion unit.',
            'type' => TeamType::Sales->value,
            'leader_id' => $this->salesManager->id,
            'is_active' => true,
            'member_ids' => [$this->salesExec->id],
        ]);

        $response->assertRedirect(route('teams.index'));
        $this->assertDatabaseHas('teams', [
            'name' => 'High-Velocity Sales Pod',
            'type' => TeamType::Sales->value,
            'leader_id' => $this->salesManager->id,
        ]);

        $team = Team::where('name', 'High-Velocity Sales Pod')->first();
        $this->assertTrue($team->members()->where('users.id', $this->salesExec->id)->exists());
        $this->assertTrue($team->members()->where('users.id', $this->salesManager->id)->exists());
    }

    public function test_admin_can_assign_members_to_sales_team(): void
    {
        $team = Team::create([
            'name' => 'Northern Region Sales Team',
            'type' => TeamType::Sales,
            'is_active' => true,
        ]);

        $newRep = User::factory()->create([
            'role' => UserRole::SalesExecutive,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($this->admin)->post(route('teams.members.assign', $team->id), [
            'user_ids' => [$newRep->id],
            'role_in_team' => 'member',
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue($team->members()->where('users.id', $newRep->id)->exists());
        // Sales team assignment updates user's primary team
        $this->assertSame($team->id, $newRep->fresh()->team_id);
    }

    public function test_admin_can_remove_member_from_team(): void
    {
        $team = Team::first();
        $user = $team->members()->first();

        $response = $this->actingAs($this->admin)->delete(route('teams.members.remove', [$team->id, $user->id]));

        $response->assertSessionHas('success');
        $this->assertFalse($team->fresh()->members()->where('users.id', $user->id)->exists());
    }

    public function test_deleting_team_detaches_members_without_deleting_users(): void
    {
        $team = Team::create([
            'name' => 'Temporary Promo Team',
            'type' => TeamType::Marketing,
            'is_active' => true,
        ]);

        $member = User::factory()->create([
            'team_id' => $team->id,
            'role' => UserRole::Marketing,
        ]);
        $team->members()->attach($member->id, ['role_in_team' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($this->admin)->delete(route('teams.destroy', $team->id));

        $response->assertRedirect(route('teams.index'));
        $this->assertSoftDeleted('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->assertNull($member->fresh()->team_id);
    }
}
