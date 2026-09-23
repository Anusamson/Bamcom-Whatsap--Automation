<?php

namespace Tests\Feature\Inspections;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\InspectionStatus;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\LeadScoringRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $agentUser;

    protected User $unauthorizedUser;

    protected Contact $contact;

    protected Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LeadScoringRuleSeeder::class);

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        // Super Admin user
        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->adminUser->assignRole($superAdminRole);

        // Field Agent with inspection permissions
        $agentRole = Role::firstOrCreate(['name' => UserRole::SalesExecutive->value, 'guard_name' => 'web']);
        $agentRole->givePermissionTo([
            PermissionEnum::InspectionsView->value,
            PermissionEnum::InspectionsCreate->value,
            PermissionEnum::InspectionsEdit->value,
            PermissionEnum::InspectionsApprove->value,
        ]);
        $this->agentUser = User::factory()->create(['role' => UserRole::SalesExecutive]);
        $this->agentUser->assignRole($agentRole);

        // Unauthorized user without inspection permissions
        $this->unauthorizedUser = User::factory()->create(['role' => UserRole::CustomerSupport]);

        $this->contact = Contact::factory()->create();
        $this->property = Property::factory()->create(['title' => 'Epe Lagoon View Estate']);
    }

    public function test_authorized_user_can_view_inspections_list_and_calendar(): void
    {
        Inspection::factory()->count(5)->create([
            'contact_id' => $this->contact->id,
            'representative_id' => $this->agentUser->id,
        ]);

        $response = $this->actingAs($this->agentUser)
            ->get(route('inspections.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Inspections/Index')
            ->has('inspections.data', 5)
            ->has('stats')
            ->has('calendarEvents')
            ->has('statuses')
        );

        // Test calendar JSON endpoint
        $calendarResponse = $this->actingAs($this->agentUser)
            ->getJson(route('inspections.calendar'));

        $calendarResponse->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_unauthorized_user_is_forbidden_from_inspections(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('inspections.index'));

        $response->assertForbidden();
    }

    public function test_user_can_schedule_inspection_via_web(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');

        $response = $this->actingAs($this->agentUser)
            ->post(route('inspections.store'), [
                'contact_id' => $this->contact->id,
                'property_id' => $this->property->id,
                'estate_name' => 'Epe Lagoon View Estate',
                'representative_id' => $this->agentUser->id,
                'inspection_date' => $date,
                'inspection_time' => '10:00 AM',
                'meeting_point' => 'Lekki Phase 1 Headquarters',
                'customer_notes' => 'Requires 3 seats in escort vehicle',
            ]);

        $this->assertDatabaseHas('inspections', [
            'contact_id' => $this->contact->id,
            'property_id' => $this->property->id,
            'representative_id' => $this->agentUser->id,
            'inspection_date' => $date,
            'inspection_time' => '10:00 AM',
            'status' => 'scheduled',
        ]);

        $inspection = Inspection::where('contact_id', $this->contact->id)->firstOrFail();
        $response->assertRedirect(route('inspections.show', $inspection->id));
    }

    public function test_conflict_returns_validation_error_on_web(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');

        // Existing booking
        Inspection::factory()->create([
            'representative_id' => $this->agentUser->id,
            'inspection_date' => $date,
            'inspection_time' => '10:00 AM',
            'status' => InspectionStatus::Scheduled,
        ]);

        $secondContact = Contact::factory()->create();

        // Attempting overlapping assignment
        $response = $this->actingAs($this->agentUser)
            ->post(route('inspections.store'), [
                'contact_id' => $secondContact->id,
                'representative_id' => $this->agentUser->id,
                'inspection_date' => $date,
                'inspection_time' => '10:00 AM',
            ]);

        $response->assertSessionHasErrors(['representative_id']);
    }

    public function test_user_can_update_inspection_status_and_outcome(): void
    {
        $inspection = Inspection::factory()->create([
            'contact_id' => $this->contact->id,
            'representative_id' => $this->agentUser->id,
            'status' => InspectionStatus::Scheduled,
        ]);

        $response = $this->actingAs($this->agentUser)
            ->post(route('inspections.status', $inspection->id), [
                'status' => 'completed',
                'outcome' => 'Client verified survey plan, selected plot 15, and committed to 30% initial deposit.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'status' => 'completed',
            'outcome' => 'Client verified survey plan, selected plot 15, and committed to 30% initial deposit.',
        ]);
    }

    public function test_user_can_reschedule_inspection(): void
    {
        $inspection = Inspection::factory()->create([
            'contact_id' => $this->contact->id,
            'representative_id' => $this->agentUser->id,
            'inspection_date' => now()->addDays(1)->format('Y-m-d'),
            'inspection_time' => '10:00 AM',
        ]);

        $newDate = now()->addDays(4)->format('Y-m-d');

        $response = $this->actingAs($this->agentUser)
            ->post(route('inspections.reschedule', $inspection->id), [
                'inspection_date' => $newDate,
                'inspection_time' => '02:00 PM',
                'reason' => 'Client postponed due to rain storm',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'inspection_date' => $newDate,
            'inspection_time' => '02:00 PM',
            'status' => 'rescheduled',
        ]);
    }

    public function test_user_can_reassign_representative(): void
    {
        $inspection = Inspection::factory()->create([
            'contact_id' => $this->contact->id,
            'representative_id' => null,
            'status' => InspectionStatus::Scheduled,
        ]);

        $newRep = User::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->post(route('inspections.assign', $inspection->id), [
                'representative_id' => $newRep->id,
            ]);

        $response->assertRedirect();
        $this->assertEquals($newRep->id, $inspection->fresh()->representative_id);
    }

    public function test_inbox_integration_creates_inspection_record(): void
    {
        // Add conversation permissions to agent
        $this->agentUser->givePermissionTo(PermissionEnum::ConversationsManage->value);

        $conversation = Conversation::create([
            'contact_id' => $this->contact->id,
            'phone_number' => $this->contact->phone,
            'status' => ConversationStatus::Open,
            'mode' => ConversationMode::Human,
            'channel' => 'whatsapp',
            'assigned_user_id' => $this->agentUser->id,
            'last_message_at' => now(),
        ]);

        $date = now()->addDays(3)->format('Y-m-d');

        $response = $this->actingAs($this->agentUser)
            ->post(route('inbox.inspections.store', $conversation->id), [
                'estate_name' => 'Silverstone Heights Lekki',
                'inspection_date' => $date,
                'inspection_time' => '10:00 AM',
                'inspector_id' => $this->agentUser->id,
                'notes' => 'Booked directly from conversation inbox thread',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('inspections', [
            'contact_id' => $this->contact->id,
            'estate_name' => 'Silverstone Heights Lekki',
            'inspection_date' => $date,
            'inspection_time' => '10:00 AM',
            'representative_id' => $this->agentUser->id,
            'status' => 'scheduled',
        ]);
    }
}
