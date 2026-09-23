<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'title' => fake()->randomElement([
                'Call client to discuss Silverstone payment plan',
                'Send site inspection confirmation via WhatsApp',
                'Prepare deed of assignment for Plot 14',
                'Follow up on initial 30% deposit invoice',
                'Coordinate pickup at Lekki office for site visit',
                'Send updated price list and layout brochure',
            ]),
            'description' => fake()->optional()->paragraph(1),
            'contact_id' => Contact::factory(),
            'lead_id' => null,
            'deal_id' => null,
            'assigned_user_id' => User::factory(),
            'created_by_id' => null,
            'due_at' => fake()->dateTimeBetween('now', '+7 days'),
            'completed_at' => null,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'status' => TaskStatus::Pending,
            'type' => fake()->randomElement(TaskType::cases()),
        ];
    }

    /**
     * Indicate that the task is due today.
     */
    public function today(): self
    {
        return $this->state(fn () => [
            'due_at' => now()->setTime(14, 0),
            'status' => TaskStatus::Pending,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is overdue.
     */
    public function overdue(): self
    {
        return $this->state(fn () => [
            'due_at' => now()->subDays(2)->setTime(10, 0),
            'status' => TaskStatus::Pending,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is upcoming.
     */
    public function upcoming(): self
    {
        return $this->state(fn () => [
            'due_at' => now()->addDays(3)->setTime(11, 0),
            'status' => TaskStatus::Pending,
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the task is completed.
     */
    public function completed(): self
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the task is cancelled.
     */
    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Cancelled,
            'completed_at' => null,
        ]);
    }

    /**
     * Set priority to urgent.
     */
    public function urgent(): self
    {
        return $this->state(fn () => [
            'priority' => TaskPriority::Urgent,
        ]);
    }
}
