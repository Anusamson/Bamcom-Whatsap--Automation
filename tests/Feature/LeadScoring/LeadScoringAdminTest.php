<?php

namespace Tests\Feature\LeadScoring;

use App\Enums\LeadTemperature;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadScoringRule;
use App\Models\User;
use Database\Seeders\LeadScoringRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadScoringAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $unauthorizedUser;

    protected Contact $contact;

    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->adminUser->assignRole($superAdminRole);

        $this->unauthorizedUser = User::factory()->create(['role' => UserRole::CustomerSupport]);

        $this->seed(LeadScoringRuleSeeder::class);

        $this->contact = Contact::factory()->create();
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 0,
            'temperature' => LeadTemperature::Cold,
        ]);
    }

    public function test_admin_can_view_lead_scoring_rules_index(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('lead-scoring.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/LeadScoring/Index')
            ->has('rules', 10)
            ->has('temperatureScale', 3)
            ->has('stats')
        );
    }

    public function test_unauthorized_user_is_forbidden_from_lead_scoring(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('lead-scoring.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_update_rule_points_via_web(): void
    {
        $rule = LeadScoringRule::where('event_key', 'new_enquiry')->firstOrFail();

        $response = $this->actingAs($this->adminUser)
            ->put(route('lead-scoring.update', $rule), [
                'name' => 'First Enquiry Received',
                'points' => 12,
                'description' => 'Updated points for first contact',
                'allow_multiple' => false,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lead_scoring_rules', [
            'id' => $rule->id,
            'name' => 'First Enquiry Received',
            'points' => 12,
        ]);
    }

    public function test_admin_can_toggle_rule_active_state_via_web(): void
    {
        $rule = LeadScoringRule::where('event_key', 'new_enquiry')->firstOrFail();
        $this->assertTrue($rule->is_active);

        $response = $this->actingAs($this->adminUser)
            ->patch(route('lead-scoring.toggle', $rule));

        $response->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);

        // Toggle back to active
        $this->actingAs($this->adminUser)
            ->patch(route('lead-scoring.toggle', $rule));
        $this->assertTrue($rule->fresh()->is_active);
    }

    public function test_admin_can_create_and_delete_custom_rule_via_web(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('lead-scoring.store'), [
                'name' => 'Requested Lawyer Consultation',
                'event_key' => 'lawyer_consultation_requested',
                'category' => 'crm_event',
                'points' => 18,
                'description' => 'Buyer asked for deed verification legal counsel',
                'allow_multiple' => false,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lead_scoring_rules', [
            'event_key' => 'lawyer_consultation_requested',
            'points' => 18,
        ]);

        $customRule = LeadScoringRule::where('event_key', 'lawyer_consultation_requested')->firstOrFail();

        $deleteResponse = $this->actingAs($this->adminUser)
            ->delete(route('lead-scoring.destroy', $customRule));

        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('lead_scoring_rules', [
            'event_key' => 'lawyer_consultation_requested',
        ]);
    }

    public function test_admin_can_reset_rules_to_defaults_via_web(): void
    {
        $rule = LeadScoringRule::where('event_key', 'payment_process_request')->firstOrFail();
        $rule->update(['points' => 99]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('lead-scoring.reset-defaults'));

        $response->assertRedirect();
        $this->assertEquals(25, $rule->fresh()->points);
    }

    public function test_api_can_fetch_scoring_rules(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson(route('api.v1.lead-scoring.rules.index'));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(10, 'data');
    }

    public function test_api_can_update_scoring_rule(): void
    {
        Sanctum::actingAs($this->adminUser);

        $rule = LeadScoringRule::where('event_key', 'budget_supplied')->firstOrFail();

        $response = $this->putJson(route('api.v1.lead-scoring.rules.update', $rule), [
            'name' => $rule->name,
            'points' => 15,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $rule->id,
                    'points' => 15,
                ],
            ]);

        $this->assertEquals(15, $rule->fresh()->points);
    }

    public function test_api_can_record_score_event_on_lead(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson(route('api.v1.leads.score-event', $this->lead), [
            'event_key' => 'inspection_request',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'lead' => [
                        'id' => $this->lead->id,
                        'score' => 20,
                        'temperature' => 'cold',
                    ],
                ],
            ]);

        $this->assertEquals(20, $this->lead->fresh()->score);
    }

    public function test_api_can_fetch_lead_score_history(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Record two events
        $this->postJson(route('api.v1.leads.score-event', $this->lead), ['event_key' => 'new_enquiry']);
        $this->postJson(route('api.v1.leads.score-event', $this->lead), ['event_key' => 'inspection_request']);

        $response = $this->getJson(route('api.v1.leads.score-history', $this->lead));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(2, 'data.data');
    }
}
