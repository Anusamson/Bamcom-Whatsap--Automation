<?php

namespace Tests\Feature\Tasks;

use App\Enums\PermissionEnum;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Contact $contact;

    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->user->assignRole($superAdminRole);

        $this->contact = Contact::factory()->create();
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
        ]);
    }

    public function test_user_can_view_tasks_index_page(): void
    {
        Task::factory()->today()->count(3)->create([
            'assigned_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('tasks.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->has('tasks.data', 3)
                ->has('metrics')
                ->has('view')
                ->has('filters')
                ->has('priorities')
                ->has('statuses')
                ->has('types')
                ->has('users')
            );
    }

    public function test_tasks_filtered_by_views(): void
    {
        // 1 Overdue
        Task::factory()->overdue()->create([
            'assigned_user_id' => $this->user->id,
            'title' => 'Overdue Task',
        ]);

        // 1 Today
        Task::factory()->today()->create([
            'assigned_user_id' => $this->user->id,
            'title' => 'Today Task',
        ]);

        // 1 Upcoming
        Task::factory()->upcoming()->create([
            'assigned_user_id' => $this->user->id,
            'title' => 'Upcoming Task',
        ]);

        // 1 Completed
        Task::factory()->completed()->create([
            'assigned_user_id' => $this->user->id,
            'title' => 'Completed Task',
        ]);

        // Overdue view
        $this->actingAs($this->user)
            ->get(route('tasks.index', ['view' => 'overdue']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('view', 'overdue')
                ->has('tasks.data', 1)
                ->where('tasks.data.0.title', 'Overdue Task')
            );

        // Today view
        $this->actingAs($this->user)
            ->get(route('tasks.index', ['view' => 'today']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('view', 'today')
                ->has('tasks.data', 1)
                ->where('tasks.data.0.title', 'Today Task')
            );

        // Upcoming view
        $this->actingAs($this->user)
            ->get(route('tasks.index', ['view' => 'upcoming']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('view', 'upcoming')
                ->has('tasks.data', 1)
                ->where('tasks.data.0.title', 'Upcoming Task')
            );

        // Completed view
        $this->actingAs($this->user)
            ->get(route('tasks.index', ['view' => 'completed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('view', 'completed')
                ->has('tasks.data', 1)
                ->where('tasks.data.0.title', 'Completed Task')
            );
    }

    public function test_user_can_create_a_task(): void
    {
        $payload = [
            'title' => 'Call buyer about Silverstone payment options',
            'description' => 'Follow up on 6-month installment plan request.',
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'assigned_user_id' => $this->user->id,
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'priority' => TaskPriority::High->value,
            'type' => TaskType::Call->value,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('tasks.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'title' => 'Call buyer about Silverstone payment options',
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'priority' => TaskPriority::High->value,
            'type' => TaskType::Call->value,
            'status' => TaskStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'task_created',
        ]);
    }

    public function test_task_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('tasks.store'), []);

        $response->assertSessionHasErrors(['title', 'due_at']);
    }

    public function test_user_can_update_a_task(): void
    {
        $task = Task::factory()->create([
            'contact_id' => $this->contact->id,
            'title' => 'Original Title',
            'priority' => TaskPriority::Low,
            'assigned_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('tasks.update', $task), [
                'title' => 'Updated Title',
                'priority' => TaskPriority::Urgent->value,
                'type' => TaskType::WhatsApp->value,
                'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'priority' => TaskPriority::Urgent->value,
            'type' => TaskType::WhatsApp->value,
        ]);
    }

    public function test_user_can_mark_task_as_completed(): void
    {
        $task = Task::factory()->create([
            'contact_id' => $this->contact->id,
            'status' => TaskStatus::Pending,
            'assigned_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('tasks.complete', $task));

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Completed->value,
        ]);
        $this->assertNotNull($task->fresh()->completed_at);

        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'task_completed',
        ]);
    }

    public function test_user_can_reopen_completed_task(): void
    {
        $task = Task::factory()->completed()->create([
            'contact_id' => $this->contact->id,
            'assigned_user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('tasks.reopen', $task));

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Pending->value,
            'completed_at' => null,
        ]);
    }

    public function test_user_can_delete_a_task(): void
    {
        $task = Task::factory()->create([
            'contact_id' => $this->contact->id,
            'assigned_user_id' => $this->user->id,
            'created_by_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('tasks.destroy', $task));

        $response->assertRedirect();
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }
}
