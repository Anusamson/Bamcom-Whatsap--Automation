<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Models\Contact;
use App\Models\Inspection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    protected $model = Inspection::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'contact_id' => Contact::factory(),
            'lead_id' => null,
            'property_id' => null,
            'estate_name' => fake()->randomElement([
                'Silverstone Heights, Lekki Phase 1',
                'Epe Lagoon View Estate, Epe',
                'Ocean Crest Gardens, Ibeju Lekki',
                'Grandview Luxury Villas, Ikoyi',
            ]),
            'representative_id' => null,
            'status' => InspectionStatus::Scheduled,
            'inspection_date' => fake()->dateTimeBetween('now', '+14 days')->format('Y-m-d'),
            'inspection_time' => fake()->randomElement(['10:00 AM', '02:00 PM']),
            'meeting_point' => 'Bamcom Corporate Office, Plot 12, Admiralty Way, Lekki Phase 1, Lagos',
            'customer_notes' => fake()->optional()->sentence(),
            'sales_notes' => fake()->optional()->sentence(),
            'outcome' => null,
            'created_by_id' => null,
        ];
    }

    /**
     * Indicate that the inspection is requested.
     */
    public function requested(): self
    {
        return $this->state(fn () => [
            'status' => InspectionStatus::Requested,
        ]);
    }

    /**
     * Indicate that the inspection is confirmed.
     */
    public function confirmed(): self
    {
        return $this->state(fn () => [
            'status' => InspectionStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the inspection is completed.
     */
    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => InspectionStatus::Completed,
            'outcome' => 'Client inspected plots 12 & 14. Highly interested, requested deed verification documents.',
        ]);
    }

    /**
     * Indicate that the inspection is cancelled.
     */
    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => InspectionStatus::Cancelled,
            'outcome' => 'Client had emergency travel and cancelled site visit.',
        ]);
    }

    /**
     * Indicate that the inspection is rescheduled.
     */
    public function rescheduled(): self
    {
        return $this->state(fn () => [
            'status' => InspectionStatus::Rescheduled,
            'outcome' => 'Rescheduled from morning to afternoon session per client request.',
        ]);
    }

    /**
     * Indicate that the inspection resulted in a no-show.
     */
    public function noShow(): self
    {
        return $this->state(fn () => [
            'status' => InspectionStatus::NoShow,
            'outcome' => 'Client did not arrive at pickup point; phone unreachable at departure.',
        ]);
    }
}
