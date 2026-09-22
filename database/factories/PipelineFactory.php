<?php

namespace Database\Factories;

use App\Models\Pipeline;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pipeline>
 */
class PipelineFactory extends Factory
{
    protected $model = Pipeline::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => 'Real Estate Sales Pipeline',
            'description' => 'Comprehensive deal progression pipeline from discovery to title transfer.',
            'is_default' => true,
            'is_active' => true,
            'order_column' => 0,
        ];
    }
}
