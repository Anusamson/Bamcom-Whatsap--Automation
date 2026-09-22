<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAssignmentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_all_eight_roles_are_seeded(): void
    {
        $expectedRoles = [
            'Super Admin',
            'Admin',
            'Sales Manager',
            'Sales Executive',
            'Customer Support',
            'Marketing',
            'Inspection Officer',
            'Management',
        ];

        foreach ($expectedRoles as $roleName) {
            $this->assertDatabaseHas('roles', ['name' => $roleName]);
        }

        $this->assertCount(8, Role::all());
    }

    public function test_super_admin_has_all_permissions_seeded(): void
    {
        $superAdmin = Role::findByName(UserRole::SuperAdmin->value, 'web');
        $allPermissionsCount = Permission::count();

        $this->assertSame($allPermissionsCount, $superAdmin->permissions()->count());
    }

    public function test_sales_manager_has_lead_and_customer_permissions_but_cannot_approve_inspections(): void
    {
        $salesManager = Role::findByName(UserRole::SalesManager->value, 'web');

        $this->assertTrue($salesManager->hasPermissionTo(PermissionEnum::LeadsView->value));
        $this->assertTrue($salesManager->hasPermissionTo(PermissionEnum::LeadsCreate->value));
        $this->assertTrue($salesManager->hasPermissionTo(PermissionEnum::LeadsAssign->value));
        $this->assertTrue($salesManager->hasPermissionTo(PermissionEnum::CustomersView->value));

        $this->assertFalse($salesManager->hasPermissionTo(PermissionEnum::InspectionsApprove->value));
        $this->assertFalse($salesManager->hasPermissionTo(PermissionEnum::RolesDelete->value));
    }

    public function test_customer_support_has_ticket_permissions(): void
    {
        $supportRole = Role::findByName(UserRole::CustomerSupport->value, 'web');

        $this->assertTrue($supportRole->hasPermissionTo(PermissionEnum::TicketsView->value));
        $this->assertTrue($supportRole->hasPermissionTo(PermissionEnum::TicketsReply->value));
        $this->assertTrue($supportRole->hasPermissionTo(PermissionEnum::TicketsResolve->value));

        $this->assertFalse($supportRole->hasPermissionTo(PermissionEnum::CampaignsDelete->value));
        $this->assertFalse($supportRole->hasPermissionTo(PermissionEnum::UsersDelete->value));
    }

    public function test_inspection_officer_can_approve_inspections_only(): void
    {
        $inspector = Role::findByName(UserRole::InspectionOfficer->value, 'web');

        $this->assertTrue($inspector->hasPermissionTo(PermissionEnum::InspectionsView->value));
        $this->assertTrue($inspector->hasPermissionTo(PermissionEnum::InspectionsApprove->value));

        $this->assertFalse($inspector->hasPermissionTo(PermissionEnum::TicketsCreate->value));
        $this->assertFalse($inspector->hasPermissionTo(PermissionEnum::CampaignsCreate->value));
    }
}
