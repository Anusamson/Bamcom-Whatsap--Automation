<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;

/**
 * Data transfer object for user authentication requests.
 */
class LoginDTO extends BaseDTO
{
    /**
     * Create a new login DTO instance.
     */
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $remember = false,
    ) {}

    /**
     * Create an instance from an associative array.
     *
     * @param  array{email: string, password: string, remember?: bool}  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            email: strtolower(trim((string) $data['email'])),
            password: (string) $data['password'],
            remember: (bool) ($data['remember'] ?? false),
        );
    }

    /**
     * Transform the DTO into an array representation.
     *
     * @return array{email: string, remember: bool}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'remember' => $this->remember,
        ];
    }
}
