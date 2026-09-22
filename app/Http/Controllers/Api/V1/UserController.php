<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends ApiController
{
    public function __construct(
        protected UserService $userService,
    ) {}

    /**
     * Display a paginated listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('users.view');

        $filters = [
            'search' => $request->input('search'),
            'role' => $request->input('role'),
            'status' => $request->input('status'),
            'team_id' => $request->input('team_id') ? (int) $request->input('team_id') : null,
            'per_page' => $request->input('per_page') ? (int) $request->input('per_page') : 15,
        ];

        $paginator = $this->userService->getPaginatedUsers($filters);

        return $this->respondWithPagination($paginator, 'Users retrieved successfully.');
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('users.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::in(UserRole::values())],
            'status' => ['nullable', 'string', Rule::in(UserStatus::values())],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(UserRole::values())],
        ]);

        $dto = CreateUserDTO::fromArray($validated);
        $user = $this->userService->createUser($dto);

        return $this->respondWithSuccess($user, 'User created successfully.', 201);
    }

    /**
     * Display the specified user profile.
     */
    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        $user->load(['team', 'teams', 'ledTeams', 'profile', 'roles']);

        return $this->respondWithSuccess([
            'user' => $user,
            'has_crm_activity' => $user->hasCrmActivity(),
        ], 'User details retrieved successfully.');
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['sometimes', 'required', 'string', Rule::in(UserRole::values())],
            'status' => ['nullable', 'string', Rule::in(UserStatus::values())],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(UserRole::values())],
        ]);

        $dto = UpdateUserDTO::fromArray($validated);
        $updatedUser = $this->userService->updateUser($user, $dto);

        return $this->respondWithSuccess($updatedUser, 'User updated successfully.');
    }

    /**
     * Disable/activate a user account.
     */
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        Gate::authorize('disable', $user);

        $status = null;
        if ($request->has('status')) {
            $status = UserStatus::tryFrom((string) $request->input('status'));
        }

        $updated = $this->userService->toggleStatus($user, $status);

        return $this->respondWithSuccess($updated, "User status updated to {$updated->status->value}.");
    }

    /**
     * Assign user to a specific team (e.g. Sales Team).
     */
    public function assignTeam(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'role_in_team' => ['nullable', 'string', 'in:leader,member,specialist'],
        ]);

        $updated = $this->userService->assignTeam(
            $user,
            $validated['team_id'] ?? null,
            $validated['role_in_team'] ?? 'member'
        );

        return $this->respondWithSuccess($updated, 'Team assignment updated successfully.');
    }

    /**
     * Remove the specified user.
     * Prevents permanent deletion of users with recorded CRM activity.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        $force = $request->boolean('force');
        $result = $this->userService->safeDelete($user, $force);

        if ($result['action'] === 'deactivated') {
            return $this->respondWithError($result['message'], 422, [
                'action' => 'deactivated',
                'has_crm_activity' => true,
            ]);
        }

        return $this->respondWithSuccess(null, $result['message']);
    }
}
