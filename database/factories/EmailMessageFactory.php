<?php

namespace Database\Factories;

use App\Enums\EmailMessageStatus;
use App\Enums\EmailMessageType;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailMessage>
 */
class EmailMessageFactory extends Factory
{
    protected $model = EmailMessage::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'email_account_id' => EmailAccount::factory(),
            'contact_id' => Contact::factory(),
            'user_id' => User::factory(),
            'to_email' => fake()->safeEmail(),
            'to_name' => fake()->name(),
            'from_email' => 'noreply@bamcomcrm.com',
            'from_name' => 'Bamcom AI CRM',
            'subject' => fake()->sentence(),
            'body_html' => '<p>'.fake()->paragraph().'</p>',
            'body_plain' => fake()->paragraph(),
            'type' => EmailMessageType::Transactional,
            'status' => EmailMessageStatus::Queued,
            'provider_message_id' => null,
            'provider_response' => null,
            'error_message' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => EmailMessageStatus::Sent,
            'provider_message_id' => 'ses-'.(string) Str::uuid(),
            'sent_at' => now(),
            'provider_response' => ['MessageId' => 'ses-'.(string) Str::uuid()],
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => EmailMessageStatus::Delivered,
            'provider_message_id' => 'ses-'.(string) Str::uuid(),
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now(),
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn () => [
            'status' => EmailMessageStatus::Bounced,
            'provider_message_id' => 'ses-'.(string) Str::uuid(),
            'sent_at' => now()->subMinutes(10),
            'bounced_at' => now(),
            'error_message' => '550 User unknown',
        ]);
    }

    public function marketing(): static
    {
        return $this->state(fn () => [
            'type' => EmailMessageType::Marketing,
        ]);
    }
}
