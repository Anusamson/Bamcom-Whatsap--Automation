<?php

namespace App\Enums;

/**
 * Queue priority tiers for Redis background processing in Bamcom AI CRM.
 */
enum QueuePriority: string
{
    case High = 'high';
    case Default = 'default';
    case Low = 'low';

    /**
     * Retrieve priority weight (higher executes first).
     */
    public function weight(): int
    {
        return match ($this) {
            self::High => 10,
            self::Default => 5,
            self::Low => 1,
        };
    }

    /**
     * Get queue name formatted for Redis worker consumption.
     */
    public function queueName(): string
    {
        return $this->value;
    }

    /**
     * Get all available queue priority string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
