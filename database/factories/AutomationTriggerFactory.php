<?php

namespace Database\Factories;

use App\Enums\AutomationTriggerType;
use App\Models\AutomationTrigger;
use App\Models\AutomationWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutomationTrigger>
 */
class AutomationTriggerFactory extends Factory
{
    protected $model = AutomationTrigger::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'workflow_id' => AutomationWorkflow::factory(),
            'trigger_type' => AutomationTriggerType::LeadScoreChanged,
            'filter_criteria' => null,
            'is_active' => true,
        ];
    }

    public function type(AutomationTriggerType|string $type): static
    {
        return $this->state(fn () => [
            'trigger_type' => $type,
        ]);
    }
}
