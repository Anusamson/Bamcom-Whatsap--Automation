<?php

namespace Database\Factories;

use App\Enums\EmailCampaignRecipientStatus;
use App\Models\Contact;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailCampaignRecipient>
 */
class EmailCampaignRecipientFactory extends Factory
{
    protected $model = EmailCampaignRecipient::class;

    public function definition(): array
    {
        return [
            'email_campaign_id' => EmailCampaign::factory(),
            'contact_id' => Contact::factory(),
            'email' => fake()->safeEmail(),
            'status' => EmailCampaignRecipientStatus::Pending,
            'skip_reason' => null,
            'email_message_id' => null,
            'batch_number' => 1,
            'queued_at' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'metadata' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => EmailCampaignRecipientStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function skipped(string $reason = 'unsubscribed'): static
    {
        return $this->state(fn () => [
            'status' => EmailCampaignRecipientStatus::Skipped,
            'skip_reason' => $reason,
        ]);
    }
}
