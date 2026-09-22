<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PermissionEnum;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->user->assignRole($superAdminRole);

        $this->contact = Contact::factory()->create();
    }

    public function test_api_can_list_leads_with_pagination(): void
    {
        Sanctum::actingAs($this->user);

        Lead::factory()->count(18)->create();

        $response = $this->getJson(route('api.v1.leads.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'title',
                        'status',
                        'temperature',
                        'score',
                        'formatted_budget',
                        'contact',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 18)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_api_can_filter_leads_by_status_and_temperature(): void
    {
        Sanctum::actingAs($this->user);

        Lead::factory()->create([
            'status' => LeadStatus::Won,
            'temperature' => LeadTemperature::Hot,
        ]);
        Lead::factory()->create([
            'status' => LeadStatus::New,
            'temperature' => LeadTemperature::Cold,
        ]);

        $response = $this->getJson(route('api.v1.leads.index', [
            'status' => LeadStatus::Won->value,
            'temperature' => LeadTemperature::Hot->value,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', LeadStatus::Won->value)
            ->assertJsonPath('data.0.temperature', LeadTemperature::Hot->value);
    }

    public function test_api_can_create_lead_with_calculated_score(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'contact_id' => $this->contact->id,
            'title' => 'Victoria Garden City 5-Bed Mansion',
            'lead_source' => LeadSource::Website->value,
            'status' => LeadStatus::Contacted->value,
            'temperature' => LeadTemperature::Hot->value,
            'purchase_timeline' => PurchaseTimeline::Immediate->value,
            'qualification_status' => QualificationStatus::Qualified->value,
            'budget_min' => 180000000,
            'budget_max' => 220000000,
            'preferred_location' => 'VGC, Lekki',
            'property_interest' => '5-Bedroom Fully Detached Mansion',
        ];

        $response = $this->postJson(route('api.v1.leads.store'), $payload);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Victoria Garden City 5-Bed Mansion')
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.temperature', LeadTemperature::Hot->value);

        $this->assertDatabaseHas('leads', [
            'title' => 'Victoria Garden City 5-Bed Mansion',
            'temperature' => LeadTemperature::Hot->value,
        ]);
    }

    public function test_api_can_get_single_lead_by_uuid(): void
    {
        Sanctum::actingAs($this->user);

        $lead = Lead::factory()->create(['contact_id' => $this->contact->id]);

        $response = $this->getJson(route('api.v1.leads.show', $lead->uuid));

        $response->assertOk()
            ->assertJsonPath('data.id', $lead->id)
            ->assertJsonPath('data.uuid', $lead->uuid)
            ->assertJsonPath('data.title', $lead->title);
    }

    public function test_api_can_update_lead(): void
    {
        Sanctum::actingAs($this->user);

        $lead = Lead::factory()->create();

        $payload = [
            'title' => 'Updated Property Inquiry Title',
            'temperature' => LeadTemperature::Hot->value,
            'budget_max' => 95000000,
        ];

        $response = $this->putJson(route('api.v1.leads.update', $lead->uuid), $payload);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Property Inquiry Title')
            ->assertJsonPath('data.temperature', LeadTemperature::Hot->value);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'title' => 'Updated Property Inquiry Title',
        ]);
    }

    public function test_api_can_update_lead_status(): void
    {
        Sanctum::actingAs($this->user);

        $lead = Lead::factory()->create(['status' => LeadStatus::New]);

        $response = $this->postJson(route('api.v1.leads.status', $lead->uuid), [
            'status' => LeadStatus::Negotiation->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', LeadStatus::Negotiation->value);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => LeadStatus::Negotiation->value,
        ]);
    }

    public function test_api_can_assign_lead_representative(): void
    {
        Sanctum::actingAs($this->user);

        $agent = User::factory()->create();
        $lead = Lead::factory()->create(['assigned_user_id' => null]);

        $response = $this->postJson(route('api.v1.leads.assign', $lead->uuid), [
            'assigned_user_id' => $agent->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.assigned_user.id', $agent->id);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'assigned_user_id' => $agent->id,
        ]);
    }

    public function test_api_can_delete_lead(): void
    {
        Sanctum::actingAs($this->user);

        $lead = Lead::factory()->create();

        $response = $this->deleteJson(route('api.v1.leads.destroy', $lead->uuid));

        $response->assertOk()
            ->assertJsonPath('message', 'Sales opportunity archived successfully.');
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_unauthenticated_api_request_returns_401(): void
    {
        $response = $this->getJson(route('api.v1.leads.index'));

        $response->assertUnauthorized();
    }
}
