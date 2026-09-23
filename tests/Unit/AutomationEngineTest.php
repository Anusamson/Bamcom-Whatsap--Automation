<?php

namespace Tests\Unit;

use App\Enums\AutomationActionType;
use App\Enums\AutomationConditionOperator;
use App\Enums\AutomationRunStatus;
use App\Enums\AutomationTriggerType;
use App\Jobs\ExecuteDelayedActionJob;
use App\Models\AutomationAction;
use App\Models\AutomationCondition;
use App\Models\AutomationTrigger;
use App\Models\AutomationWorkflow;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\Automation\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected AutomationEngine $engine;

    protected Contact $contact;

    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(AutomationEngine::class);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Bolanle',
            'phone' => '+2348098765432',
        ]);

        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 65,
        ]);
    }

    public function test_dispatches_workflow_and_executes_immediate_action(): void
    {
        $workflow = AutomationWorkflow::factory()->create([
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
            'action_config' => ['tag' => 'Warm Lead'],
            'delay_seconds' => 0,
        ]);

        $runs = $this->engine->dispatch('lead_score_changed', $this->lead, [
            'score' => 65,
        ]);

        $this->assertCount(1, $runs);
        $run = $runs->first();
        $this->assertEquals(AutomationRunStatus::Completed, $run->status);
        $this->assertTrue($this->lead->fresh()->hasTag('Warm Lead'));
    }

    public function test_dispatches_delayed_action_to_queue(): void
    {
        Queue::fake();

        $workflow = AutomationWorkflow::factory()->create([
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::InspectionScheduled,
            'is_active' => true,
        ]);

        AutomationAction::factory()->delayed(600)->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::CreateTask,
            'action_config' => ['title' => 'Prepare inspection confirmation documents'],
        ]);

        $runs = $this->engine->dispatch('inspection_scheduled', $this->contact);

        $this->assertCount(1, $runs);
        $run = $runs->first();

        // Run has delayed actions so status remains running until queue job finishes
        $this->assertEquals(AutomationRunStatus::Running, $run->status);
        $this->assertDatabaseHas('automation_run_logs', [
            'run_id' => $run->id,
            'status' => 'delayed',
        ]);

        Queue::assertPushed(ExecuteDelayedActionJob::class);
    }

    public function test_prevents_duplicate_runs_when_single_run_rule_active(): void
    {
        $workflow = AutomationWorkflow::factory()->singleRunOnly()->create([
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::LeadScoreChanged,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::AddTag,
            'action_config' => ['tag' => 'One-Time Tag'],
            'delay_seconds' => 0,
        ]);

        // First execution -> succeeds
        $runs1 = $this->engine->dispatch('lead_score_changed', $this->lead);
        $this->assertCount(1, $runs1);

        // Second execution for same subject -> blocked by single run rule
        $runs2 = $this->engine->dispatch('lead_score_changed', $this->lead);
        $this->assertCount(0, $runs2);
    }

    public function test_prevents_duplicate_runs_within_cooldown_window(): void
    {
        $workflow = AutomationWorkflow::factory()->withCooldown(3600)->create([
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::LeadScoreChanged,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::AddTag,
            'action_config' => ['tag' => 'Cooldown Tag'],
            'delay_seconds' => 0,
        ]);

        // First execution
        $runs1 = $this->engine->dispatch('lead_score_changed', $this->lead);
        $this->assertCount(1, $runs1);

        // Second execution immediately after -> blocked by cooldown
        $runs2 = $this->engine->dispatch('lead_score_changed', $this->lead);
        $this->assertCount(0, $runs2);
    }

    public function test_skips_workflow_when_conditions_fail(): void
    {
        $workflow = AutomationWorkflow::factory()->create([
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::LeadScoreChanged,
            'is_active' => true,
        ]);

        // Condition requires score >= 90, but lead score is 65
        AutomationCondition::factory()->create([
            'workflow_id' => $workflow->id,
            'field' => 'lead.score',
            'operator' => AutomationConditionOperator::GreaterThanOrEqual,
            'value' => 90,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::AddTag,
            'action_config' => ['tag' => 'Super Hot'],
        ]);

        $runs = $this->engine->dispatch('lead_score_changed', $this->lead);
        $this->assertCount(0, $runs);
        $this->assertFalse($this->lead->fresh()->hasTag('Super Hot'));
    }
}
