<?php

namespace App\Enums;

/**
 * Event types logged for WhatsApp campaign audit history.
 */
enum CampaignEventType: string
{
    case Created = 'created';
    case AudienceResolved = 'audience_resolved';
    case Scheduled = 'scheduled';
    case Started = 'started';
    case BatchDispatched = 'batch_dispatched';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case OptedOut = 'opted_out';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Campaign Created',
            self::AudienceResolved => 'Audience Resolved',
            self::Scheduled => 'Campaign Scheduled',
            self::Started => 'Campaign Started',
            self::BatchDispatched => 'Batch Dispatched',
            self::Sent => 'Message Sent',
            self::Delivered => 'Message Delivered',
            self::Read => 'Message Read',
            self::Failed => 'Message Failed',
            self::OptedOut => 'Recipient Opted Out',
            self::Paused => 'Campaign Paused',
            self::Resumed => 'Campaign Resumed',
            self::Completed => 'Campaign Completed',
            self::Cancelled => 'Campaign Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Created, self::AudienceResolved, self::Scheduled => 'bg-blue-100 text-blue-800',
            self::Started, self::BatchDispatched => 'bg-indigo-100 text-indigo-800',
            self::Sent, self::Delivered, self::Read => 'bg-emerald-100 text-emerald-800',
            self::Failed, self::Cancelled => 'bg-rose-100 text-rose-800',
            self::OptedOut => 'bg-purple-100 text-purple-800',
            self::Paused => 'bg-amber-100 text-amber-800',
            self::Resumed, self::Completed => 'bg-teal-100 text-teal-800',
        };
    }
}
