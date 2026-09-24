<?php

namespace Database\Factories;

use App\Models\SmartList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SmartList>
 */
class SmartListFactory extends Factory
{
    protected $model = SmartList::class;

    public function definition(): array
    {
        $name = fake()->words(3, true).' Prospects';

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->sentence(),
            'rule_groups' => [
                'logical_operator' => 'AND',
                'rules' => [
                    ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Lagos'],
                ],
            ],
            'icon' => 'Filter',
            'color' => 'indigo',
            'is_preset' => false,
            'is_favorite' => false,
            'cached_count' => 0,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function preset(): static
    {
        return $this->state(fn () => [
            'is_preset' => true,
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn () => [
            'is_favorite' => true,
        ]);
    }
}
