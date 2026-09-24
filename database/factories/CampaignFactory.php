<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(3, true).' Campaign',
            'description' => fake()->sentence(),
            'status' => CampaignStatus::Draft,
            'audience_id' => Audience::factory(),
            'whatsapp_template_id' => null,
            'message_type' => 'custom_text',
            'message_content' => 'Hello {{contact.first_name}}, check out our latest property listings!',
            'template_parameters' => null,
            'batch_size' => 50,
            'batch_delay_seconds' => 5,
            'scheduled_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'paused_at' => null,
            'cancellation_reason' => null,
            'total_recipients' => 0,
            'processed_recipients' => 0,
            'sent_count' => 0,
            'delivered_count' => 0,
            'read_count' => 0,
            'failed_count' => 0,
            'opted_out_count' => 0,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
        ]);
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Running,
            'started_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Paused,
            'started_at' => now()->subHour(),
            'paused_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Completed,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Manually cancelled in testing',
        ]);
    }
}
