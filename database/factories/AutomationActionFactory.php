<?php

namespace Database\Factories;

use App\Enums\AutomationActionType;
use App\Models\AutomationAction;
use App\Models\AutomationWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutomationAction>
 */
class AutomationActionFactory extends Factory
{
    protected $model = AutomationAction::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'workflow_id' => AutomationWorkflow::factory(),
            'action_type' => AutomationActionType::CreateTask,
            'action_config' => [
                'title' => 'Follow up with {{contact.name}}',
                'priority' => 'high',
                'type' => 'call',
            ],
            'delay_seconds' => 0,
            'delay_type' => 'none',
            'sort_order' => 0,
        ];
    }

    public function delayed(int $seconds = 300): static
    {
        return $this->state(fn () => [
            'delay_seconds' => $seconds,
            'delay_type' => 'seconds',
        ]);
    }

    public function type(AutomationActionType|string $type, array $config = []): static
    {
        return $this->state(fn () => [
            'action_type' => $type,
            'action_config' => $config ?: [
                'title' => 'Sample Action',
            ],
        ]);
    }
}
