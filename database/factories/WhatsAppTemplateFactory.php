<?php

namespace Database\Factories;

use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2, '_');

        return [
            'uuid' => (string) Str::uuid(),
            'whatsapp_account_id' => null,
            'meta_template_id' => (string) fake()->numerify('987654321#####'),
            'name' => $name,
            'category' => 'UTILITY',
            'language' => 'en_US',
            'status' => WhatsAppTemplateStatus::Approved,
            'header_type' => 'TEXT',
            'header_content' => 'Bamcom Real Estate Alert',
            'body_text' => 'Hello {{1}}, your site inspection for {{2}} has been confirmed for {{3}}.',
            'footer_text' => 'Bamcom Properties - Building Tomorrow',
            'buttons' => [
                ['type' => 'QUICK_REPLY', 'text' => 'Confirm Attendance'],
                ['type' => 'QUICK_REPLY', 'text' => 'Reschedule'],
            ],
            'components' => [],
            'rejection_reason' => null,
            'last_synced_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WhatsAppTemplateStatus::Approved,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WhatsAppTemplateStatus::Pending,
        ]);
    }

    public function rejected(?string $reason = 'Content violates WhatsApp Commercial Policy'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WhatsAppTemplateStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }
}
