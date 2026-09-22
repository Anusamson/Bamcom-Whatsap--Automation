<?php

namespace Tests\Feature\Pipelines;

use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Events\PipelineStageChanged;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PipelineKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $salesExec;

    protected User $unauthorizedUser;

    protected Pipeline $pipeline;

    protected PipelineStage $stageNew;

    protected PipelineStage $stageInterested;

    protected PipelineStage $stageWon;

    protected PipelineStage $stageLost;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->adminUser->assignRole($superAdminRole);

        $salesRole = Role::firstOrCreate(['name' => UserRole::SalesExecutive->value, 'guard_name' => 'web']);
        $salesRole->givePermissionTo([
            PermissionEnum::LeadsView->value,
            PermissionEnum::LeadsEdit->value,
        ]);
        $this->salesExec = User::factory()->create(['role' => UserRole::SalesExecutive]);
        $this->salesExec->assignRole($salesRole);

        $this->unauthorizedUser = User::factory()->create(['role' => UserRole::CustomerSupport]);

        // Pipeline and Stages
        $this->pipeline = Pipeline::factory()->create([
            'name' => 'Bamcom Sales Pipeline',
            'is_default' => true,
        ]);

        $this->stageNew = PipelineStage::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'New Lead',
            'order_column' => 1,
            'probability' => 10,
        ]);

        $this->stageInterested = PipelineStage::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Interested',
            'order_column' => 4,
            'probability' => 50,
        ]);

        $this->stageWon = PipelineStage::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Won',
            'order_column' => 9,
            'probability' => 100,
            'is_won' => true,
        ]);

        $this->stageLost = PipelineStage::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Lost',
            'order_column' => 10,
            'probability' => 0,
            'is_lost' => true,
        ]);
    }

    public function test_can_view_kanban_board_with_columns_and_leads(): void
    {
        $contact = Contact::factory()->create();
        Lead::factory()->create([
            'contact_id' => $contact->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageNew->id,
            'title' => 'Victoria Island Penthouse',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('pipelines.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pipelines/Kanban')
                ->has('board.columns', 4)
                ->where('board.pipeline.name', 'Bamcom Sales Pipeline')
                ->has('board.summary.total_leads')
            );
    }

    public function test_authorized_user_can_move_lead_stage_and_creates_activity_and_dispatches_event(): void
    {
        Event::fake([PipelineStageChanged::class]);

        $lead = Lead::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageNew->id,
            'status' => LeadStatus::New,
        ]);

        $response = $this->actingAs($this->salesExec)->post(route('leads.stage.move', $lead), [
            'pipeline_stage_id' => $this->stageInterested->id,
        ]);

        $response->assertRedirect();

        // 1. Lead stage updated
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'pipeline_stage_id' => $this->stageInterested->id,
        ]);

        // 2. Activity record created
        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'user_id' => $this->salesExec->id,
            'activity_type' => 'stage_change',
        ]);

        $activity = Activity::where('lead_id', $lead->id)->first();
        $this->assertNotNull($activity);
        $this->assertStringContainsString('New Lead', $activity->description);
        $this->assertStringContainsString('Interested', $activity->description);
        $this->assertEquals($this->stageNew->id, $activity->properties['old_stage_id']);
        $this->assertEquals($this->stageInterested->id, $activity->properties['new_stage_id']);

        // 3. Event dispatched
        Event::assertDispatched(PipelineStageChanged::class, function (PipelineStageChanged $event) use ($lead) {
            return $event->lead->id === $lead->id
                && $event->previousStage?->id === $this->stageNew->id
                && $event->newStage->id === $this->stageInterested->id
                && $event->causer?->id === $this->salesExec->id;
        });
    }

    public function test_moving_to_won_stage_synchronizes_status_and_converted_at(): void
    {
        Event::fake([PipelineStageChanged::class]);

        $lead = Lead::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageInterested->id,
            'status' => LeadStatus::Qualified,
            'converted_at' => null,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('leads.stage.move', $lead), [
            'pipeline_stage_id' => $this->stageWon->id,
        ]);

        $response->assertRedirect();

        $lead->refresh();
        $this->assertEquals($this->stageWon->id, $lead->pipeline_stage_id);
        $this->assertEquals(LeadStatus::Won, $lead->status);
        $this->assertNotNull($lead->converted_at);

        Event::assertDispatched(PipelineStageChanged::class);
    }

    public function test_moving_to_lost_stage_synchronizes_status(): void
    {
        $lead = Lead::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageInterested->id,
            'status' => LeadStatus::Negotiation,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('leads.stage.move', $lead), [
            'pipeline_stage_id' => $this->stageLost->id,
        ]);

        $response->assertRedirect();

        $lead->refresh();
        $this->assertEquals($this->stageLost->id, $lead->pipeline_stage_id);
        $this->assertEquals(LeadStatus::Lost, $lead->status);
    }

    public function test_unauthorized_user_cannot_move_lead_stage(): void
    {
        $lead = Lead::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageNew->id,
        ]);

        $response = $this->actingAs($this->unauthorizedUser)->post(route('leads.stage.move', $lead), [
            'pipeline_stage_id' => $this->stageInterested->id,
        ]);

        $response->assertForbidden();
    }

    public function test_can_filter_kanban_by_agent_and_temperature(): void
    {
        Lead::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageNew->id,
            'assigned_user_id' => $this->salesExec->id,
            'temperature' => LeadTemperature::Hot,
            'title' => 'Hot Exec Opportunity',
        ]);

        Lead::factory()->create([
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageNew->id,
            'assigned_user_id' => $this->adminUser->id,
            'temperature' => LeadTemperature::Cold,
            'title' => 'Cold Admin Opportunity',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('pipelines.index', [
            'agent' => $this->salesExec->id,
            'temperature' => LeadTemperature::Hot->value,
        ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Pipelines/Kanban')
                ->where('board.summary.total_leads', 1)
            );
    }
}
