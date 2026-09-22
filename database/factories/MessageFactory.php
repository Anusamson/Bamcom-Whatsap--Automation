<?php

namespace Database\Factories;

use App\Enums\MessageDeliveryStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'conversation_id' => Conversation::factory(),
            'contact_id' => fn (array $attributes) => Conversation::find($attributes['conversation_id'])?->contact_id ?? Contact::factory(),
            'sender_type' => 'contact',
            'sender_id' => null,
            'meta_message_id' => 'wamid.'.Str::random(32),
            'direction' => 'inbound',
            'sender_phone' => '+23480'.fake()->numerify('########'),
            'recipient_phone' => '+2348000000000',
            'type' => 'text',
            'body' => fake()->sentence(),
            'media_url' => null,
            'media_mime_type' => null,
            'media_metadata' => null,
            'delivery_status' => MessageDeliveryStatus::Received,
            'is_read' => false,
            'read_at' => null,
            'delivered_at' => now(),
            'sent_at' => now(),
            'error_message' => null,
            'payload' => null,
        ];
    }

    /**
     * Outbound message from user/agent.
     */
    public function outbound(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outbound',
            'sender_type' => $user ? 'user' : 'system',
            'sender_id' => $user?->id,
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    }

    /**
     * Inbound message from contact.
     */
    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'inbound',
            'sender_type' => 'contact',
            'delivery_status' => MessageDeliveryStatus::Received,
        ]);
    }

    /**
     * Read message.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_at' => now(),
            'delivery_status' => MessageDeliveryStatus::Read,
        ]);
    }

    /**
     * Delivered message.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivered_at' => now(),
            'delivery_status' => MessageDeliveryStatus::Delivered,
        ]);
    }
}
