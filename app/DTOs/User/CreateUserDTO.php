<?php

namespace App\DTOs\User;

use App\DTOs\BaseDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;

class CreateUserDTO extends BaseDTO
{
    /**
     * @param  list<string>  $additionalRoles
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly UserRole $role = UserRole::SalesExecutive,
        public readonly UserStatus $status = UserStatus::Active,
        public readonly ?int $teamId = null,
        public readonly ?string $phone = null,
        public readonly ?string $jobTitle = null,
        public readonly ?string $department = null,
        public readonly ?string $bio = null,
        public readonly array $additionalRoles = [],
    ) {}

    /**
     * Create an instance from an associative array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $role = match (true) {
            isset($data['role']) && $data['role'] instanceof UserRole => $data['role'],
            isset($data['role']) && is_string($data['role']) => UserRole::tryFrom($data['role']) ?? UserRole::SalesExecutive,
            default => UserRole::SalesExecutive,
        };

        $status = match (true) {
            isset($data['status']) && $data['status'] instanceof UserStatus => $data['status'],
            isset($data['status']) && is_string($data['status']) => UserStatus::tryFrom($data['status']) ?? UserStatus::Active,
            default => UserStatus::Active,
        };

        return new static(
            name: trim((string) ($data['name'] ?? '')),
            email: strtolower(trim((string) ($data['email'] ?? ''))),
            password: (string) ($data['password'] ?? ''),
            role: $role,
            status: $status,
            teamId: ! empty($data['team_id']) ? (int) $data['team_id'] : null,
            phone: ! empty($data['phone']) ? trim((string) $data['phone']) : null,
            jobTitle: ! empty($data['job_title']) ? trim((string) $data['job_title']) : null,
            department: ! empty($data['department']) ? trim((string) $data['department']) : null,
            bio: ! empty($data['bio']) ? trim((string) $data['bio']) : null,
            additionalRoles: isset($data['roles']) && is_array($data['roles']) ? array_values($data['roles']) : [],
        );
    }

    /**
     * Transform the DTO into an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'team_id' => $this->teamId,
            'phone' => $this->phone,
            'job_title' => $this->jobTitle,
            'department' => $this->department,
            'bio' => $this->bio,
            'roles' => $this->additionalRoles,
        ];
    }
}
