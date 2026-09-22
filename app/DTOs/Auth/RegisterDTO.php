<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;
use App\Enums\UserRole;

/**
 * Data transfer object for user registration requests.
 */
class RegisterDTO extends BaseDTO
{
    /**
     * Create a new register DTO instance.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly UserRole $role = UserRole::SalesExecutive,
    ) {}

    /**
     * Create an instance from an associative array.
     *
     * @param  array{name: string, email: string, password: string, role?: string|UserRole}  $data
     */
    public static function fromArray(array $data): static
    {
        $role = match (true) {
            isset($data['role']) && $data['role'] instanceof UserRole => $data['role'],
            isset($data['role']) && is_string($data['role']) => UserRole::tryFrom($data['role']) ?? UserRole::SalesExecutive,
            default => UserRole::SalesExecutive,
        };

        return new static(
            name: (string) $data['name'],
            email: strtolower(trim((string) $data['email'])),
            password: (string) $data['password'],
            role: $role,
        );
    }

    /**
     * Transform the DTO into an array representation.
     *
     * @return array{name: string, email: string, role: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
        ];
    }
}
