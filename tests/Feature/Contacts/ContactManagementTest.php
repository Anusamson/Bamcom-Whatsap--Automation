<?php

namespace Tests\Feature\Contacts;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Permissions
        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        // Super Admin / Admin role
        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => UserRole::Admin->value, 'guard_name' => 'web']);
        $adminRole->givePermissionTo([
            PermissionEnum::ContactsView->value,
            PermissionEnum::ContactsCreate->value,
            PermissionEnum::ContactsEdit->value,
            PermissionEnum::ContactsDelete->value,
            PermissionEnum::ContactsAssign->value,
        ]);

        $this->adminUser = User::factory()->create(['role' => UserRole::Admin]);
        $this->adminUser->assignRole($adminRole);

        // User without contacts permissions
        $this->regularUser = User::factory()->create(['role' => UserRole::SalesExecutive]);
    }

    public function test_admin_can_view_contact_list_with_pagination(): void
    {
        Contact::factory()->count(20)->create();

        $response = $this->actingAs($this->adminUser)->get(route('contacts.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Index')
                ->has('contacts.data', 15)
                ->has('metrics')
            );
    }

    public function test_user_can_search_contacts_by_name_and_phone(): void
    {
        $target = Contact::factory()->create([
            'first_name' => 'Oladipupo',
            'last_name' => 'Balogun',
            'phone' => '+2348099887766',
        ]);

        Contact::factory()->create([
            'first_name' => 'Chinedu',
            'last_name' => 'Eze',
            'phone' => '+2348011223344',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('contacts.index', [
            'search' => 'Oladipupo',
        ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Index')
                ->has('contacts.data', 1)
                ->where('contacts.data.0.id', $target->id)
            );
    }

    public function test_user_can_filter_contacts_by_status_and_source(): void
    {
        $customer = Contact::factory()->create([
            'status' => ContactStatus::Customer,
            'lead_source' => LeadSource::WhatsApp,
        ]);

        Contact::factory()->create([
            'status' => ContactStatus::Lead,
            'lead_source' => LeadSource::Website,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('contacts.index', [
            'status' => ContactStatus::Customer->value,
            'lead_source' => LeadSource::WhatsApp->value,
        ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Index')
                ->has('contacts.data', 1)
                ->where('contacts.data.0.id', $customer->id)
            );
    }

    public function test_user_can_create_contact_with_normalized_phone_and_uuid(): void
    {
        $payload = [
            'first_name' => 'Tunde',
            'last_name' => 'Bakare',
            'phone' => '080 3456 7890', // local formatted Nigerian number
            'email' => 'tunde.bakare@example.com',
            'location' => 'Ikoyi, Lagos',
            'occupation' => 'Architectural Consultant',
            'preferred_language' => 'en',
            'lead_source' => LeadSource::WhatsApp->value,
            'status' => ContactStatus::Lead->value,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('contacts.store'), $payload);

        $contact = Contact::where('email', 'tunde.bakare@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('+2348034567890', $contact->phone);
        $this->assertNotEmpty($contact->uuid);

        $response->assertRedirect(route('contacts.show', $contact->id));
    }

    public function test_duplicate_phone_numbers_are_prevented_on_create(): void
    {
        Contact::factory()->create([
            'phone' => '+2348034567890',
        ]);

        // Attempt to create another contact with same number in different local format
        $payload = [
            'first_name' => 'Duplicate',
            'last_name' => 'User',
            'phone' => '08034567890',
            'email' => 'duplicate@example.com',
            'status' => ContactStatus::Lead->value,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('contacts.store'), $payload);

        $response->assertSessionHasErrors(['phone']);
        $this->assertEquals(1, Contact::count());
    }

    public function test_user_can_update_contact_details(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Original',
            'phone' => '+2348011223344',
        ]);

        $payload = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => '080 1122 3344',
            'occupation' => 'Senior Partner',
            'status' => ContactStatus::Prospect->value,
        ];

        $response = $this->actingAs($this->adminUser)->put(route('contacts.update', $contact->id), $payload);

        $response->assertRedirect(route('contacts.show', $contact->id));
        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'first_name' => 'Updated',
            'occupation' => 'Senior Partner',
            'status' => ContactStatus::Prospect->value,
        ]);
    }

    public function test_prevents_updating_phone_to_one_owned_by_another_contact(): void
    {
        Contact::factory()->create(['phone' => '+2348011111111']);
        $contactB = Contact::factory()->create(['phone' => '+2348022222222']);

        $payload = [
            'first_name' => $contactB->first_name,
            'phone' => '08011111111', // Collides with Contact A
            'status' => ContactStatus::Lead->value,
        ];

        $response = $this->actingAs($this->adminUser)->put(route('contacts.update', $contactB->id), $payload);

        $response->assertSessionHasErrors(['phone']);
        $this->assertEquals('+2348022222222', $contactB->fresh()->phone);
    }

    public function test_can_view_contact_360_profile_shell(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('contacts.show', $contact->id));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Contacts/Show')
                ->where('contact.id', $contact->id)
                ->has('users')
                ->has('statuses')
            );
    }

    public function test_can_log_touchpoint_on_contact(): void
    {
        $contact = Contact::factory()->create([
            'last_contact_at' => null,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('contacts.touchpoint', $contact->id));

        $response->assertRedirect();
        $this->assertNotNull($contact->fresh()->last_contact_at);
    }

    public function test_can_soft_delete_contact(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('contacts.destroy', $contact->id));

        $response->assertRedirect(route('contacts.index'));
        $this->assertSoftDeleted($contact);
    }

    public function test_unauthorized_user_cannot_access_contacts(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('contacts.index'));

        $response->assertForbidden();
    }
}
