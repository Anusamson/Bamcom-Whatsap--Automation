<?php

namespace App\Enums;

enum WhatsAppTemplateStatus: string
{
    case Approved = 'APPROVED';
    case Pending = 'PENDING';
    case Rejected = 'REJECTED';
    case Paused = 'PAUSED';
    case Disabled = 'DISABLED';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved by Meta',
            self::Pending => 'In Meta Review',
            self::Rejected => 'Rejected',
            self::Paused => 'Paused',
            self::Disabled => 'Disabled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Approved => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Rejected => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            self::Paused => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-950/50 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800',
            self::Disabled => 'bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-300 border-slate-200 dark:border-slate-800',
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
