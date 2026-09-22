<?php

namespace Database\Factories;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'contact_id' => Contact::factory(),
            'assigned_user_id' => null,
            'whatsapp_account_id' => null,
            'mode' => ConversationMode::Hybrid,
            'status' => ConversationStatus::Open,
            'channel' => 'whatsapp',
            'subject' => fake()->optional(0.5)->sentence(3),
            'last_message_at' => now(),
            'unread_count' => 0,
            'metadata' => null,
            'closed_at' => null,
        ];
    }

    /**
     * AI handling mode.
     */
    public function ai(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => ConversationMode::Ai,
        ]);
    }

    /**
     * Human handling mode.
     */
    public function human(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => ConversationMode::Human,
        ]);
    }

    /**
     * Hybrid handling mode.
     */
    public function hybrid(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => ConversationMode::Hybrid,
        ]);
    }

    /**
     * Open status.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversationStatus::Open,
            'closed_at' => null,
        ]);
    }

    /**
     * Closed status.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    /**
     * Pending status.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ConversationStatus::Pending,
        ]);
    }

    /**
     * Assigned to a user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_user_id' => $user->id,
        ]);
    }
}
