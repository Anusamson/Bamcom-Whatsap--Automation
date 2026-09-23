<?php

namespace Tests\Feature\Automation;

use App\Enums\AutomationActionType;
use App\Enums\AutomationConditionOperator;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTriggerType;
use App\Enums\LeadTemperature;
use App\Events\LeadScoreChanged;
use App\Events\PipelineStageChanged;
use App\Jobs\ExecuteDelayedActionJob;
use App\Models\AutomationAction;
use App\Models\AutomationCondition;
use App\Models\AutomationRun;
use App\Models\AutomationRunLog;
use App\Models\AutomationTrigger;
use App\Models\AutomationWorkflow;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Automation\ActionExecutor;
use Database\Seeders\LeadScoringRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationWorkflowExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected Contact $contact;

    protected Lead $lead;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LeadScoringRuleSeeder::class);

        $this->user = User::factory()->create();
        $this->contact = Contact::factory()->create([
            'first_name' => 'Olumide',
            'phone' => '+2348030001111',
        ]);
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 50,
        ]);
    }

    public function test_crm_lead_score_changed_event_triggers_automation(): void
    {
        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'High Value Score Escalation',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::LeadScoreChanged,
            'is_active' => true,
        ]);

        AutomationCondition::factory()->create([
            'workflow_id' => $workflow->id,
            'field' => 'lead.score',
            'operator' => AutomationConditionOperator::GreaterThanOrEqual,
            'value' => 60,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::AddTag,
            'action_config' => ['tag' => 'Hot Lead Escalation'],
            'delay_seconds' => 0,
        ]);

        // Update lead and dispatch CRM domain event
        $this->lead->update(['score' => 65, 'temperature' => LeadTemperature::Hot]);

        LeadScoreChanged::dispatch(
            $this->lead,
            50,
            65,
            LeadTemperature::Warm,
            LeadTemperature::Hot,
            'inspection_completed',
            null,
            $this->user
        );

        $this->assertTrue($this->lead->fresh()->hasTag('Hot Lead Escalation'));
        $this->assertDatabaseHas('automation_runs', [
            'workflow_id' => $workflow->id,
            'subject_id' => $this->lead->id,
            'status' => AutomationRunStatus::Completed->value,
        ]);
    }

    public function test_crm_pipeline_stage_changed_event_triggers_automation(): void
    {
        $pipeline = Pipeline::factory()->create();
        $stageFrom = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'name' => 'New Inquiries']);
        $stageTo = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'name' => 'Qualified Prospects']);

        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Auto Task on Qualified Stage',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::PipelineStageChanged,
            'filter_criteria' => [
                'to_stage_id' => $stageTo->id,
            ],
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::CreateTask,
            'action_config' => [
                'title' => 'Send Silverstone brochure to {{contact.first_name}}',
                'priority' => 'high',
                'type' => 'follow_up',
            ],
            'delay_seconds' => 0,
        ]);

        // Dispatch PipelineStageChanged event
        PipelineStageChanged::dispatch(
            $this->lead,
            $stageFrom,
            $stageTo,
            $this->user
        );

        $this->assertDatabaseHas('tasks', [
            'title' => 'Send Silverstone brochure to Olumide',
            'contact_id' => $this->contact->id,
        ]);
    }

    public function test_delayed_automation_action_job_executes_on_queue(): void
    {
        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Delayed Tag Workflow',
            'is_active' => true,
            'status' => 'active',
        ]);

        $action = AutomationAction::factory()->delayed(60)->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::AddTag,
            'action_config' => ['tag' => 'Delayed Qualification'],
        ]);

        $run = AutomationRun::create([
            'workflow_id' => $workflow->id,
            'subject_type' => Lead::class,
            'subject_id' => $this->lead->id,
            'status' => AutomationRunStatus::Running,
            'started_at' => now(),
        ]);

        $runLog = AutomationRunLog::create([
            'run_id' => $run->id,
            'action_id' => $action->id,
            'action_type' => $action->action_type,
            'status' => 'delayed',
            'input_payload' => $action->action_config,
            'scheduled_at' => now()->addSeconds(60),
        ]);

        // Process delayed job directly using ActionExecutor
        $job = new ExecuteDelayedActionJob($runLog->id);
        $job->handle(app(ActionExecutor::class));

        $this->assertTrue($this->lead->fresh()->hasTag('Delayed Qualification'));
        $this->assertEquals('success', $runLog->fresh()->status->value);
        $this->assertEquals(AutomationRunStatus::Completed, $run->fresh()->status);
    }
}
