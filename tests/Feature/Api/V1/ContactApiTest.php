<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);

        $this->user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->user->assignRole($superAdminRole);
    }

    public function test_api_can_list_contacts_with_pagination(): void
    {
        Sanctum::actingAs($this->user);

        Contact::factory()->count(18)->create();

        $response = $this->getJson(route('api.v1.contacts.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'uuid', 'first_name', 'phone', 'status', 'lead_source'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 18)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_api_can_create_contact_with_phone_normalization(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'first_name' => 'Funke',
            'last_name' => 'Akindele',
            'phone' => '070 3344 5566',
            'email' => 'funke@sceneone.ng',
            'location' => 'Amen Estate, Ibeju Lekki',
            'occupation' => 'Media Executive & Producer',
            'preferred_language' => 'yo',
            'lead_source' => LeadSource::WhatsApp->value,
            'status' => ContactStatus::Prospect->value,
        ];

        $response = $this->postJson(route('api.v1.contacts.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.phone', '+2347033445566')
            ->assertJsonPath('data.first_name', 'Funke');

        $this->assertDatabaseHas('contacts', [
            'phone' => '+2347033445566',
            'email' => 'funke@sceneone.ng',
        ]);
    }

    public function test_api_rejects_duplicate_phone_number(): void
    {
        Sanctum::actingAs($this->user);

        Contact::factory()->create([
            'phone' => '+2347033445566',
        ]);

        $payload = [
            'first_name' => 'Duplicate',
            'phone' => '07033445566', // Same number in local format
            'status' => ContactStatus::Lead->value,
        ];

        $response = $this->postJson(route('api.v1.contacts.store'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_api_can_show_contact(): void
    {
        Sanctum::actingAs($this->user);

        $contact = Contact::factory()->create();

        $response = $this->getJson(route('api.v1.contacts.show', $contact->id));

        $response->assertOk()
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.uuid', $contact->uuid);
    }

    public function test_api_can_update_contact(): void
    {
        Sanctum::actingAs($this->user);

        $contact = Contact::factory()->create([
            'phone' => '+2348011223344',
        ]);

        $payload = [
            'first_name' => 'Updated Name',
            'phone' => '080 1122 3344',
            'status' => ContactStatus::Customer->value,
        ];

        $response = $this->putJson(route('api.v1.contacts.update', $contact->id), $payload);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Updated Name');

        $this->assertEquals(ContactStatus::Customer, $contact->fresh()->status);
    }

    public function test_api_can_delete_contact(): void
    {
        Sanctum::actingAs($this->user);

        $contact = Contact::factory()->create();

        $response = $this->deleteJson(route('api.v1.contacts.destroy', $contact->id));

        $response->assertOk();
        $this->assertSoftDeleted($contact);
    }
}
