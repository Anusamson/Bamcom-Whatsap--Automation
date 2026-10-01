<?php

namespace Database\Factories;

use App\Models\EmailAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAccount>
 */
class EmailAccountFactory extends Factory
{
    protected $model = EmailAccount::class;

    public function definition(): array
    {
        return [
            'name' => 'Amazon SES Production',
            'provider' => 'ses',
            'from_name' => fake()->company(),
            'from_email' => fake()->safeEmail(),
            'reply_to_email' => fake()->safeEmail(),
            'configuration' => [
                'region' => 'us-east-1',
            ],
            'is_default' => false,
            'is_active' => true,
            'daily_quota' => 50000,
            'sent_today' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
