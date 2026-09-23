<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TaskService $service;

    protected Contact $contact;

    protected Lead $lead;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TaskService::class);
        $this->user = User::factory()->create();
        $this->contact = Contact::factory()->create();
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
        ]);
    }

    public function test_can_create_task_with_required_and_optional_fields(): void
    {
        $task = $this->service->createTask([
            'title' => 'Follow up on survey plan',
            'description' => 'Send survey plan and layout for Silverstone Plot 12.',
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'assigned_user_id' => $this->user->id,
            'due_at' => now()->addDays(2),
            'priority' => TaskPriority::High,
            'type' => TaskType::DocumentPreparation,
        ], $this->user);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Follow up on survey plan',
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'assigned_user_id' => $this->user->id,
            'priority' => TaskPriority::High->value,
            'type' => TaskType::DocumentPreparation->value,
            'status' => TaskStatus::Pending->value,
        ]);
    }

    public function test_creating_task_automatically_records_crm_activity(): void
    {
        $task = $this->service->createTask([
            'title' => 'Call lead regarding 30% deposit invoice',
            'contact_id' => $this->contact->id,
            'due_at' => now()->addDay(),
            'priority' => TaskPriority::Urgent,
            'type' => TaskType::Call,
        ], $this->user);

        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'task_created',
        ]);

        $activity = Activity::where('contact_id', $this->contact->id)
            ->where('activity_type', 'task_created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($task->id, $activity->properties['task_id']);
        $this->assertSame('urgent', $activity->properties['priority']);
    }

    public function test_task_scopes_categorize_today_overdue_upcoming_completed(): void
    {
        // 1. Task due today
        $todayTask = Task::factory()->today()->create([
            'contact_id' => $this->contact->id,
        ]);

        // 2. Overdue task
        $overdueTask = Task::factory()->overdue()->create([
            'contact_id' => $this->contact->id,
        ]);

        // 3. Upcoming task
        $upcomingTask = Task::factory()->upcoming()->create([
            'contact_id' => $this->contact->id,
        ]);

        // 4. Completed task
        $completedTask = Task::factory()->completed()->create([
            'contact_id' => $this->contact->id,
        ]);

        // Verify scopes
        $todayResults = Task::today()->pluck('id')->all();
        $this->assertContains($todayTask->id, $todayResults);
        $this->assertNotContains($overdueTask->id, $todayResults);
        $this->assertNotContains($completedTask->id, $todayResults);

        $overdueResults = Task::overdue()->pluck('id')->all();
        $this->assertContains($overdueTask->id, $overdueResults);
        $this->assertNotContains($todayTask->id, $overdueResults);
        $this->assertNotContains($completedTask->id, $overdueResults);

        $upcomingResults = Task::upcoming()->pluck('id')->all();
        $this->assertContains($upcomingTask->id, $upcomingResults);
        $this->assertNotContains($todayTask->id, $upcomingResults);
        $this->assertNotContains($completedTask->id, $upcomingResults);

        $completedResults = Task::completed()->pluck('id')->all();
        $this->assertContains($completedTask->id, $completedResults);
        $this->assertNotContains($todayTask->id, $completedResults);
    }

    public function test_can_update_task_attributes(): void
    {
        $task = Task::factory()->create([
            'contact_id' => $this->contact->id,
            'title' => 'Old Title',
            'priority' => TaskPriority::Low,
        ]);

        $updated = $this->service->updateTask($task, [
            'title' => 'Updated Task Title',
            'priority' => TaskPriority::Urgent,
        ], $this->user);

        $this->assertSame('Updated Task Title', $updated->title);
        $this->assertSame(TaskPriority::Urgent, $updated->priority);
    }

    public function test_completing_task_sets_completed_at_and_records_activity(): void
    {
        $task = Task::factory()->create([
            'contact_id' => $this->contact->id,
            'status' => TaskStatus::Pending,
        ]);

        $completed = $this->service->completeTask($task, $this->user);

        $this->assertSame(TaskStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);

        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'task_completed',
        ]);
    }

    public function test_reopening_task_resets_status_and_completed_at(): void
    {
        $task = Task::factory()->completed()->create([
            'contact_id' => $this->contact->id,
        ]);

        $reopened = $this->service->reopenTask($task, $this->user);

        $this->assertSame(TaskStatus::Pending, $reopened->status);
        $this->assertNull($reopened->completed_at);
    }

    public function test_cancelling_task_records_cancellation_activity_with_reason(): void
    {
        $task = Task::factory()->create([
            'contact_id' => $this->contact->id,
            'status' => TaskStatus::Pending,
        ]);

        $cancelled = $this->service->cancelTask($task, $this->user, 'Client postponed real estate purchase');

        $this->assertSame(TaskStatus::Cancelled, $cancelled->status);

        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'task_cancelled',
        ]);
    }

    public function test_calculates_task_metrics_accurately(): void
    {
        Task::factory()->today()->create(['assigned_user_id' => $this->user->id]);
        Task::factory()->overdue()->create(['assigned_user_id' => $this->user->id]);
        Task::factory()->upcoming()->create(['assigned_user_id' => $this->user->id]);
        Task::factory()->completed()->create(['assigned_user_id' => $this->user->id]);

        $metrics = $this->service->getMetrics($this->user->id);

        $this->assertSame(1, $metrics['today']);
        $this->assertSame(1, $metrics['overdue']);
        $this->assertSame(1, $metrics['upcoming']);
        $this->assertSame(1, $metrics['completed']);
        $this->assertSame(3, $metrics['total_active']);
    }

    public function test_can_soft_delete_task(): void
    {
        $task = Task::factory()->create([
            'contact_id' => $this->contact->id,
        ]);

        $this->service->deleteTask($task);

        $this->assertSoftDeleted('tasks', [
            'id' => $task->id,
        ]);
    }
}
