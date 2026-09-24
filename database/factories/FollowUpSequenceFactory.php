<?php

namespace Database\Factories;

use App\Enums\SequenceStatus;
use App\Models\FollowUpSequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FollowUpSequence>
 */
class FollowUpSequenceFactory extends Factory
{
    protected $model = FollowUpSequence::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(3, true).' Cadence',
            'description' => fake()->sentence(),
            'status' => SequenceStatus::Active,
            'trigger_type' => 'manual',
            'trigger_config' => null,
            'exit_on_deal_won' => true,
            'exit_on_reply' => false,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => SequenceStatus::Draft,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => SequenceStatus::Paused,
        ]);
    }
}
