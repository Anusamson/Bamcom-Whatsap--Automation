<?php

namespace App\Enums;

/**
 * Delivery and acknowledgement status of a message.
 */
enum MessageDeliveryStatus: string
{
    case Received = 'received';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
        };
    }
}
