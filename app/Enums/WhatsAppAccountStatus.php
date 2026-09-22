<?php

namespace App\Enums;

enum WhatsAppAccountStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Pending = 'pending';
    case RateLimited = 'rate_limited';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected & Verified',
            self::Disconnected => 'Disconnected',
            self::Pending => 'Pending Verification',
            self::RateLimited => 'Rate Limited',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Connected => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::Disconnected => 'bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-300 border-slate-200 dark:border-slate-800',
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::RateLimited => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
