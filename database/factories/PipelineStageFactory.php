<?php

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    protected $model = PipelineStage::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Discovery Stage',
            'Proposal Stage',
            'Verification Stage',
            'Closing Stage',
        ]);

        return [
            'uuid' => (string) Str::uuid(),
            'pipeline_id' => Pipeline::factory(),
            'name' => $name,
            'slug' => Str::slug($name, '_'),
            'order_column' => fake()->numberBetween(1, 10),
            'color' => 'blue',
            'probability' => fake()->numberBetween(10, 90),
            'is_won' => false,
            'is_lost' => false,
        ];
    }
}
