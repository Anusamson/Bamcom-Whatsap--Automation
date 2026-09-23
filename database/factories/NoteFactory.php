<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'contact_id' => Contact::factory(),
            'lead_id' => null,
            'user_id' => User::factory(),
            'content' => fake()->paragraph(2),
            'is_pinned' => false,
        ];
    }

    /**
     * Indicate that the note is pinned.
     */
    public function pinned(): self
    {
        return $this->state(fn () => [
            'is_pinned' => true,
        ]);
    }
}
