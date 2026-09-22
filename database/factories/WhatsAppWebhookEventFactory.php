<?php

namespace Database\Factories;

use App\Models\WhatsAppWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppWebhookEvent>
 */
class WhatsAppWebhookEventFactory extends Factory
{
    protected $model = WhatsAppWebhookEvent::class;

    public function definition(): array
    {
        $senderPhone = '23480'.fake()->numerify('########');
        $messageId = 'wamid.'.Str::random(32);

        return [
            'uuid' => (string) Str::uuid(),
            'whatsapp_account_id' => null,
            'event_type' => 'messages',
            'meta_event_id' => $messageId,
            'sender_phone' => $senderPhone,
            'recipient_phone' => '+2348000000000',
            'payload' => [
                'object' => 'whatsapp_business_account',
                'entry' => [
                    [
                        'id' => '123456789',
                        'changes' => [
                            [
                                'value' => [
                                    'messaging_product' => 'whatsapp',
                                    'metadata' => [
                                        'display_phone_number' => '+2348000000000',
                                        'phone_number_id' => '109876543210',
                                    ],
                                    'messages' => [
                                        [
                                            'from' => $senderPhone,
                                            'id' => $messageId,
                                            'timestamp' => (string) time(),
                                            'type' => 'text',
                                            'text' => [
                                                'body' => 'Hello, I want to inquire about Epe Waterfront plots.',
                                            ],
                                        ],
                                    ],
                                ],
                                'field' => 'messages',
                            ],
                        ],
                    ],
                ],
            ],
            'headers' => [
                'x-hub-signature-256' => 'sha256='.hash('sha256', 'mock_payload'),
            ],
            'signature' => 'sha256='.hash('sha256', 'mock_payload'),
            'status' => 'pending',
            'processed_at' => null,
            'error_message' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function failed(?string $error = 'Invalid HMAC signature'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $error,
            'processed_at' => now(),
        ]);
    }
}
