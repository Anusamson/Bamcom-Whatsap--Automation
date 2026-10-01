<?php

namespace Database\Factories;

use App\Enums\EmailCampaignStatus;
use App\Models\EmailCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailCampaign>
 */
class EmailCampaignFactory extends Factory
{
    protected $model = EmailCampaign::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->catchPhrase().' Campaign',
            'description' => fake()->sentence(),
            'status' => EmailCampaignStatus::Draft,
            'email_template_id' => null,
            'subject' => 'Exclusive Real Estate Investment for {{ contact.first_name }}',
            'preheader' => 'Special portfolio update from Bamcom CRM',
            'body_html' => '<h2>Hello {{ contact.first_name }}</h2><p>Here are your property portfolio updates.</p><p><a href="{{ unsubscribe_url }}">Unsubscribe</a></p>',
            'body_plain' => "Hello {{ contact.first_name }},\nHere are your property portfolio updates.\nUnsubscribe: {{ unsubscribe_url }}",
            'smart_list_id' => null,
            'segment_criteria' => null,
            'scheduled_at' => null,
            'batch_size' => 50,
            'total_recipients' => 0,
            'eligible_recipients' => 0,
            'skipped_recipients' => 0,
            'sent_count' => 0,
            'delivered_count' => 0,
            'failed_count' => 0,
            'opened_count' => 0,
            'clicked_count' => 0,
            'bounced_count' => 0,
            'complained_count' => 0,
            'unsubscribed_count' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => EmailCampaignStatus::Scheduled,
            'scheduled_at' => now()->addHour(),
        ]);
    }

    public function sending(): static
    {
        return $this->state(fn () => [
            'status' => EmailCampaignStatus::Sending,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => EmailCampaignStatus::Completed,
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }
}
