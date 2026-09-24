<?php

namespace Database\Factories;

use App\Models\Audience;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Audience>
 */
class AudienceFactory extends Factory
{
    protected $model = Audience::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(3, true).' Segment',
            'description' => fake()->sentence(),
            'filters' => [
                'temperatures' => ['hot', 'warm'],
            ],
            'cached_count' => 0,
            'created_by_user_id' => User::factory(),
        ];
    }
}
