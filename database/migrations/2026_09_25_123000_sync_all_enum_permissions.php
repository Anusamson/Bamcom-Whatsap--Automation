<?php

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Ensure all permissions defined in PermissionEnum exist in the database
        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission->value,
                'guard_name' => 'web',
            ]);
        }

        // 2. Ensure all roles exist
        $roles = [];
        foreach (UserRole::cases() as $roleEnum) {
            $roles[$roleEnum->value] = Role::firstOrCreate([
                'name' => $roleEnum->value,
                'guard_name' => 'web',
            ]);
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
            PermissionEnum::PropertiesView->value,
            PermissionEnum::PropertiesCreate->value,
            PermissionEnum::PropertiesEdit->value,
            PermissionEnum::PropertiesDelete->value,
            PermissionEnum::DealsView->value,
            PermissionEnum::DealsCreate->value,
            PermissionEnum::DealsEdit->value,
            PermissionEnum::DealsDelete->value,
            PermissionEnum::ReportsView->value,
            PermissionEnum::ReportsExport->value,
            PermissionEnum::WhatsAppView->value,
            PermissionEnum::WhatsAppManage->value,
            PermissionEnum::WhatsAppSend->value,
            PermissionEnum::ConversationsView->value,
            PermissionEnum::ConversationsManage->value,
            PermissionEnum::ConversationsAssign->value,
            PermissionEnum::MessagesSend->value,
            PermissionEnum::KnowledgeView->value,
            PermissionEnum::KnowledgeCreate->value,
            PermissionEnum::KnowledgeEdit->value,
            PermissionEnum::KnowledgeDelete->value,
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
            PermissionEnum::PropertiesView->value,
            PermissionEnum::DealsView->value,
            PermissionEnum::DealsCreate->value,
            PermissionEnum::DealsEdit->value,
            PermissionEnum::DealsDelete->value,
            PermissionEnum::WhatsAppView->value,
            PermissionEnum::WhatsAppManage->value,
            PermissionEnum::WhatsAppSend->value,
            PermissionEnum::ConversationsView->value,
            PermissionEnum::ConversationsManage->value,
            PermissionEnum::ConversationsAssign->value,
            PermissionEnum::MessagesSend->value,
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
            PermissionEnum::PropertiesView->value,
            PermissionEnum::DealsView->value,
            PermissionEnum::DealsCreate->value,
            PermissionEnum::DealsEdit->value,
            PermissionEnum::WhatsAppView->value,
            PermissionEnum::WhatsAppSend->value,
            PermissionEnum::ConversationsView->value,
            PermissionEnum::MessagesSend->value,
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
            PermissionEnum::ConversationsView->value,
            PermissionEnum::MessagesSend->value,
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

        // Clear cache again after syncing
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to avoid breaking active role permissions
    }
};
