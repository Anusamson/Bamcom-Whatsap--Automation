<?php

namespace Tests\Feature\Leads;

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $agentUser;

    protected User $unauthorizedUser;

    protected Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Permissions
        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        // Super Admin role & user
        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->adminUser->assignRole($superAdminRole);

        // Sales Executive with lead permissions
        $salesRole = Role::firstOrCreate(['name' => UserRole::SalesExecutive->value, 'guard_name' => 'web']);
        $salesRole->givePermissionTo([
            PermissionEnum::LeadsView->value,
            PermissionEnum::LeadsCreate->value,
            PermissionEnum::LeadsEdit->value,
            PermissionEnum::LeadsAssign->value,
        ]);
        $this->agentUser = User::factory()->create(['role' => UserRole::SalesExecutive]);
        $this->agentUser->assignRole($salesRole);

        // Unauthorized user without lead permissions
        $this->unauthorizedUser = User::factory()->create(['role' => UserRole::CustomerSupport]);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Tunde',
            'last_name' => 'Bakare',
            'phone' => '+2348021112233',
        ]);
    }

    public function test_can_view_lead_listing_with_pagination_and_metrics(): void
    {
        Lead::factory()->count(18)->create();

        $response = $this->actingAs($this->adminUser)->get(route('leads.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leads/Index')
                ->has('leads.data', 15)
                ->has('metrics.total')
                ->has('metrics.hot')
                ->has('metrics.warm')
                ->has('metrics.cold')
            );
    }

    public function test_can_filter_leads_by_status(): void
    {
        Lead::factory()->create(['status' => LeadStatus::Won, 'title' => 'Won Deal Penthouse']);
        Lead::factory()->create(['status' => LeadStatus::New, 'title' => 'New Deal Apartment']);

        $response = $this->actingAs($this->adminUser)->get(route('leads.index', ['status' => LeadStatus::Won->value]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leads/Index')
                ->has('leads.data', 1)
                ->where('leads.data.0.status', LeadStatus::Won->value)
            );
    }

    public function test_can_filter_leads_by_temperature(): void
    {
        Lead::factory()->create(['temperature' => LeadTemperature::Hot, 'title' => 'Hot Lekki Land']);
        Lead::factory()->create(['temperature' => LeadTemperature::Cold, 'title' => 'Cold Commercial Shop']);

        $response = $this->actingAs($this->adminUser)->get(route('leads.index', ['temperature' => LeadTemperature::Hot->value]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leads/Index')
                ->has('leads.data', 1)
                ->where('leads.data.0.temperature', LeadTemperature::Hot->value)
            );
    }

    public function test_can_filter_leads_by_assigned_agent(): void
    {
        Lead::factory()->create(['assigned_user_id' => $this->agentUser->id, 'title' => 'Assigned to Agent']);
        Lead::factory()->create(['assigned_user_id' => $this->adminUser->id, 'title' => 'Assigned to Admin']);

        $response = $this->actingAs($this->adminUser)->get(route('leads.index', ['assigned_user_id' => $this->agentUser->id]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leads/Index')
                ->has('leads.data', 1)
                ->where('leads.data.0.assigned_user_id', $this->agentUser->id)
            );
    }

    public function test_can_filter_leads_by_source(): void
    {
        Lead::factory()->create(['lead_source' => LeadSource::WhatsApp, 'title' => 'WhatsApp Lead']);
        Lead::factory()->create(['lead_source' => LeadSource::Website, 'title' => 'Website Lead']);

        $response = $this->actingAs($this->adminUser)->get(route('leads.index', ['lead_source' => LeadSource::WhatsApp->value]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leads/Index')
                ->has('leads.data', 1)
                ->where('leads.data.0.lead_source', LeadSource::WhatsApp->value)
            );
    }

    public function test_can_filter_leads_by_property_interest(): void
    {
        Lead::factory()->create(['property_interest' => '4-Bedroom Terrace Duplex']);
        Lead::factory()->create(['property_interest' => '500sqm Residential Land']);

        $response = $this->actingAs($this->adminUser)->get(route('leads.index', ['property_interest' => 'Terrace']));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leads/Index')
                ->has('leads.data', 1)
            );
    }

    public function test_can_filter_leads_by_date_range(): void
    {
        $oldLead = Lead::factory()->create(['created_at' => Carbon::now()->subDays(20)]);
        $recentLead = Lead::factory()->create(['created_at' => Carbon::now()->subDays(2)]);

        $fromDate = Carbon::now()->subDays(5)->toDateString();
        $toDate = Carbon::now()->toDateString();

        $response = $this->actingAs($this->adminUser)->get(route('leads.index', [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leads/Index')
                ->has('leads.data', 1)
                ->where('leads.data.0.id', $recentLead->id)
            );
    }

    public function test_can_create_lead_and_score_is_calculated(): void
    {
        $payload = [
            'contact_id' => $this->contact->id,
            'title' => 'Eko Atlantic High-Rise Apartment',
            'lead_source' => LeadSource::WhatsApp->value,
            'status' => LeadStatus::Qualified->value,
            'temperature' => LeadTemperature::Hot->value,
            'purchase_timeline' => PurchaseTimeline::Immediate->value,
            'qualification_status' => QualificationStatus::Qualified->value,
            'budget_min' => 150000000,
            'budget_max' => 250000000,
            'preferred_location' => 'Eko Atlantic City, Victoria Island',
            'property_interest' => '3-Bedroom Luxury Apartment',
            'assigned_user_id' => $this->agentUser->id,
            'notes' => 'High net-worth diaspora investor seeking high rental yield.',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('leads.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('leads', [
            'contact_id' => $this->contact->id,
            'title' => 'Eko Atlantic High-Rise Apartment',
            'temperature' => LeadTemperature::Hot->value,
            'assigned_user_id' => $this->agentUser->id,
        ]);

        $lead = Lead::where('title', 'Eko Atlantic High-Rise Apartment')->first();
        $this->assertNotNull($lead);
        $this->assertEquals(100, $lead->score); // 20 + 35 + 25 + 20 + 10 = 110 => 100
        $this->assertNotNull($lead->uuid);
    }

    public function test_can_view_lead_detail_page(): void
    {
        $lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'assigned_user_id' => $this->agentUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('leads.show', $lead));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Leads/Show')
                ->where('lead.id', $lead->id)
                ->where('lead.contact.id', $this->contact->id)
            );
    }

    public function test_can_advance_lead_status(): void
    {
        $lead = Lead::factory()->create([
            'status' => LeadStatus::New,
        ]);

        $response = $this->actingAs($this->agentUser)->post(route('leads.status', $lead), [
            'status' => LeadStatus::ProposalSent->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => LeadStatus::ProposalSent->value,
        ]);
    }

    public function test_can_assign_sales_representative(): void
    {
        $lead = Lead::factory()->create([
            'assigned_user_id' => null,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('leads.assign', $lead), [
            'assigned_user_id' => $this->agentUser->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'assigned_user_id' => $this->agentUser->id,
        ]);
    }

    public function test_can_archive_lead_soft_deletes(): void
    {
        $lead = Lead::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('leads.destroy', $lead));

        $response->assertRedirect(route('leads.index'));
        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
    }

    public function test_unauthorized_user_cannot_view_or_create_leads(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('leads.index'));
        $response->assertForbidden();

        $response = $this->actingAs($this->unauthorizedUser)->post(route('leads.store'), [
            'contact_id' => $this->contact->id,
            'title' => 'Unauthorized Deal',
        ]);
        $response->assertForbidden();
    }
}
