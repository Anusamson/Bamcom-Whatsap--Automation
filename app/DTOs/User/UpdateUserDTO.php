<?php

namespace App\DTOs\User;

use App\DTOs\BaseDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;

class UpdateUserDTO extends BaseDTO
{
    /**
     * @param  list<string>|null  $additionalRoles
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?UserRole $role = null,
        public readonly ?UserStatus $status = null,
        public readonly ?int $teamId = null,
        public readonly bool $clearTeam = false,
        public readonly ?string $phone = null,
        public readonly ?string $jobTitle = null,
        public readonly ?string $department = null,
        public readonly ?string $bio = null,
        public readonly ?array $additionalRoles = null,
    ) {}

    /**
     * Create an instance from an associative array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $role = null;
        if (isset($data['role'])) {
            $role = $data['role'] instanceof UserRole
                ? $data['role']
                : (is_string($data['role']) ? UserRole::tryFrom($data['role']) : null);
        }

        $status = null;
        if (isset($data['status'])) {
            $status = $data['status'] instanceof UserStatus
                ? $data['status']
                : (is_string($data['status']) ? UserStatus::tryFrom($data['status']) : null);
        }

        return new static(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            email: isset($data['email']) ? strtolower(trim((string) $data['email'])) : null,
            password: ! empty($data['password']) ? (string) $data['password'] : null,
            role: $role,
            status: $status,
            teamId: ! empty($data['team_id']) ? (int) $data['team_id'] : null,
            clearTeam: array_key_exists('team_id', $data) && empty($data['team_id']),
            phone: array_key_exists('phone', $data) ? ($data['phone'] !== null ? trim((string) $data['phone']) : null) : null,
            jobTitle: array_key_exists('job_title', $data) ? ($data['job_title'] !== null ? trim((string) $data['job_title']) : null) : null,
            department: array_key_exists('department', $data) ? ($data['department'] !== null ? trim((string) $data['department']) : null) : null,
            bio: array_key_exists('bio', $data) ? ($data['bio'] !== null ? trim((string) $data['bio']) : null) : null,
            additionalRoles: isset($data['roles']) && is_array($data['roles']) ? array_values($data['roles']) : null,
        );
    }

    /**
     * Transform the DTO into an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value,
            'status' => $this->status?->value,
            'team_id' => $this->teamId,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'department' => $this->department,
            'bio' => $this->bio,
            'roles' => $this->additionalRoles,
        ], fn ($val) => $val !== null);
    }
}
