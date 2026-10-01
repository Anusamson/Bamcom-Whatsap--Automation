<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(3, true).' Template',
            'subject' => 'Exclusive Update for {{contact.first_name}}',
            'preheader' => 'Exclusive real estate updates from Bamcom',
            'body_html' => '<h2>Hello {{contact.first_name}}</h2><p>Welcome to {{company.name}}! Here is your latest property investment portfolio update.</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
            'body_plain' => "Hello {{contact.first_name}},\nWelcome to {{company.name}}!\nUnsubscribe: {{unsubscribe_url}}",
            'category' => 'marketing',
            'status' => 'active',
            'variables' => ['contact.first_name', 'company.name', 'unsubscribe_url'],
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function transactional(): static
    {
        return $this->state(fn () => [
            'category' => 'transactional',
            'subject' => 'Inspection Confirmation - {{contact.first_name}}',
            'body_html' => '<p>Dear {{contact.first_name}}, your property inspection is confirmed.</p>',
            'body_plain' => 'Dear {{contact.first_name}}, your property inspection is confirmed.',
        ]);
    }
}
