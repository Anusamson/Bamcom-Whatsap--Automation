<?php

namespace Database\Factories;

use App\Enums\WhatsAppAccountStatus;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppAccount>
 */
class WhatsAppAccountFactory extends Factory
{
    protected $model = WhatsAppAccount::class;

    public function definition(): array
    {
        $phoneDigits = '23480'.fake()->numerify('########');

        return [
            'uuid' => (string) Str::uuid(),
            'name' => 'Bamcom Official WhatsApp',
            'phone_number_id' => (string) fake()->numerify('1098765432#####'),
            'waba_id' => (string) fake()->numerify('1234567890#####'),
            'display_phone_number' => '+'.$phoneDigits,
            'verified_name' => 'Bamcom Real Estate Ltd',
            'quality_rating' => 'GREEN',
            'status' => WhatsAppAccountStatus::Connected,
            'is_default' => false,
            'webhook_verified_at' => now(),
            'last_synced_at' => now(),
            'metadata' => [
                'code_verification_status' => 'VERIFIED',
                'vertical' => 'REAL_ESTATE',
            ],
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WhatsAppAccountStatus::Disconnected,
        ]);
    }
}
