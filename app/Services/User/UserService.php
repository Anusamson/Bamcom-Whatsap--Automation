<?php

namespace App\Services\User;

use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService extends BaseService
{
    /**
     * Get paginated users with optional search and filters.
     *
     * @param  array{search?: ?string, role?: ?string, team_id?: ?int, status?: ?string, per_page?: ?int}  $filters
     */
    public function getPaginatedUsers(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['team', 'profile', 'roles'])
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('profile', function (Builder $pq) use ($search) {
                        $pq->where('phone', 'like', "%{$search}%")
                            ->orWhere('job_title', 'like', "%{$search}%")
                            ->orWhere('department', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', (int) $filters['team_id']);
        }

        $perPage = ! empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Create a new user with role, profile, and team assignment.
     *
     * @throws ValidationException
     */
    public function createUser(CreateUserDTO $dto): User
    {
        if (User::where('email', $dto->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['The provided email address is already in use.'],
            ]);
        }

        return $this->transaction(function () use ($dto): User {
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'role' => $dto->role,
                'status' => $dto->status,
                'team_id' => $dto->teamId,
                'email_verified_at' => now(),
            ]);

            // Sync Spatie role
            $roles = array_unique(array_filter([
                $dto->role->value,
                ...$dto->additionalRoles,
            ]));
            $user->syncRoles($roles);

            // Create profile
            $user->profile()->create([
                'phone' => $dto->phone,
                'job_title' => $dto->jobTitle,
                'department' => $dto->department,
                'bio' => $dto->bio,
            ]);

            // Attach to team pivot if team assigned
            if ($dto->teamId) {
                $user->teams()->syncWithoutDetaching([
                    $dto->teamId => [
                        'role_in_team' => $dto->role === UserRole::SalesManager ? 'leader' : 'member',
                        'joined_at' => now(),
                    ],
                ]);
            }

            $this->logInfo('User created successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role->value,
                'team_id' => $user->team_id,
            ]);

            return $user->load(['team', 'profile', 'roles']);
        });
    }

    /**
     * Update an existing user with role, profile, and team assignment.
     *
     * @throws ValidationException
     */
    public function updateUser(User $user, UpdateUserDTO $dto): User
    {
        if ($dto->email !== null && $dto->email !== $user->email) {
            if (User::where('email', $dto->email)->where('id', '!=', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['The provided email address is already taken.'],
                ]);
            }
        }

        return $this->transaction(function () use ($user, $dto): User {
            $updates = [];

            if ($dto->name !== null) {
                $updates['name'] = $dto->name;
            }

            if ($dto->email !== null) {
                $updates['email'] = $dto->email;
            }

            if (! empty($dto->password)) {
                $updates['password'] = Hash::make($dto->password);
            }

            if ($dto->role !== null) {
                // Protect Super Admin from accidental demotion
                if ($user->isSuperAdmin() && $dto->role !== UserRole::SuperAdmin) {
                    $otherSuperAdmins = User::where('role', UserRole::SuperAdmin->value)
                        ->where('id', '!=', $user->id)
                        ->count();

                    if ($otherSuperAdmins === 0) {
                        throw ValidationException::withMessages([
                            'role' => ['Cannot change role of the sole remaining Super Administrator.'],
                        ]);
                    }
                }

                $updates['role'] = $dto->role;
                $user->syncRoles([$dto->role->value, ...($dto->additionalRoles ?? [])]);
            }

            if ($dto->status !== null) {
                $updates['status'] = $dto->status;
            }

            if ($dto->clearTeam) {
                $updates['team_id'] = null;
            } elseif ($dto->teamId !== null) {
                $updates['team_id'] = $dto->teamId;
                $user->teams()->syncWithoutDetaching([
                    $dto->teamId => [
                        'role_in_team' => $user->role === UserRole::SalesManager ? 'leader' : 'member',
                        'joined_at' => now(),
                    ],
                ]);
            }

            if (! empty($updates)) {
                $user->update($updates);
            }

            // Update or create profile
            $profileData = array_filter([
                'phone' => $dto->phone,
                'job_title' => $dto->jobTitle,
                'department' => $dto->department,
                'bio' => $dto->bio,
            ], fn ($val) => $val !== null);

            if (! empty($profileData)) {
                $user->profile()->updateOrCreate([], $profileData);
            }

            $this->logInfo('User updated successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $user->load(['team', 'profile', 'roles']);
        });
    }

    /**
     * Toggle user active/inactive status or set directly.
     */
    public function toggleStatus(User $user, ?UserStatus $status = null): User
    {
        $newStatus = $status ?? ($user->status === UserStatus::Active ? UserStatus::Inactive : UserStatus::Active);

        if ($user->isSuperAdmin() && $newStatus !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'status' => ['Super Administrator accounts cannot be disabled.'],
            ]);
        }

        $user->update(['status' => $newStatus]);

        $this->logInfo('User status toggled', [
            'user_id' => $user->id,
            'new_status' => $newStatus->value,
        ]);

        return $user->fresh(['team', 'profile', 'roles']);
    }

    /**
     * Assign a user to a specific team (e.g. Sales Team).
     */
    public function assignTeam(User $user, ?int $teamId, string $roleInTeam = 'member'): User
    {
        return $this->transaction(function () use ($user, $teamId, $roleInTeam): User {
            $user->update(['team_id' => $teamId]);

            if ($teamId) {
                $user->teams()->syncWithoutDetaching([
                    $teamId => [
                        'role_in_team' => $roleInTeam,
                        'joined_at' => now(),
                    ],
                ]);
            }

            $this->logInfo('User team assigned', [
                'user_id' => $user->id,
                'team_id' => $teamId,
                'role_in_team' => $roleInTeam,
            ]);

            return $user->fresh(['team', 'profile', 'roles']);
        });
    }

    /**
     * Safely delete or deactivate a user.
     * Users with existing CRM activity are never permanently deleted.
     *
     * @return array{action: string, message: string, user: User}
     *
     * @throws ValidationException
     */
    public function safeDelete(User $user, bool $force = false): array
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'delete' => ['Super Administrator accounts cannot be deleted.'],
            ]);
        }

        if ($user->hasCrmActivity()) {
            // Deactivate and preserve records
            $user->disable();

            $this->logInfo('User deletion blocked due to CRM activity; account deactivated instead', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'action' => 'deactivated',
                'message' => 'User has recorded CRM activity and cannot be permanently deleted. The account has been deactivated to preserve audit records.',
                'user' => $user->fresh(),
            ];
        }

        if ($force) {
            $user->forceDelete();

            $this->logInfo('User permanently force deleted', [
                'user_id' => $user->id,
            ]);

            return [
                'action' => 'permanently_deleted',
                'message' => 'User permanently deleted successfully.',
                'user' => $user,
            ];
        }

        $user->delete();

        $this->logInfo('User soft deleted', [
            'user_id' => $user->id,
        ]);

        return [
            'action' => 'deleted',
            'message' => 'User deleted successfully.',
            'user' => $user,
        ];
    }
}
