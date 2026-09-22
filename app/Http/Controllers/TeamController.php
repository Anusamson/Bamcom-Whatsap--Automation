<?php

namespace App\Http\Controllers;

use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Http\Requests\Team\CreateTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\Team\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        protected TeamService $teamService,
    ) {}

    /**
     * Display a listing of teams with sales team filters.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('teams.view');

        $filters = [
            'search' => $request->input('search'),
            'type' => $request->input('type'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'per_page' => 12,
        ];

        $teams = $this->teamService->getPaginatedTeams($filters);

        return Inertia::render('Teams/Index', [
            'teams' => $teams,
            'types' => TeamType::values(),
            'filters' => $filters,
            'canCreate' => auth()->user()?->can('teams.create'),
        ]);
    }

    /**
     * Show the form for creating a new team.
     */
    public function create(): Response
    {
        Gate::authorize('teams.create');

        $eligibleLeaders = User::whereIn('role', [
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::SalesManager->value,
            UserRole::Management->value,
        ])->orderBy('name')->get(['id', 'name', 'email', 'role']);

        $eligibleMembers = User::active()->orderBy('name')->get(['id', 'name', 'email', 'role', 'team_id']);

        return Inertia::render('Teams/Create', [
            'types' => TeamType::values(),
            'eligibleLeaders' => $eligibleLeaders,
            'eligibleMembers' => $eligibleMembers,
        ]);
    }

    /**
     * Store a newly created team in storage.
     */
    public function store(CreateTeamRequest $request): RedirectResponse
    {
        $team = $this->teamService->createTeam($request->toDTO());

        return redirect()->route('teams.index')->with('success', "Team '{$team->name}' created successfully.");
    }

    /**
     * Display the specified team and its member roster.
     */
    public function show(Team $team): Response
    {
        Gate::authorize('view', $team);

        $team->load(['leader.profile', 'members.profile', 'directUsers.profile']);

        $eligibleMembers = User::active()
            ->whereNotIn('id', $team->members->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'team_id']);

        return Inertia::render('Teams/Show', [
            'team' => $team,
            'eligibleMembers' => $eligibleMembers,
            'canEdit' => auth()->user()?->can('update', $team),
            'canDelete' => auth()->user()?->can('delete', $team),
            'canAssign' => auth()->user()?->can('assign', $team),
        ]);
    }

    /**
     * Show the form for editing the specified team.
     */
    public function edit(Team $team): Response
    {
        Gate::authorize('update', $team);

        $team->load(['leader', 'members']);

        $eligibleLeaders = User::whereIn('role', [
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::SalesManager->value,
            UserRole::Management->value,
        ])->orderBy('name')->get(['id', 'name', 'email', 'role']);

        $eligibleMembers = User::active()->orderBy('name')->get(['id', 'name', 'email', 'role', 'team_id']);

        return Inertia::render('Teams/Edit', [
            'team' => $team,
            'types' => TeamType::values(),
            'eligibleLeaders' => $eligibleLeaders,
            'eligibleMembers' => $eligibleMembers,
        ]);
    }

    /**
     * Update the specified team in storage.
     */
    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $this->teamService->updateTeam($team, $request->toDTO());

        return redirect()->route('teams.index')->with('success', "Team '{$team->name}' updated successfully.");
    }

    /**
     * Assign members to a team.
     */
    public function assignMembers(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('assign', $team);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'role_in_team' => ['nullable', 'string', 'in:leader,member,specialist'],
        ]);

        $this->teamService->assignMembers($team, $validated['user_ids'], $validated['role_in_team'] ?? 'member');

        return back()->with('success', 'Team members assigned successfully.');
    }

    /**
     * Remove a member from a team.
     */
    public function removeMember(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('assign', $team);

        $this->teamService->removeMember($team, $user);

        return back()->with('success', "Member '{$user->name}' removed from team.");
    }

    /**
     * Remove the specified team from storage.
     */
    public function destroy(Team $team): RedirectResponse
    {
        Gate::authorize('delete', $team);

        $name = $team->name;
        $this->teamService->deleteTeam($team);

        return redirect()->route('teams.index')->with('success', "Team '{$name}' deleted successfully.");
    }
}
