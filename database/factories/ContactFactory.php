<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Contact>
     */
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+23480'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'location' => fake()->randomElement(['Lekki Phase 1, Lagos', 'Ikeja GRA, Lagos', 'Victoria Island, Lagos', 'Maitama, Abuja', 'Wuse 2, Abuja', 'Port Harcourt, Rivers']),
            'occupation' => fake()->randomElement(['Software Architect', 'Real Estate Investor', 'Chartered Accountant', 'Managing Director', 'Medical Consultant', 'Petroleum Engineer', 'Commercial Lawyer']),
            'preferred_language' => fake()->randomElement(['en', 'yo', 'ha', 'ig']),
            'lead_source' => fake()->randomElement(LeadSource::cases()),
            'assigned_user_id' => null,
            'status' => fake()->randomElement(ContactStatus::cases()),
            'last_contact_at' => fake()->optional(0.7)->dateTimeBetween('-14 days', 'now'),
        ];
    }

    /**
     * Contact state for lead status.
     */
    public function lead(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Lead,
        ]);
    }

    /**
     * Contact state for customer status.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Customer,
        ]);
    }

    /**
     * Contact state for prospect status.
     */
    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContactStatus::Prospect,
        ]);
    }

    /**
     * Contact state for WhatsApp source.
     */
    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'lead_source' => LeadSource::WhatsApp,
        ]);
    }

    /**
     * Contact assigned to specific user.
     */
    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_user_id' => $user->id,
        ]);
    }
}
