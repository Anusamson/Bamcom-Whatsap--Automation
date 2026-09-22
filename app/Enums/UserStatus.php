<?php

namespace App\Enums;

/**
 * Account status indicators for Bamcom AI CRM users.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
    case Suspended = 'suspended';

    /**
     * Retrieve the human-friendly label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Pending => 'Pending Verification',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Determine whether the account is currently active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Retrieve badge visual classes for status indication.
     *
     * @return array{bg: string, text: string, dot: string}
     */
    public function badgeClasses(): array
    {
        return match ($this) {
            self::Active => [
                'bg' => 'bg-emerald-50 dark:bg-emerald-950/40',
                'text' => 'text-emerald-700 dark:text-emerald-400',
                'dot' => 'bg-emerald-500',
            ],
            self::Inactive => [
                'bg' => 'bg-slate-100 dark:bg-slate-800',
                'text' => 'text-slate-600 dark:text-slate-400',
                'dot' => 'bg-slate-400',
            ],
            self::Pending => [
                'bg' => 'bg-amber-50 dark:bg-amber-950/40',
                'text' => 'text-amber-700 dark:text-amber-400',
                'dot' => 'bg-amber-500',
            ],
            self::Suspended => [
                'bg' => 'bg-red-50 dark:bg-red-950/40',
                'text' => 'text-red-700 dark:text-red-400',
                'dot' => 'bg-red-500',
            ],
        };
    }

    /**
     * Get all available status string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
