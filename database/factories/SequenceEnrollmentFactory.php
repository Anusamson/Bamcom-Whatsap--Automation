<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Enums\SequenceEnrollmentStatus;
use App\Models\Contact;
use App\Models\FollowUpSequence;
use App\Models\SequenceEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SequenceEnrollment>
 */
class SequenceEnrollmentFactory extends Factory
{
    protected $model = SequenceEnrollment::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'sequence_id' => FollowUpSequence::factory(),
            'contact_id' => Contact::factory()->state(['status' => ContactStatus::Lead]),
            'lead_id' => null,
            'enrolled_by_user_id' => User::factory(),
            'status' => SequenceEnrollmentStatus::Active,
            'current_step_number' => 0,
            'enrolled_at' => now(),
            'cancellation_reason' => null,
            'metadata' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => SequenceEnrollmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(string $reason = 'Cancelled by rule'): static
    {
        return $this->state(fn () => [
            'status' => SequenceEnrollmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
