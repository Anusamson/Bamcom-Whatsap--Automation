<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {}

    /**
     * Display a tabbed listing of tasks: Today, Overdue, Upcoming, Completed.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);

        $view = $request->input('view', 'today');
        if (! in_array($view, ['today', 'overdue', 'upcoming', 'completed', 'all'], true)) {
            $view = 'today';
        }

        $filters = [
            'search' => $request->input('search'),
            'priority' => $request->input('priority'),
            'type' => $request->input('type'),
            'assigned_user_id' => $request->input('assigned_user_id'),
            'contact_id' => $request->input('contact_id'),
        ];

        $tasks = $this->taskService->getTasks($view, $filters, 25);
        $metrics = $this->taskService->getMetrics();

        $users = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $priorities = array_map(fn (TaskPriority $p): array => [
            'value' => $p->value,
            'label' => $p->label(),
            'badge' => $p->badgeClass(),
            'color' => $p->color(),
        ], TaskPriority::cases());

        $types = array_map(fn (TaskType $t): array => [
            'value' => $t->value,
            'label' => $t->label(),
            'badge' => $t->badgeClass(),
            'icon' => $t->iconName(),
        ], TaskType::cases());

        $statuses = array_map(fn (TaskStatus $s): array => [
            'value' => $s->value,
            'label' => $s->label(),
            'badge' => $s->badgeClass(),
            'color' => $s->color(),
        ], TaskStatus::cases());

        // Contacts list for quick picker
        $contacts = Contact::query()
            ->select('id', 'first_name', 'last_name', 'phone')
            ->orderBy('first_name')
            ->limit(100)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->full_name,
                'phone' => $c->phone,
            ]);

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'view' => $view,
            'filters' => $filters,
            'metrics' => $metrics,
            'users' => $users,
            'priorities' => $priorities,
            'types' => $types,
            'statuses' => $statuses,
            'contacts' => $contacts,
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'deal_id' => ['nullable', 'exists:deals,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['required', 'date'],
            'priority' => ['required', 'string', 'in:'.implode(',', TaskPriority::values())],
            'type' => ['required', 'string', 'in:'.implode(',', TaskType::values())],
            'status' => ['nullable', 'string', 'in:'.implode(',', TaskStatus::values())],
        ]);

        $task = $this->taskService->createTask($validated, $request->user());

        return back()->with('success', "Task \"{$task->title}\" created successfully.");
    }

    /**
     * Update an existing task.
     */
    public function update(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'deal_id' => ['nullable', 'exists:deals,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'due_at' => ['sometimes', 'required', 'date'],
            'priority' => ['sometimes', 'required', 'string', 'in:'.implode(',', TaskPriority::values())],
            'type' => ['sometimes', 'required', 'string', 'in:'.implode(',', TaskType::values())],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', TaskStatus::values())],
        ]);

        $this->taskService->updateTask($task, $validated, $request->user());

        return back()->with('success', "Task \"{$task->title}\" updated successfully.");
    }

    /**
     * Mark a task as completed.
     */
    public function complete(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $this->taskService->completeTask($task, $request->user());

        return back()->with('success', "Task \"{$task->title}\" marked as completed.");
    }

    /**
     * Reopen a completed or cancelled task.
     */
    public function reopen(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $this->taskService->reopenTask($task, $request->user());

        return back()->with('success', "Task \"{$task->title}\" reopened.");
    }

    /**
     * Delete / archive a task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $title = $task->title;
        $this->taskService->deleteTask($task);

        return back()->with('success', "Task \"{$title}\" archived successfully.");
    }
}
