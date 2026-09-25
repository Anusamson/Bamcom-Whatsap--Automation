<?php

namespace Tests\Feature\Auth;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        foreach (UserRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }
    }

    public function test_root_url_redirects_unauthenticated_user_to_login_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_root_url_redirects_authenticated_user_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_public_self_registration_is_disabled(): void
    {
        // Public registration GET endpoint must be not found (404)
        $this->get('/register')->assertNotFound();

        // Public registration POST endpoint must be not found (404)
        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', [
            'email' => 'intruder@example.com',
        ]);
    }

    public function test_super_admin_can_create_user_with_username_and_password(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $superAdmin->assignRole($superAdminRole);

        $response = $this->actingAs($superAdmin)->post(route('users.store'), [
            'name' => 'Chiamaka Nze',
            'email' => 'chiamaka@bamcom.ng',
            'password' => 'SecurePass2026!',
            'role' => UserRole::SalesExecutive->value,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'Chiamaka Nze',
            'email' => 'chiamaka@bamcom.ng',
            'role' => UserRole::SalesExecutive->value,
        ]);

        // Verify password was hashed and user can authenticate with provided password
        $createdUser = User::where('email', 'chiamaka@bamcom.ng')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('SecurePass2026!', $createdUser->password));
    }

    public function test_regular_user_cannot_create_new_users(): void
    {
        $regularUser = User::factory()->create(['role' => UserRole::SalesExecutive]);

        $response = $this->actingAs($regularUser)->post(route('users.store'), [
            'name' => 'Unwanted User',
            'email' => 'unwanted@bamcom.ng',
            'password' => 'SecurePass2026!',
            'role' => UserRole::SalesExecutive->value,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', [
            'email' => 'unwanted@bamcom.ng',
        ]);
    }
}
