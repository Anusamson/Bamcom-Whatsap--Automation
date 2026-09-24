<?php

namespace Database\Factories;

use App\Models\FollowUpSequence;
use App\Models\SequenceStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SequenceStep>
 */
class SequenceStepFactory extends Factory
{
    protected $model = SequenceStep::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'sequence_id' => FollowUpSequence::factory(),
            'step_number' => 1,
            'name' => 'Day 1 Follow-up',
            'delay_minutes' => 0,
            'delay_type' => 'minutes',
            'whatsapp_config' => [
                'message' => 'Hello {{contact.first_name}}, how can we assist you today?',
            ],
            'task_config' => null,
            'stage_change_config' => null,
            'tag_config' => null,
            'assignment_config' => null,
            'notification_config' => null,
            'applicability_rules' => null,
        ];
    }

    public function withDelay(int $minutes): static
    {
        return $this->state(fn () => [
            'delay_minutes' => $minutes,
        ]);
    }

    public function withTask(string $title = 'Follow up with lead'): static
    {
        return $this->state(fn () => [
            'task_config' => [
                'title' => $title,
                'due_in_days' => 1,
                'priority' => 'medium',
                'type' => 'follow_up',
            ],
        ]);
    }
}
