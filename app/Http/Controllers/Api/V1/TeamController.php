<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Team\CreateTeamDTO;
use App\DTOs\Team\UpdateTeamDTO;
use App\Enums\TeamType;
use App\Models\Team;
use App\Models\User;
use App\Services\Team\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeamController extends ApiController
{
    public function __construct(
        protected TeamService $teamService,
    ) {}

    /**
     * Display a listing of teams.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('teams.view');

        $filters = [
            'search' => $request->input('search'),
            'type' => $request->input('type'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'per_page' => $request->input('per_page') ? (int) $request->input('per_page') : 15,
        ];

        $paginator = $this->teamService->getPaginatedTeams($filters);

        return $this->respondWithPagination($paginator, 'Teams retrieved successfully.');
    }

    /**
     * Retrieve dedicated sales teams for sales assignments.
     */
    public function sales(): JsonResponse
    {
        Gate::authorize('teams.view');

        $salesTeams = $this->teamService->getSalesTeams();

        return $this->respondWithSuccess($salesTeams, 'Sales teams retrieved successfully.');
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('teams.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(TeamType::values())],
            'leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $dto = CreateTeamDTO::fromArray($validated);
        $team = $this->teamService->createTeam($dto);

        return $this->respondWithSuccess($team, 'Team created successfully.', 201);
    }

    /**
     * Display the specified team.
     */
    public function show(Team $team): JsonResponse
    {
        Gate::authorize('view', $team);

        $team->load(['leader.profile', 'members.profile', 'directUsers.profile']);

        return $this->respondWithSuccess($team, 'Team details retrieved successfully.');
    }

    /**
     * Update the specified team.
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('update', $team);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($team->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', 'required', 'string', Rule::in(TeamType::values())],
            'leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $dto = UpdateTeamDTO::fromArray($validated);
        $updatedTeam = $this->teamService->updateTeam($team, $dto);

        return $this->respondWithSuccess($updatedTeam, 'Team updated successfully.');
    }

    /**
     * Assign members to a team.
     */
    public function assignMembers(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('assign', $team);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'role_in_team' => ['nullable', 'string', 'in:leader,member,specialist'],
        ]);

        $updatedTeam = $this->teamService->assignMembers(
            $team,
            $validated['user_ids'],
            $validated['role_in_team'] ?? 'member'
        );

        return $this->respondWithSuccess($updatedTeam, 'Team members assigned successfully.');
    }

    /**
     * Remove a member from a team.
     */
    public function removeMember(Team $team, User $user): JsonResponse
    {
        Gate::authorize('assign', $team);

        $updatedTeam = $this->teamService->removeMember($team, $user);

        return $this->respondWithSuccess($updatedTeam, "Member '{$user->name}' removed from team.");
    }

    /**
     * Remove the specified team.
     */
    public function destroy(Team $team): JsonResponse
    {
        Gate::authorize('delete', $team);

        $this->teamService->deleteTeam($team);

        return $this->respondWithSuccess(null, 'Team deleted successfully.');
    }
}
