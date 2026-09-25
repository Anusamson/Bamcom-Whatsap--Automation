<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_super_admin_can_access_roles_index_via_gate_before_bypass(): void
    {
        $superAdmin = User::where('email', 'superadmin@bamcom.ai')->first();

        $response = $this->actingAs($superAdmin)->get(route('roles.index'));

        $response->assertStatus(200);
    }

    public function test_user_with_roles_view_permission_can_view_roles_index(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionEnum::RolesView->value);

        $response = $this->actingAs($user)->get(route('roles.index'));

        $response->assertStatus(200);
    }

    public function test_user_without_roles_view_permission_receives_forbidden(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('roles.index'));

        $response->assertStatus(403);
    }

    public function test_user_with_roles_create_can_store_new_role_with_permissions(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionEnum::RolesCreate->value);

        $payload = [
            'name' => 'Auditor Specialist',
            'permissions' => [
                PermissionEnum::ReportsView->value,
                PermissionEnum::ReportsExport->value,
            ],
        ];

        $response = $this->actingAs($user)->post(route('roles.store'), $payload);

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'Auditor Specialist']);

        $role = Role::findByName('Auditor Specialist', 'web');
        $this->assertTrue($role->hasPermissionTo(PermissionEnum::ReportsView->value));
        $this->assertTrue($role->hasPermissionTo(PermissionEnum::ReportsExport->value));
        $this->assertFalse($role->hasPermissionTo(PermissionEnum::RolesCreate->value));
    }

    public function test_user_without_roles_create_cannot_create_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('roles.store'), [
            'name' => 'Unauthorized Role',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('roles', ['name' => 'Unauthorized Role']);
    }

    public function test_user_with_roles_edit_can_update_role(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionEnum::RolesEdit->value);

        $role = Role::create(['name' => 'Regional Officer', 'guard_name' => 'web']);

        $response = $this->actingAs($user)->put(route('roles.update', $role->id), [
            'name' => 'Regional Director',
            'permissions' => [PermissionEnum::LeadsView->value],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'Regional Director']);
        $this->assertTrue($role->fresh()->hasPermissionTo(PermissionEnum::LeadsView->value));
    }

    public function test_user_without_roles_edit_cannot_update_role(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Custom Role', 'guard_name' => 'web']);

        $response = $this->actingAs($user)->put(route('roles.update', $role->id), [
            'name' => 'Hacked Role',
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_role_cannot_be_renamed(): void
    {
        $superAdminUser = User::where('email', 'superadmin@bamcom.ai')->first();
        $superAdminRole = Role::findByName(UserRole::SuperAdmin->value, 'web');

        $response = $this->actingAs($superAdminUser)->put(route('roles.update', $superAdminRole->id), [
            'name' => 'Demoted Super Admin',
            'permissions' => [],
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(UserRole::SuperAdmin->value, $superAdminRole->fresh()->name);
    }

    public function test_core_system_roles_cannot_be_deleted(): void
    {
        $superAdminUser = User::where('email', 'superadmin@bamcom.ai')->first();
        $superAdminRole = Role::findByName(UserRole::SuperAdmin->value, 'web');

        $response = $this->actingAs($superAdminUser)->delete(route('roles.destroy', $superAdminRole->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => UserRole::SuperAdmin->value]);
    }

    public function test_role_with_assigned_users_cannot_be_deleted(): void
    {
        $superAdminUser = User::where('email', 'superadmin@bamcom.ai')->first();
        $customRole = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

        $assignedUser = User::factory()->create();
        $assignedUser->assignRole('Test Role');

        $response = $this->actingAs($superAdminUser)->delete(route('roles.destroy', $customRole->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['name' => 'Test Role']);
    }

    public function test_custom_role_without_users_can_be_deleted(): void
    {
        $superAdminUser = User::where('email', 'superadmin@bamcom.ai')->first();
        $customRole = Role::create(['name' => 'Disposable Role', 'guard_name' => 'web']);

        $response = $this->actingAs($superAdminUser)->delete(route('roles.destroy', $customRole->id));

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseMissing('roles', ['name' => 'Disposable Role']);
    }

    public function test_user_with_permissions_view_can_access_permissions_directory(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionEnum::PermissionsView->value);

        $response = $this->actingAs($user)->get(route('permissions.index'));

        $response->assertStatus(200);
    }

    public function test_user_without_permissions_view_cannot_access_permissions_directory(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('permissions.index'));

        $response->assertStatus(403);
    }

    public function test_user_with_users_edit_can_assign_roles_to_user(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(PermissionEnum::UsersEdit->value);

        $targetUser = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('users.roles.update', $targetUser->id), [
            'roles' => [UserRole::SalesManager->value],
        ]);

        $response->assertRedirect();
        $this->assertTrue($targetUser->fresh()->hasRole(UserRole::SalesManager->value));
    }

    public function test_all_permission_enum_cases_exist_in_database(): void
    {
        $databasePermissions = Permission::pluck('name')->all();

        foreach (PermissionEnum::cases() as $permissionEnum) {
            $this->assertContains($permissionEnum->value, $databasePermissions);
        }
    }

    public function test_user_has_permission_to_returns_false_safely_for_nonexistent_permission(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasPermissionTo('nonexistent.permission.does.not.exist'));
    }

    public function test_admin_user_can_view_any_conversations_without_permission_does_not_exist_exception(): void
    {
        $admin = User::where('email', 'admin@bamcom.ai')->first();

        $this->assertTrue($admin->can('viewAny', Conversation::class));
    }
}
