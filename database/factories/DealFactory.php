<?php

namespace Database\Factories;

use App\Enums\DealStatus;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        $status = fake()->randomElement([DealStatus::Open, DealStatus::Won, DealStatus::Lost]);
        $value = fake()->randomElement([15000000.00, 22000000.00, 35000000.00, 145000000.00, 295000000.00]);

        $lostReason = null;
        $actualCloseDate = null;
        if ($status === DealStatus::Lost) {
            $lostReason = fake()->randomElement([
                'Client opted for mainland location instead of island',
                'Budget constraints after currency devaluation',
                'Purchased alternative property from another developer',
                'Delayed mortgage approval from bank',
            ]);
            $actualCloseDate = fake()->dateTimeBetween('-30 days', 'now');
        } elseif ($status === DealStatus::Won) {
            $actualCloseDate = fake()->dateTimeBetween('-30 days', 'now');
        }

        return [
            'uuid' => (string) Str::uuid(),
            'title' => fake()->randomElement(['Grace Haven Plot Acquisition', 'Pacific Palms Duplex Purchase', 'Crown Heights Commercial Unit', 'Maitama Sky Penthouse Deal']),
            'contact_id' => Contact::factory(),
            'lead_id' => null,
            'property_id' => Property::factory(),
            'assigned_user_id' => null,
            'pipeline_id' => null,
            'pipeline_stage_id' => null,
            'deal_value' => $value,
            'currency' => 'NGN',
            'expected_close_date' => fake()->dateTimeBetween('now', '+60 days'),
            'actual_close_date' => $actualCloseDate,
            'status' => $status,
            'lost_reason' => $lostReason,
            'probability' => fake()->numberBetween(10, 90),
            'notes' => fake()->sentence(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => DealStatus::Open,
            'lost_reason' => null,
            'actual_close_date' => null,
        ]);
    }

    public function won(): static
    {
        return $this->state(fn () => [
            'status' => DealStatus::Won,
            'lost_reason' => null,
            'actual_close_date' => now(),
        ]);
    }

    public function lost(?string $reason = null): static
    {
        return $this->state(fn () => [
            'status' => DealStatus::Lost,
            'lost_reason' => $reason ?? 'Client decided not to proceed.',
            'actual_close_date' => now(),
        ]);
    }
}
