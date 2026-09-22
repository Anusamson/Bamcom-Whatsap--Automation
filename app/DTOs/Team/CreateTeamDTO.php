<?php

namespace App\DTOs\Team;

use App\DTOs\BaseDTO;
use App\Enums\TeamType;

class CreateTeamDTO extends BaseDTO
{
    /**
     * @param  list<int>  $memberIds
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly TeamType $type = TeamType::Sales,
        public readonly ?int $leaderId = null,
        public readonly bool $isActive = true,
        public readonly array $memberIds = [],
    ) {}

    /**
     * Create an instance from an associative array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $type = match (true) {
            isset($data['type']) && $data['type'] instanceof TeamType => $data['type'],
            isset($data['type']) && is_string($data['type']) => TeamType::tryFrom($data['type']) ?? TeamType::Sales,
            default => TeamType::Sales,
        };

        return new static(
            name: trim((string) ($data['name'] ?? '')),
            description: ! empty($data['description']) ? trim((string) $data['description']) : null,
            type: $type,
            leaderId: ! empty($data['leader_id']) ? (int) $data['leader_id'] : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
            memberIds: isset($data['member_ids']) && is_array($data['member_ids'])
                ? array_map('intval', array_values($data['member_ids']))
                : [],
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
            'description' => $this->description,
            'type' => $this->type->value,
            'leader_id' => $this->leaderId,
            'is_active' => $this->isActive,
            'member_ids' => $this->memberIds,
        ];
    }
}
