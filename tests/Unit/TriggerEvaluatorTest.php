<?php

namespace Tests\Unit;

use App\Enums\AutomationConditionOperator;
use App\Enums\AutomationTriggerType;
use App\Models\AutomationCondition;
use App\Models\AutomationTrigger;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\Automation\TriggerEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriggerEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    protected TriggerEvaluator $evaluator;

    protected Contact $contact;

    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = app(TriggerEvaluator::class);
        $this->contact = Contact::factory()->create([
            'first_name' => 'Adewale',
            'location' => 'Lekki Phase 1',
            'phone' => '+2348012345678',
        ]);
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 75,
            'budget_max' => 50000000,
        ]);
    }

    public function test_matches_trigger_when_type_matches_and_active(): void
    {
        $trigger = new AutomationTrigger([
            'trigger_type' => AutomationTriggerType::LeadScoreChanged,
            'is_active' => true,
        ]);

        $this->assertTrue($this->evaluator->matchesTrigger($trigger, 'lead_score_changed'));
        $this->assertFalse($this->evaluator->matchesTrigger($trigger, 'pipeline_stage_changed'));
    }

    public function test_does_not_match_inactive_trigger(): void
    {
        $trigger = new AutomationTrigger([
            'trigger_type' => AutomationTriggerType::LeadScoreChanged,
            'is_active' => false,
        ]);

        $this->assertFalse($this->evaluator->matchesTrigger($trigger, 'lead_score_changed'));
    }

    public function test_matches_trigger_filter_criteria(): void
    {
        $trigger = new AutomationTrigger([
            'trigger_type' => AutomationTriggerType::PipelineStageChanged,
            'is_active' => true,
            'filter_criteria' => [
                'to_stage_id' => 3,
                'pipeline_id' => 1,
            ],
        ]);

        $matchingContext = ['to_stage_id' => 3, 'pipeline_id' => 1, 'other' => 'abc'];
        $nonMatchingContext = ['to_stage_id' => 2, 'pipeline_id' => 1];

        $this->assertTrue($this->evaluator->matchesTrigger($trigger, 'pipeline_stage_changed', $matchingContext));
        $this->assertFalse($this->evaluator->matchesTrigger($trigger, 'pipeline_stage_changed', $nonMatchingContext));
    }

    public function test_evaluates_condition_operators(): void
    {
        // Greater than or equal: lead.score >= 70 (true, score is 75)
        $c1 = new AutomationCondition([
            'field' => 'lead.score',
            'operator' => AutomationConditionOperator::GreaterThanOrEqual,
            'value' => 70,
            'logical_operator' => 'AND',
        ]);
        $this->assertTrue($this->evaluator->evaluateConditions([$c1], $this->lead));

        // Greater than: lead.score > 80 (false)
        $c2 = new AutomationCondition([
            'field' => 'lead.score',
            'operator' => AutomationConditionOperator::GreaterThan,
            'value' => 80,
            'logical_operator' => 'AND',
        ]);
        $this->assertFalse($this->evaluator->evaluateConditions([$c2], $this->lead));

        // Contains: contact.location contains 'Lekki' (true)
        $c3 = new AutomationCondition([
            'field' => 'contact.location',
            'operator' => AutomationConditionOperator::Contains,
            'value' => 'Lekki',
            'logical_operator' => 'AND',
        ]);
        $this->assertTrue($this->evaluator->evaluateConditions([$c3], $this->lead));

        // In: contact.first_name in ['Adewale', 'Babajide'] (true)
        $c4 = new AutomationCondition([
            'field' => 'contact.first_name',
            'operator' => AutomationConditionOperator::In,
            'value' => ['Adewale', 'Babajide'],
            'logical_operator' => 'AND',
        ]);
        $this->assertTrue($this->evaluator->evaluateConditions([$c4], $this->contact));
    }

    public function test_evaluates_and_versus_or_logic_gates(): void
    {
        // Cond 1: score >= 70 (true)
        $c1 = new AutomationCondition([
            'field' => 'lead.score',
            'operator' => AutomationConditionOperator::GreaterThanOrEqual,
            'value' => 70,
            'logical_operator' => 'AND',
        ]);

        // Cond 2: location contains 'Abuja' (false)
        $c2 = new AutomationCondition([
            'field' => 'contact.location',
            'operator' => AutomationConditionOperator::Contains,
            'value' => 'Abuja',
            'logical_operator' => 'AND', // AND gate => true && false => false
        ]);

        $this->assertFalse($this->evaluator->evaluateConditions([$c1, $c2], $this->lead));

        // Switch Cond 2 to OR gate => true || false => true
        $c2->logical_operator = 'OR';
        $this->assertTrue($this->evaluator->evaluateConditions([$c1, $c2], $this->lead));
    }
}
