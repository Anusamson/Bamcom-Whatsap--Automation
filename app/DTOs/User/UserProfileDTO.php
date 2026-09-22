<?php

namespace App\DTOs\User;

use App\DTOs\BaseDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Data transfer object for user profile presentation.
 */
class UserProfileDTO extends BaseDTO
{
    /**
     * Create a new user profile DTO instance.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly string $status,
        public readonly ?string $emailVerifiedAt = null,
        public readonly ?string $createdAt = null,
    ) {}

    /**
     * Create a DTO instance from an Eloquent User model.
     */
    public static function fromModel(User $user): static
    {
        $role = match (true) {
            $user->role instanceof UserRole => $user->role->value,
            is_string($user->role) => $user->role,
            default => 'user',
        };

        $status = match (true) {
            $user->status instanceof UserStatus => $user->status->value,
            is_string($user->status) => $user->status,
            default => 'active',
        };

        return new static(
            id: (int) $user->id,
            name: (string) $user->name,
            email: (string) $user->email,
            role: $role,
            status: $status,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            createdAt: $user->created_at?->toIso8601String(),
        );
    }

    /**
     * Create an instance from an associative array.
     *
     * @param  array{id: int, name: string, email: string, role?: string, status?: string, email_verified_at?: ?string, created_at?: ?string}  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: (int) $data['id'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            role: (string) ($data['role'] ?? 'user'),
            status: (string) ($data['status'] ?? 'active'),
            emailVerifiedAt: isset($data['email_verified_at']) ? (string) $data['email_verified_at'] : null,
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
        );
    }

    /**
     * Transform the DTO into an array representation.
     *
     * @return array{id: int, name: string, email: string, role: string, status: string, email_verified_at: ?string, created_at: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'email_verified_at' => $this->emailVerifiedAt,
            'created_at' => $this->createdAt,
        ];
    }
}
