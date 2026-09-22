<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Lead>
     */
    protected $model = Lead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $budgetMin = fake()->randomElement([25000000, 50000000, 80000000, 120000000]);
        $budgetMax = $budgetMin + fake()->randomElement([20000000, 40000000, 70000000]);

        return [
            'uuid' => (string) Str::uuid(),
            'contact_id' => Contact::factory(),
            'assigned_user_id' => null,
            'title' => fake()->randomElement([
                '4-Bedroom Terrace Duplex - Lekki Phase 1',
                'Commercial Waterfront Land - Epe',
                'Luxury Penthouse Suite - Victoria Island',
                'Smart Serviced Villa - Ikoyi',
                'Mixed-Use Commercial Plot - Maitama Abuja',
            ]),
            'lead_source' => fake()->randomElement(LeadSource::cases()),
            'status' => fake()->randomElement([LeadStatus::New, LeadStatus::Contacted, LeadStatus::Qualified, LeadStatus::Negotiation]),
            'temperature' => fake()->randomElement(LeadTemperature::cases()),
            'score' => fake()->numberBetween(35, 95),
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
            'budget_range' => sprintf('₦%s - ₦%s', number_format($budgetMin), number_format($budgetMax)),
            'purchase_timeline' => fake()->randomElement(PurchaseTimeline::cases()),
            'preferred_location' => fake()->randomElement(['Lekki Phase 1, Lagos', 'Ikoyi, Lagos', 'Victoria Island, Lagos', 'Epe, Lagos', 'Maitama, Abuja']),
            'property_interest' => fake()->randomElement(['4-Bedroom Terrace Duplex', 'Commercial Land', 'Luxury Penthouse', 'Waterfront Villa', 'Residential Plot']),
            'qualification_status' => fake()->randomElement([QualificationStatus::Qualified, QualificationStatus::InReview, QualificationStatus::Unqualified]),
            'notes' => fake()->sentence(),
        ];
    }

    /**
     * Hot lead state.
     */
    public function hot(): static
    {
        return $this->state(fn (array $attributes) => [
            'temperature' => LeadTemperature::Hot,
            'purchase_timeline' => PurchaseTimeline::Immediate,
            'score' => 90,
        ]);
    }

    /**
     * Warm lead state.
     */
    public function warm(): static
    {
        return $this->state(fn (array $attributes) => [
            'temperature' => LeadTemperature::Warm,
            'purchase_timeline' => PurchaseTimeline::OneToThreeMonths,
            'score' => 70,
        ]);
    }

    /**
     * Cold lead state.
     */
    public function cold(): static
    {
        return $this->state(fn (array $attributes) => [
            'temperature' => LeadTemperature::Cold,
            'purchase_timeline' => PurchaseTimeline::Flexible,
            'score' => 35,
        ]);
    }

    /**
     * Qualified state.
     */
    public function qualified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::Qualified,
            'qualification_status' => QualificationStatus::Qualified,
        ]);
    }

    /**
     * Closed won state.
     */
    public function won(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LeadStatus::Won,
            'converted_at' => now(),
        ]);
    }

    /**
     * Assign to specific agent.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_user_id' => $user->id,
        ]);
    }
}
