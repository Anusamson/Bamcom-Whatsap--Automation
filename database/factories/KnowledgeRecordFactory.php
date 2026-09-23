<?php

namespace Database\Factories;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeRecord>
 */
class KnowledgeRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = KnowledgeRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var KnowledgeCategory $category */
        $category = fake()->randomElement(KnowledgeCategory::cases());

        return [
            'uuid' => (string) Str::uuid(),
            'title' => fake()->sentence(5),
            'category' => $category,
            'content' => fake()->paragraphs(3, true),
            'status' => KnowledgeStatus::Active,
            'priority' => fake()->numberBetween(0, 100),
            'effective_date' => now()->subDays(fake()->numberBetween(1, 30)),
            'expiration_date' => now()->addDays(fake()->numberBetween(30, 365)),
            'keywords' => [fake()->word(), fake()->word(), fake()->word()],
            'metadata' => ['department' => 'Sales', 'tags' => ['real-estate', 'grounding']],
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Force the record to be explicitly active and currently valid.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KnowledgeStatus::Active,
            'effective_date' => now()->subDay(),
            'expiration_date' => now()->addMonth(),
        ]);
    }

    /**
     * Force the record to be in draft state.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KnowledgeStatus::Draft,
        ]);
    }

    /**
     * Force the record to be archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KnowledgeStatus::Archived,
        ]);
    }

    /**
     * Force the record to be expired in the past.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KnowledgeStatus::Active,
            'effective_date' => now()->subMonths(2),
            'expiration_date' => now()->subDay(),
        ]);
    }

    /**
     * Force the record to have an effective date in the future.
     */
    public function futureEffective(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KnowledgeStatus::Active,
            'effective_date' => now()->addWeeks(2),
            'expiration_date' => now()->addMonths(6),
        ]);
    }

    /**
     * Target a specific knowledge category.
     */
    public function forCategory(KnowledgeCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
        ]);
    }
}
