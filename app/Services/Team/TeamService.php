<?php

namespace App\Services\Team;

use App\DTOs\Team\CreateTeamDTO;
use App\DTOs\Team\UpdateTeamDTO;
use App\Models\Team;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TeamService extends BaseService
{
    /**
     * Get paginated teams with search, leader, and member counts.
     *
     * @param  array{search?: ?string, type?: ?string, is_active?: ?bool, per_page?: ?int}  $filters
     */
    public function getPaginatedTeams(array $filters = []): LengthAwarePaginator
    {
        $query = Team::query()
            ->with(['leader.profile', 'members'])
            ->withCount('members')
            ->latest('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('leader', function (Builder $lq) use ($search) {
                        $lq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        $perPage = ! empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Retrieve all active teams for dropdown selectors.
     */
    public function getAllTeams(): Collection
    {
        return Team::query()
            ->active()
            ->with('leader')
            ->orderBy('name')
            ->get();
    }

    /**
     * Retrieve dedicated sales teams for sales rep assignments.
     */
    public function getSalesTeams(): Collection
    {
        return Team::query()
            ->sales()
            ->active()
            ->with('leader')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new team with optional leader and initial members.
     *
     * @throws ValidationException
     */
    public function createTeam(CreateTeamDTO $dto): Team
    {
        if (Team::where('name', $dto->name)->exists()) {
            throw ValidationException::withMessages([
                'name' => ['A team with this name already exists.'],
            ]);
        }

        return $this->transaction(function () use ($dto): Team {
            $team = Team::create([
                'name' => $dto->name,
                'description' => $dto->description,
                'type' => $dto->type,
                'leader_id' => $dto->leaderId,
                'is_active' => $dto->isActive,
            ]);

            if (! empty($dto->memberIds)) {
                $syncData = [];
                foreach ($dto->memberIds as $userId) {
                    $syncData[$userId] = [
                        'role_in_team' => ($userId === $dto->leaderId) ? 'leader' : 'member',
                        'joined_at' => now(),
                    ];
                }
                $team->members()->sync($syncData);

                // Update primary team on members if they have none
                User::whereIn('id', $dto->memberIds)
                    ->whereNull('team_id')
                    ->update(['team_id' => $team->id]);
            }

            // Ensure leader is attached to members
            if ($dto->leaderId) {
                $team->members()->syncWithoutDetaching([
                    $dto->leaderId => [
                        'role_in_team' => 'leader',
                        'joined_at' => now(),
                    ],
                ]);
            }

            $this->logInfo('Team created successfully', [
                'team_id' => $team->id,
                'name' => $team->name,
                'type' => $team->type->value,
            ]);

            return $team->load(['leader', 'members']);
        });
    }

    /**
     * Update an existing team and membership.
     *
     * @throws ValidationException
     */
    public function updateTeam(Team $team, UpdateTeamDTO $dto): Team
    {
        if ($dto->name !== null && $dto->name !== $team->name) {
            if (Team::where('name', $dto->name)->where('id', '!=', $team->id)->exists()) {
                throw ValidationException::withMessages([
                    'name' => ['A team with this name already exists.'],
                ]);
            }
        }

        return $this->transaction(function () use ($team, $dto): Team {
            $updates = [];

            if ($dto->name !== null) {
                $updates['name'] = $dto->name;
            }

            if ($dto->description !== null) {
                $updates['description'] = $dto->description;
            }

            if ($dto->type !== null) {
                $updates['type'] = $dto->type;
            }

            if ($dto->clearLeader) {
                $updates['leader_id'] = null;
            } elseif ($dto->leaderId !== null) {
                $updates['leader_id'] = $dto->leaderId;
            }

            if ($dto->isActive !== null) {
                $updates['is_active'] = $dto->isActive;
            }

            if (! empty($updates)) {
                $team->update($updates);
            }

            if ($dto->memberIds !== null) {
                $syncData = [];
                foreach ($dto->memberIds as $userId) {
                    $syncData[$userId] = [
                        'role_in_team' => ($userId === $team->leader_id) ? 'leader' : 'member',
                        'joined_at' => now(),
                    ];
                }
                $team->members()->sync($syncData);
            }

            if ($team->leader_id) {
                $team->members()->syncWithoutDetaching([
                    $team->leader_id => [
                        'role_in_team' => 'leader',
                        'joined_at' => now(),
                    ],
                ]);
            }

            $this->logInfo('Team updated successfully', [
                'team_id' => $team->id,
            ]);

            return $team->fresh(['leader', 'members']);
        });
    }

    /**
     * Assign members to a team.
     *
     * @param  list<int>  $userIds
     */
    public function assignMembers(Team $team, array $userIds, string $roleInTeam = 'member'): Team
    {
        return $this->transaction(function () use ($team, $userIds, $roleInTeam): Team {
            $syncData = [];
            foreach ($userIds as $userId) {
                $syncData[$userId] = [
                    'role_in_team' => ($userId === $team->leader_id) ? 'leader' : $roleInTeam,
                    'joined_at' => now(),
                ];
            }

            $team->members()->syncWithoutDetaching($syncData);

            // Update primary team for sales team assignments
            if ($team->isSalesTeam()) {
                User::whereIn('id', $userIds)->update(['team_id' => $team->id]);
            }

            $this->logInfo('Team members assigned', [
                'team_id' => $team->id,
                'user_ids' => $userIds,
            ]);

            return $team->fresh(['leader', 'members']);
        });
    }

    /**
     * Remove a member from a team.
     */
    public function removeMember(Team $team, User $user): Team
    {
        return $this->transaction(function () use ($team, $user): Team {
            $team->members()->detach($user->id);

            if ($user->team_id === $team->id) {
                $user->update(['team_id' => null]);
            }

            if ($team->leader_id === $user->id) {
                $team->update(['leader_id' => null]);
            }

            $this->logInfo('Team member removed', [
                'team_id' => $team->id,
                'user_id' => $user->id,
            ]);

            return $team->fresh(['leader', 'members']);
        });
    }

    /**
     * Delete a team.
     */
    public function deleteTeam(Team $team): void
    {
        $this->transaction(function () use ($team): void {
            // Nullify primary team on users
            User::where('team_id', $team->id)->update(['team_id' => null]);

            // Detach all members
            $team->members()->detach();

            $team->delete();

            $this->logInfo('Team deleted successfully', [
                'team_id' => $team->id,
            ]);
        });
    }
}
