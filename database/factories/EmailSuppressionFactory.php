<?php

namespace Database\Factories;

use App\Enums\EmailSuppressionReason;
use App\Models\Contact;
use App\Models\EmailSuppression;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSuppression>
 */
class EmailSuppressionFactory extends Factory
{
    protected $model = EmailSuppression::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'contact_id' => Contact::factory(),
            'reason' => EmailSuppressionReason::Bounce,
            'details' => '550 Recipient address rejected: mailbox unavailable',
            'suppressed_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    public function complaint(): static
    {
        return $this->state(fn () => [
            'reason' => EmailSuppressionReason::Complaint,
            'details' => 'Recipient filed spam abuse complaint',
        ]);
    }

    public function unsubscribe(): static
    {
        return $this->state(fn () => [
            'reason' => EmailSuppressionReason::Unsubscribe,
            'details' => 'Recipient unsubscribed via portal link',
        ]);
    }
}
