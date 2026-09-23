<?php

namespace Database\Factories;

use App\Models\AutomationWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutomationWorkflow>
 */
class AutomationWorkflowFactory extends Factory
{
    protected $model = AutomationWorkflow::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(3, true).' Workflow',
            'description' => fake()->sentence(),
            'status' => 'active',
            'is_active' => true,
            'allow_multiple_runs_per_subject' => true,
            'prevent_duplicate_window_seconds' => null,
            'created_by_id' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'status' => 'inactive',
        ]);
    }

    public function singleRunOnly(): static
    {
        return $this->state(fn () => [
            'allow_multiple_runs_per_subject' => false,
        ]);
    }

    public function withCooldown(int $seconds = 3600): static
    {
        return $this->state(fn () => [
            'prevent_duplicate_window_seconds' => $seconds,
        ]);
    }
}
