<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Seed all default roles, granular permissions, and assigned system users.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create all Granular Permissions
        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission->value, 'guard_name' => 'web']
            );
        }

        // 2. Create the 8 Roles
        $roles = [];
        foreach (UserRole::cases() as $roleEnum) {
            $roles[$roleEnum->value] = Role::firstOrCreate(
                ['name' => $roleEnum->value, 'guard_name' => 'web']
            );
        }

        // 3. Super Admin receives all permissions
        $roles[UserRole::SuperAdmin->value]->syncPermissions(Permission::all());

        // 4. Admin receives all operational & user management permissions
        $roles[UserRole::Admin->value]->syncPermissions([
            PermissionEnum::UsersView->value,
            PermissionEnum::UsersCreate->value,
            PermissionEnum::UsersEdit->value,
            PermissionEnum::UsersDelete->value,
            PermissionEnum::RolesView->value,
            PermissionEnum::RolesCreate->value,
            PermissionEnum::RolesEdit->value,
            PermissionEnum::PermissionsView->value,
            PermissionEnum::LeadsView->value,
            PermissionEnum::LeadsCreate->value,
            PermissionEnum::LeadsEdit->value,
            PermissionEnum::LeadsDelete->value,
            PermissionEnum::LeadsAssign->value,
            PermissionEnum::CustomersView->value,
            PermissionEnum::CustomersCreate->value,
            PermissionEnum::CustomersEdit->value,
            PermissionEnum::CustomersDelete->value,
            PermissionEnum::InspectionsView->value,
            PermissionEnum::InspectionsCreate->value,
            PermissionEnum::InspectionsEdit->value,
            PermissionEnum::InspectionsApprove->value,
            PermissionEnum::CampaignsView->value,
            PermissionEnum::CampaignsCreate->value,
            PermissionEnum::CampaignsEdit->value,
            PermissionEnum::CampaignsDelete->value,
            PermissionEnum::TicketsView->value,
            PermissionEnum::TicketsCreate->value,
            PermissionEnum::TicketsReply->value,
            PermissionEnum::TicketsResolve->value,
            PermissionEnum::ReportsView->value,
            PermissionEnum::ReportsExport->value,
            PermissionEnum::SettingsView->value,
            PermissionEnum::SystemHealth->value,
        ]);

        // 5. Sales Manager
        $roles[UserRole::SalesManager->value]->syncPermissions([
            PermissionEnum::UsersView->value,
            PermissionEnum::LeadsView->value,
            PermissionEnum::LeadsCreate->value,
            PermissionEnum::LeadsEdit->value,
            PermissionEnum::LeadsDelete->value,
            PermissionEnum::LeadsAssign->value,
            PermissionEnum::CustomersView->value,
            PermissionEnum::CustomersCreate->value,
            PermissionEnum::CustomersEdit->value,
            PermissionEnum::ReportsView->value,
            PermissionEnum::ReportsExport->value,
        ]);

        // 6. Sales Executive
        $roles[UserRole::SalesExecutive->value]->syncPermissions([
            PermissionEnum::LeadsView->value,
            PermissionEnum::LeadsCreate->value,
            PermissionEnum::LeadsEdit->value,
            PermissionEnum::CustomersView->value,
            PermissionEnum::CustomersCreate->value,
        ]);

        // 7. Customer Support
        $roles[UserRole::CustomerSupport->value]->syncPermissions([
            PermissionEnum::TicketsView->value,
            PermissionEnum::TicketsCreate->value,
            PermissionEnum::TicketsReply->value,
            PermissionEnum::TicketsResolve->value,
            PermissionEnum::CustomersView->value,
            PermissionEnum::LeadsView->value,
        ]);

        // 8. Marketing
        $roles[UserRole::Marketing->value]->syncPermissions([
            PermissionEnum::CampaignsView->value,
            PermissionEnum::CampaignsCreate->value,
            PermissionEnum::CampaignsEdit->value,
            PermissionEnum::CampaignsDelete->value,
            PermissionEnum::LeadsView->value,
            PermissionEnum::ReportsView->value,
        ]);

        // 9. Inspection Officer
        $roles[UserRole::InspectionOfficer->value]->syncPermissions([
            PermissionEnum::InspectionsView->value,
            PermissionEnum::InspectionsCreate->value,
            PermissionEnum::InspectionsEdit->value,
            PermissionEnum::InspectionsApprove->value,
            PermissionEnum::CustomersView->value,
        ]);

        // 10. Management
        $roles[UserRole::Management->value]->syncPermissions([
            PermissionEnum::UsersView->value,
            PermissionEnum::ReportsView->value,
            PermissionEnum::ReportsExport->value,
            PermissionEnum::LeadsView->value,
            PermissionEnum::CustomersView->value,
            PermissionEnum::InspectionsView->value,
            PermissionEnum::CampaignsView->value,
            PermissionEnum::TicketsView->value,
        ]);

        // Seed Users for each role
        $usersToSeed = [
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@bamcom.ai',
                'role' => UserRole::SuperAdmin,
            ],
            [
                'name' => 'System Administrator',
                'email' => 'admin@bamcom.ai',
                'role' => UserRole::Admin,
            ],
            [
                'name' => 'Sarah Sales Manager',
                'email' => 'sales.manager@bamcom.ai',
                'role' => UserRole::SalesManager,
            ],
            [
                'name' => 'Sam Sales Exec',
                'email' => 'sales.exec@bamcom.ai',
                'role' => UserRole::SalesExecutive,
            ],
            [
                'name' => 'Claire Support',
                'email' => 'support@bamcom.ai',
                'role' => UserRole::CustomerSupport,
            ],
            [
                'name' => 'Mike Marketing',
                'email' => 'marketing@bamcom.ai',
                'role' => UserRole::Marketing,
            ],
            [
                'name' => 'Ian Inspector',
                'email' => 'inspection@bamcom.ai',
                'role' => UserRole::InspectionOfficer,
            ],
            [
                'name' => 'Mary Management',
                'email' => 'management@bamcom.ai',
                'role' => UserRole::Management,
            ],
        ];

        foreach ($usersToSeed as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('Password123!'),
                    'role' => $userData['role'],
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                ]
            );

            // Sync role via Spatie
            $user->syncRoles([$userData['role']->value]);
        }

        // Ensure primary developer/owner account is granted Super Admin
        $primaryUser = User::where('email', 'anusamson25@gmail.com')->first();
        if ($primaryUser) {
            $primaryUser->update(['role' => UserRole::SuperAdmin]);
            $primaryUser->syncRoles([UserRole::SuperAdmin->value]);
        }
    }
}
