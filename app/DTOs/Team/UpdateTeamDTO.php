<?php

namespace App\DTOs\Team;

use App\DTOs\BaseDTO;
use App\Enums\TeamType;

class UpdateTeamDTO extends BaseDTO
{
    /**
     * @param  list<int>|null  $memberIds
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?TeamType $type = null,
        public readonly ?int $leaderId = null,
        public readonly bool $clearLeader = false,
        public readonly ?bool $isActive = null,
        public readonly ?array $memberIds = null,
    ) {}

    /**
     * Create an instance from an associative array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $type = null;
        if (isset($data['type'])) {
            $type = $data['type'] instanceof TeamType
                ? $data['type']
                : (is_string($data['type']) ? TeamType::tryFrom($data['type']) : null);
        }

        return new static(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            description: array_key_exists('description', $data) ? ($data['description'] !== null ? trim((string) $data['description']) : null) : null,
            type: $type,
            leaderId: ! empty($data['leader_id']) ? (int) $data['leader_id'] : null,
            clearLeader: array_key_exists('leader_id', $data) && empty($data['leader_id']),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            memberIds: isset($data['member_ids']) && is_array($data['member_ids'])
                ? array_map('intval', array_values($data['member_ids']))
                : null,
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
            'description' => $this->description,
            'type' => $this->type?->value,
            'leader_id' => $this->leaderId,
            'is_active' => $this->isActive,
            'member_ids' => $this->memberIds,
        ], fn ($val) => $val !== null);
    }
}
