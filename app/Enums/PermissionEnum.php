<?php

namespace App\Enums;

/**
 * Granular permissions catalog for Bamcom AI CRM.
 */
enum PermissionEnum: string
{
    // User & Role Access Control
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersEdit = 'users.edit';
    case UsersDelete = 'users.delete';
    case UsersDisable = 'users.disable';
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesEdit = 'roles.edit';
    case RolesDelete = 'roles.delete';
    case PermissionsView = 'permissions.view';

    // Teams & Sales Assignments
    case TeamsView = 'teams.view';
    case TeamsCreate = 'teams.create';
    case TeamsEdit = 'teams.edit';
    case TeamsDelete = 'teams.delete';
    case TeamsAssign = 'teams.assign';

    // CRM Contacts Management
    case ContactsView = 'contacts.view';
    case ContactsCreate = 'contacts.create';
    case ContactsEdit = 'contacts.edit';
    case ContactsDelete = 'contacts.delete';
    case ContactsAssign = 'contacts.assign';

    // CRM Leads Management
    case LeadsView = 'leads.view';
    case LeadsCreate = 'leads.create';
    case LeadsEdit = 'leads.edit';
    case LeadsDelete = 'leads.delete';
    case LeadsAssign = 'leads.assign';

    // Customer Records
    case CustomersView = 'customers.view';
    case CustomersCreate = 'customers.create';
    case CustomersEdit = 'customers.edit';
    case CustomersDelete = 'customers.delete';

    // Inspections & Field Operations
    case InspectionsView = 'inspections.view';
    case InspectionsCreate = 'inspections.create';
    case InspectionsEdit = 'inspections.edit';
    case InspectionsApprove = 'inspections.approve';

    // Marketing & Campaigns
    case CampaignsView = 'campaigns.view';
    case CampaignsCreate = 'campaigns.create';
    case CampaignsEdit = 'campaigns.edit';
    case CampaignsDelete = 'campaigns.delete';

    // Customer Support & Tickets
    case TicketsView = 'tickets.view';
    case TicketsCreate = 'tickets.create';
    case TicketsReply = 'tickets.reply';
    case TicketsResolve = 'tickets.resolve';

    // Reporting & Analytics
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    // Property & Estate Management
    case PropertiesView = 'properties.view';
    case PropertiesCreate = 'properties.create';
    case PropertiesEdit = 'properties.edit';
    case PropertiesDelete = 'properties.delete';

    // Opportunities & Deals Management
    case DealsView = 'deals.view';
    case DealsCreate = 'deals.create';
    case DealsEdit = 'deals.edit';
    case DealsDelete = 'deals.delete';

    // System Settings & Maintenance
    case SettingsView = 'settings.view';
    case SettingsEdit = 'settings.edit';
    case SystemHealth = 'system.health';

    /**
     * Retrieve the functional domain category for the permission.
     */
    public function group(): string
    {
        return match ($this) {
            self::UsersView, self::UsersCreate, self::UsersEdit, self::UsersDelete, self::UsersDisable,
            self::RolesView, self::RolesCreate, self::RolesEdit, self::RolesDelete,
            self::PermissionsView => 'Access Control & Users',

            self::TeamsView, self::TeamsCreate, self::TeamsEdit, self::TeamsDelete,
            self::TeamsAssign => 'Teams Management',

            self::ContactsView, self::ContactsCreate, self::ContactsEdit, self::ContactsDelete,
            self::ContactsAssign => 'CRM Contacts',

            self::LeadsView, self::LeadsCreate, self::LeadsEdit, self::LeadsDelete,
            self::LeadsAssign => 'Leads Management',

            self::CustomersView, self::CustomersCreate, self::CustomersEdit,
            self::CustomersDelete => 'Customers',

            self::InspectionsView, self::InspectionsCreate, self::InspectionsEdit,
            self::InspectionsApprove => 'Field Inspections',

            self::CampaignsView, self::CampaignsCreate, self::CampaignsEdit,
            self::CampaignsDelete => 'Marketing',

            self::TicketsView, self::TicketsCreate, self::TicketsReply,
            self::TicketsResolve => 'Customer Support',

            self::PropertiesView, self::PropertiesCreate, self::PropertiesEdit,
            self::PropertiesDelete => 'Property Management',

            self::DealsView, self::DealsCreate, self::DealsEdit,
            self::DealsDelete => 'Opportunities & Deals',

            self::ReportsView, self::ReportsExport => 'Reports & Analytics',

            self::SettingsView, self::SettingsEdit, self::SystemHealth => 'System Settings',
        };
    }

    /**
     * Retrieve a human-friendly description for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::UsersView => 'View Users',
            self::UsersCreate => 'Create Users',
            self::UsersEdit => 'Edit Users',
            self::UsersDelete => 'Delete Users',
            self::UsersDisable => 'Disable / Enable Users',
            self::RolesView => 'View Roles',
            self::RolesCreate => 'Create Roles',
            self::RolesEdit => 'Edit Roles',
            self::RolesDelete => 'Delete Roles',
            self::PermissionsView => 'View Permissions',

            self::TeamsView => 'View Teams',
            self::TeamsCreate => 'Create Teams',
            self::TeamsEdit => 'Edit Teams',
            self::TeamsDelete => 'Delete Teams',
            self::TeamsAssign => 'Assign Team Members & Leaders',

            self::ContactsView => 'View CRM Contacts',
            self::ContactsCreate => 'Create CRM Contacts',
            self::ContactsEdit => 'Edit CRM Contacts',
            self::ContactsDelete => 'Delete CRM Contacts',
            self::ContactsAssign => 'Assign Contacts to Reps',

            self::LeadsView => 'View Leads',
            self::LeadsCreate => 'Create Leads',
            self::LeadsEdit => 'Edit Leads',
            self::LeadsDelete => 'Delete Leads',
            self::LeadsAssign => 'Assign Leads',

            self::CustomersView => 'View Customers',
            self::CustomersCreate => 'Create Customers',
            self::CustomersEdit => 'Edit Customers',
            self::CustomersDelete => 'Delete Customers',

            self::InspectionsView => 'View Inspections',
            self::InspectionsCreate => 'Schedule Inspections',
            self::InspectionsEdit => 'Update Inspections',
            self::InspectionsApprove => 'Approve Inspections',

            self::CampaignsView => 'View Campaigns',
            self::CampaignsCreate => 'Create Campaigns',
            self::CampaignsEdit => 'Edit Campaigns',
            self::CampaignsDelete => 'Delete Campaigns',

            self::TicketsView => 'View Support Tickets',
            self::TicketsCreate => 'Open Support Tickets',
            self::TicketsReply => 'Reply to Tickets',
            self::TicketsResolve => 'Resolve Tickets',

            self::PropertiesView => 'View Properties & Estates',
            self::PropertiesCreate => 'Create Properties & Estates',
            self::PropertiesEdit => 'Edit Properties & Estates',
            self::PropertiesDelete => 'Delete Properties & Estates',

            self::DealsView => 'View Deals & Opportunities',
            self::DealsCreate => 'Create Deals & Opportunities',
            self::DealsEdit => 'Edit Deals & Opportunities',
            self::DealsDelete => 'Delete Deals & Opportunities',

            self::ReportsView => 'View Analytical Reports',
            self::ReportsExport => 'Export Report Datasets',

            self::SettingsView => 'View System Settings',
            self::SettingsEdit => 'Modify System Settings',
            self::SystemHealth => 'Probe Infrastructure Health',
        };
    }

    /**
     * Group all permissions by their domain categories.
     *
     * @return array<string, list<array{value: string, label: string}>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $case) {
            $group = $case->group();
            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        return $grouped;
    }

    /**
     * Get all available permission values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
