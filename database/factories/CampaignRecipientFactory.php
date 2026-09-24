<?php

namespace Database\Factories;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'campaign_id' => Campaign::factory(),
            'contact_id' => Contact::factory(),
            'lead_id' => null,
            'phone' => '+23480'.fake()->numerify('########'),
            'status' => CampaignRecipientStatus::Pending,
            'batch_number' => 1,
            'meta_message_id' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
            'failed_at' => null,
            'error_message' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Sent,
            'sent_at' => now(),
            'meta_message_id' => 'wamid.'.Str::random(32),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Delivered,
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now(),
            'meta_message_id' => 'wamid.'.Str::random(32),
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Read,
            'sent_at' => now()->subMinutes(10),
            'delivered_at' => now()->subMinutes(8),
            'read_at' => now(),
            'meta_message_id' => 'wamid.'.Str::random(32),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::Failed,
            'failed_at' => now(),
            'error_message' => 'Recipient phone number is invalid',
        ]);
    }

    public function optedOut(): static
    {
        return $this->state(fn () => [
            'status' => CampaignRecipientStatus::OptedOut,
            'error_message' => 'Contact opted out from WhatsApp broadcasts',
        ]);
    }
}
