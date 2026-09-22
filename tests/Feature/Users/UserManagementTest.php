<?php

namespace Tests\Feature\Users;

use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $salesExec;

    protected User $superAdmin;

    protected Team $salesTeam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->superAdmin = User::where('email', 'superadmin@bamcom.ai')->first();
        $this->admin = User::where('email', 'admin@bamcom.ai')->first();
        $this->salesExec = User::where('email', 'sales.exec@bamcom.ai')->first();
        $this->salesTeam = Team::where('type', TeamType::Sales)->first();
    }

    public function test_admin_can_view_users_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertOk();
    }

    public function test_unauthorized_user_cannot_view_users_index(): void
    {
        $unauthorized = User::factory()->create([
            'role' => UserRole::CustomerSupport,
            'status' => UserStatus::Active,
        ]);
        // Do not assign users.view permission
        $response = $this->actingAs($unauthorized)->get(route('users.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_user_with_role_team_and_profile(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Marcus Vance',
            'email' => 'marcus@bamcom.ai',
            'password' => 'Password123!',
            'role' => UserRole::SalesExecutive->value,
            'status' => UserStatus::Active->value,
            'team_id' => $this->salesTeam->id,
            'phone' => '+1 (555) 334-5566',
            'job_title' => 'Junior Sales Representative',
            'department' => 'Outbound Sales',
            'bio' => 'Top performing inbound closer.',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'marcus@bamcom.ai',
            'role' => UserRole::SalesExecutive->value,
            'team_id' => $this->salesTeam->id,
        ]);

        $created = User::where('email', 'marcus@bamcom.ai')->first();
        $this->assertNotNull($created->profile);
        $this->assertSame('+1 (555) 334-5566', $created->profile->phone);
        $this->assertSame('Junior Sales Representative', $created->profile->job_title);
        $this->assertTrue($created->hasRole(UserRole::SalesExecutive->value));
    }

    public function test_admin_can_edit_user_and_change_team(): void
    {
        $newTeam = Team::create([
            'name' => 'Strategic Growth Team',
            'type' => TeamType::Sales,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('users.update', $this->salesExec->id), [
            'name' => 'Sam Sales Executive Updated',
            'email' => $this->salesExec->email,
            'role' => UserRole::SalesExecutive->value,
            'status' => UserStatus::Active->value,
            'team_id' => $newTeam->id,
            'phone' => '+1 (555) 999-0000',
            'job_title' => 'Lead Sales Strategist',
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertSame($newTeam->id, $this->salesExec->fresh()->team_id);
        $this->assertSame('Sam Sales Executive Updated', $this->salesExec->fresh()->name);
        $this->assertSame('Lead Sales Strategist', $this->salesExec->fresh()->profile->job_title);
    }

    public function test_admin_can_toggle_user_status_to_disable(): void
    {
        $this->assertSame(UserStatus::Active, $this->salesExec->status);

        $response = $this->actingAs($this->admin)->patch(route('users.toggle-status', $this->salesExec->id));

        $response->assertSessionHas('success');
        $this->assertSame(UserStatus::Inactive, $this->salesExec->fresh()->status);
    }

    public function test_disabled_user_cannot_log_in(): void
    {
        $this->salesExec->update(['status' => UserStatus::Inactive]);

        $response = $this->post(route('login'), [
            'email' => $this->salesExec->email,
            'password' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_with_crm_activity_cannot_be_permanently_deleted(): void
    {
        // Give user CRM activity: assigned as team leader
        $team = Team::create([
            'name' => 'Inspection Alpha Unit',
            'type' => TeamType::Inspection,
            'leader_id' => $this->salesExec->id,
            'is_active' => true,
        ]);

        $this->assertTrue($this->salesExec->hasCrmActivity());

        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $this->salesExec->id));

        $response->assertSessionHas('error');
        // Assert user was deactivated instead of deleted
        $this->assertDatabaseHas('users', [
            'id' => $this->salesExec->id,
            'status' => UserStatus::Inactive->value,
            'deleted_at' => null,
        ]);
    }

    public function test_user_without_crm_activity_can_be_soft_deleted(): void
    {
        $freshUser = User::factory()->create([
            'role' => UserRole::SalesExecutive,
            'status' => UserStatus::Active,
        ]);

        $this->assertFalse($freshUser->hasCrmActivity());

        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $freshUser->id));

        $response->assertRedirect(route('users.index'));
        $this->assertSoftDeleted('users', ['id' => $freshUser->id]);
    }

    public function test_super_admin_cannot_be_deleted_or_disabled(): void
    {
        // Try deleting Super Admin
        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $this->superAdmin->id));
        $response->assertForbidden();

        // Try disabling Super Admin
        $response = $this->actingAs($this->admin)->patch(route('users.toggle-status', $this->superAdmin->id));
        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->id,
            'status' => UserStatus::Active->value,
            'deleted_at' => null,
        ]);
    }
}
