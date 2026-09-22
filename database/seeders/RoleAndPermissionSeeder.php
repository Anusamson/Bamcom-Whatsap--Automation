<?php

namespace Database\Seeders;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Enums\PermissionEnum;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Contact;
use App\Models\Team;
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
            PermissionEnum::UsersDisable->value,
            PermissionEnum::TeamsView->value,
            PermissionEnum::TeamsCreate->value,
            PermissionEnum::TeamsEdit->value,
            PermissionEnum::TeamsDelete->value,
            PermissionEnum::TeamsAssign->value,
            PermissionEnum::ContactsView->value,
            PermissionEnum::ContactsCreate->value,
            PermissionEnum::ContactsEdit->value,
            PermissionEnum::ContactsDelete->value,
            PermissionEnum::ContactsAssign->value,
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
            PermissionEnum::TeamsView->value,
            PermissionEnum::TeamsAssign->value,
            PermissionEnum::ContactsView->value,
            PermissionEnum::ContactsCreate->value,
            PermissionEnum::ContactsEdit->value,
            PermissionEnum::ContactsDelete->value,
            PermissionEnum::ContactsAssign->value,
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
            PermissionEnum::ContactsView->value,
            PermissionEnum::ContactsCreate->value,
            PermissionEnum::ContactsEdit->value,
            PermissionEnum::LeadsView->value,
            PermissionEnum::LeadsCreate->value,
            PermissionEnum::LeadsEdit->value,
            PermissionEnum::CustomersView->value,
            PermissionEnum::CustomersCreate->value,
        ]);

        // 7. Customer Support
        $roles[UserRole::CustomerSupport->value]->syncPermissions([
            PermissionEnum::ContactsView->value,
            PermissionEnum::ContactsEdit->value,
            PermissionEnum::TicketsView->value,
            PermissionEnum::TicketsCreate->value,
            PermissionEnum::TicketsReply->value,
            PermissionEnum::TicketsResolve->value,
            PermissionEnum::CustomersView->value,
            PermissionEnum::LeadsView->value,
        ]);

        // 8. Marketing
        $roles[UserRole::Marketing->value]->syncPermissions([
            PermissionEnum::ContactsView->value,
            PermissionEnum::ContactsCreate->value,
            PermissionEnum::CampaignsView->value,
            PermissionEnum::CampaignsCreate->value,
            PermissionEnum::CampaignsEdit->value,
            PermissionEnum::CampaignsDelete->value,
            PermissionEnum::LeadsView->value,
            PermissionEnum::ReportsView->value,
        ]);

        // 9. Inspection Officer
        $roles[UserRole::InspectionOfficer->value]->syncPermissions([
            PermissionEnum::ContactsView->value,
            PermissionEnum::InspectionsView->value,
            PermissionEnum::InspectionsCreate->value,
            PermissionEnum::InspectionsEdit->value,
            PermissionEnum::InspectionsApprove->value,
            PermissionEnum::CustomersView->value,
        ]);

        // 10. Management
        $roles[UserRole::Management->value]->syncPermissions([
            PermissionEnum::UsersView->value,
            PermissionEnum::TeamsView->value,
            PermissionEnum::ContactsView->value,
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
                'phone' => '+1 (555) 019-2831',
                'job_title' => 'Chief Executive Officer',
                'department' => 'Executive Office',
            ],
            [
                'name' => 'System Administrator',
                'email' => 'admin@bamcom.ai',
                'role' => UserRole::Admin,
                'phone' => '+1 (555) 014-9922',
                'job_title' => 'Lead System Administrator',
                'department' => 'IT & Operations',
            ],
            [
                'name' => 'Sarah Sales Manager',
                'email' => 'sales.manager@bamcom.ai',
                'role' => UserRole::SalesManager,
                'phone' => '+1 (555) 012-3456',
                'job_title' => 'Sales Director',
                'department' => 'Enterprise Sales',
            ],
            [
                'name' => 'Sam Sales Exec',
                'email' => 'sales.exec@bamcom.ai',
                'role' => UserRole::SalesExecutive,
                'phone' => '+1 (555) 018-7654',
                'job_title' => 'Senior Sales Executive',
                'department' => 'Enterprise Sales',
            ],
            [
                'name' => 'Claire Support',
                'email' => 'support@bamcom.ai',
                'role' => UserRole::CustomerSupport,
                'phone' => '+1 (555) 017-4321',
                'job_title' => 'Support Team Lead',
                'department' => 'Customer Success',
            ],
            [
                'name' => 'Mike Marketing',
                'email' => 'marketing@bamcom.ai',
                'role' => UserRole::Marketing,
                'phone' => '+1 (555) 016-8765',
                'job_title' => 'Head of Growth & Marketing',
                'department' => 'Marketing',
            ],
            [
                'name' => 'Ian Inspector',
                'email' => 'inspection@bamcom.ai',
                'role' => UserRole::InspectionOfficer,
                'phone' => '+1 (555) 015-1122',
                'job_title' => 'Senior Field Inspector',
                'department' => 'Quality & Verification',
            ],
            [
                'name' => 'Mary Management',
                'email' => 'management@bamcom.ai',
                'role' => UserRole::Management,
                'phone' => '+1 (555) 013-9876',
                'job_title' => 'Operations Director',
                'department' => 'Management',
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

            // Sync Profile
            $user->profile()->updateOrCreate([], [
                'phone' => $userData['phone'],
                'job_title' => $userData['job_title'],
                'department' => $userData['department'],
                'bio' => "Professional {$userData['job_title']} at Bamcom AI CRM.",
            ]);
        }

        // Ensure primary developer/owner account is granted Super Admin and has a profile
        $primaryUser = User::where('email', 'anusamson25@gmail.com')->first();
        if ($primaryUser) {
            $primaryUser->update(['role' => UserRole::SuperAdmin]);
            $primaryUser->syncRoles([UserRole::SuperAdmin->value]);
            $primaryUser->profile()->updateOrCreate([], [
                'phone' => '+1 (555) 000-0001',
                'job_title' => 'Principal Founder / Super Admin',
                'department' => 'Executive Office',
                'bio' => 'System founder and principal administrator.',
            ]);
        }

        // 11. Seed Default Teams and Sales Team Assignments
        $salesManager = User::where('email', 'sales.manager@bamcom.ai')->first();
        $salesExec = User::where('email', 'sales.exec@bamcom.ai')->first();
        $supportUser = User::where('email', 'support@bamcom.ai')->first();
        $inspectorUser = User::where('email', 'inspection@bamcom.ai')->first();

        // 1. Enterprise Sales Team
        $salesTeam = Team::updateOrCreate(
            ['name' => 'Enterprise Sales Team'],
            [
                'description' => 'Dedicated unit driving high-value corporate client acquisition and pipeline conversions.',
                'type' => TeamType::Sales,
                'leader_id' => $salesManager?->id,
                'is_active' => true,
            ]
        );

        if ($salesManager && $salesExec) {
            $salesTeam->members()->sync([
                $salesManager->id => ['role_in_team' => 'leader', 'joined_at' => now()],
                $salesExec->id => ['role_in_team' => 'member', 'joined_at' => now()],
            ]);

            // Assign sales team as primary team
            $salesManager->update(['team_id' => $salesTeam->id]);
            $salesExec->update(['team_id' => $salesTeam->id]);
        }

        // 2. Customer Care Squad
        $supportTeam = Team::updateOrCreate(
            ['name' => 'Customer Care Squad'],
            [
                'description' => 'Front-line customer satisfaction, rapid issue triage, and onboarding assistance.',
                'type' => TeamType::Support,
                'leader_id' => $supportUser?->id,
                'is_active' => true,
            ]
        );

        if ($supportUser) {
            $supportTeam->members()->sync([
                $supportUser->id => ['role_in_team' => 'leader', 'joined_at' => now()],
            ]);
            $supportUser->update(['team_id' => $supportTeam->id]);
        }

        // 3. Field Inspection Unit
        $inspectionTeam = Team::updateOrCreate(
            ['name' => 'Field Inspection Unit'],
            [
                'description' => 'On-site asset verification, compliance audits, and field inspection reporting.',
                'type' => TeamType::Inspection,
                'leader_id' => $inspectorUser?->id,
                'is_active' => true,
            ]
        );

        if ($inspectorUser) {
            $inspectionTeam->members()->sync([
                $inspectorUser->id => ['role_in_team' => 'leader', 'joined_at' => now()],
            ]);
            $inspectorUser->update(['team_id' => $inspectionTeam->id]);
        }

        // Seed Sample CRM Contacts
        $sampleContacts = [
            [
                'first_name' => 'Adewale',
                'last_name' => 'Adeyemi',
                'phone' => '+2348021234567',
                'email' => 'adewale.adeyemi@primeinvest.ng',
                'location' => 'Ikoyi, Lagos',
                'occupation' => 'Chief Investment Officer',
                'preferred_language' => 'en',
                'lead_source' => LeadSource::WhatsApp->value,
                'assigned_user_id' => $salesExec?->id,
                'status' => ContactStatus::Prospect->value,
                'last_contact_at' => now()->subHours(4),
            ],
            [
                'first_name' => 'Chioma',
                'last_name' => 'Okonkwo',
                'phone' => '+2348039876543',
                'email' => 'chioma.okonkwo@capitalflow.com',
                'location' => 'Victoria Island, Lagos',
                'occupation' => 'Fintech Executive & Angel Investor',
                'preferred_language' => 'en',
                'lead_source' => LeadSource::Referral->value,
                'assigned_user_id' => $salesManager?->id,
                'status' => ContactStatus::Customer->value,
                'last_contact_at' => now()->subDays(2),
            ],
            [
                'first_name' => 'Babajide',
                'last_name' => 'Sanusi',
                'phone' => '+2348055551234',
                'email' => 'babajide.sanusi@apexenergy.ng',
                'location' => 'Maitama, Abuja',
                'occupation' => 'Energy Consultant & Developer',
                'preferred_language' => 'en',
                'lead_source' => LeadSource::Website->value,
                'assigned_user_id' => $salesExec?->id,
                'status' => ContactStatus::Lead->value,
                'last_contact_at' => now()->subHours(12),
            ],
            [
                'first_name' => 'Fatima',
                'last_name' => 'Bello',
                'phone' => '+2348077778899',
                'email' => 'fatima.bello@nordicexports.com',
                'location' => 'Kano Municipal, Kano',
                'occupation' => 'Agri-Tech Founder & Exporter',
                'preferred_language' => 'ha',
                'lead_source' => LeadSource::WhatsApp->value,
                'assigned_user_id' => null,
                'status' => ContactStatus::Lead->value,
                'last_contact_at' => null,
            ],
        ];

        foreach ($sampleContacts as $data) {
            Contact::updateOrCreate(
                ['phone' => $data['phone']],
                $data
            );
        }
    }
}
