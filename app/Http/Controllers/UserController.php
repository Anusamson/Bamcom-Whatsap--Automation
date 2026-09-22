<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\Team\TeamService;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected TeamService $teamService,
    ) {}

    /**
     * Display a listing of system users with filter capabilities.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('users.view');

        $filters = [
            'search' => $request->input('search'),
            'role' => $request->input('role'),
            'status' => $request->input('status'),
            'team_id' => $request->input('team_id') ? (int) $request->input('team_id') : null,
            'per_page' => 15,
        ];

        $users = $this->userService->getPaginatedUsers($filters);
        $teams = $this->teamService->getAllTeams();
        $roles = Role::pluck('name');

        return Inertia::render('Users/Index', [
            'users' => $users,
            'teams' => $teams,
            'roles' => $roles,
            'statuses' => UserStatus::values(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        Gate::authorize('users.create');

        return Inertia::render('Users/Create', [
            'roles' => UserRole::values(),
            'teams' => $this->teamService->getAllTeams(),
            'salesTeams' => $this->teamService->getSalesTeams(),
            'statuses' => UserStatus::values(),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(CreateUserRequest $request): RedirectResponse
    {
        $user = $this->userService->createUser($request->toDTO());

        return redirect()->route('users.index')->with('success', "User '{$user->name}' created successfully.");
    }

    /**
     * Display the specified user profile and activity summary.
     */
    public function show(User $user): Response
    {
        Gate::authorize('view', $user);

        $user->load(['team.leader', 'teams', 'ledTeams', 'profile', 'roles']);

        return Inertia::render('Users/Show', [
            'user' => $user,
            'hasCrmActivity' => $user->hasCrmActivity(),
            'canEdit' => auth()->user()?->can('update', $user),
            'canDelete' => auth()->user()?->can('delete', $user),
            'canDisable' => auth()->user()?->can('disable', $user),
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        $user->load(['team', 'profile', 'roles']);

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => UserRole::values(),
            'teams' => $this->teamService->getAllTeams(),
            'salesTeams' => $this->teamService->getSalesTeams(),
            'statuses' => UserStatus::values(),
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->updateUser($user, $request->toDTO());

        return redirect()->route('users.index')->with('success', "User '{$user->name}' updated successfully.");
    }

    /**
     * Toggle or update user active status.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('disable', $user);

        $updated = $this->userService->toggleStatus($user);
        $statusLabel = $updated->status->label();

        return back()->with('success', "User account for '{$user->name}' is now {$statusLabel}.");
    }

    /**
     * Assign user to a specific team (sales team or operational team).
     */
    public function assignTeam(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'role_in_team' => ['nullable', 'string', 'in:leader,member,specialist'],
        ]);

        $this->userService->assignTeam($user, $validated['team_id'] ?? null, $validated['role_in_team'] ?? 'member');

        return back()->with('success', "Team assignment updated for '{$user->name}'.");
    }

    /**
     * Remove the specified user from storage safely.
     * Prevents permanent deletion of users with recorded CRM activity.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $force = $request->boolean('force');
        $result = $this->userService->safeDelete($user, $force);

        if ($result['action'] === 'deactivated') {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('users.index')->with('success', $result['message']);
    }
}
