<?php

namespace Database\Factories;

use App\Enums\AutomationConditionOperator;
use App\Models\AutomationCondition;
use App\Models\AutomationWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutomationCondition>
 */
class AutomationConditionFactory extends Factory
{
    protected $model = AutomationCondition::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'workflow_id' => AutomationWorkflow::factory(),
            'field' => 'lead.score',
            'operator' => AutomationConditionOperator::GreaterThanOrEqual,
            'value' => 60,
            'logical_operator' => 'AND',
            'sort_order' => 0,
        ];
    }
}
