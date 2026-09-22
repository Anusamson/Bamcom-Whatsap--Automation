<?php

namespace App\Enums;

/**
 * Status of a CRM contact within the customer lifecycle.
 */
enum ContactStatus: string
{
    case Lead = 'lead';
    case Prospect = 'prospect';
    case Customer = 'customer';
    case Inactive = 'inactive';
    case Dormant = 'dormant';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Prospect => 'Prospect',
            self::Customer => 'Customer',
            self::Inactive => 'Inactive',
            self::Dormant => 'Dormant',
        };
    }

    /**
     * Tailwind CSS badge styling based on Bamcom color system.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Lead => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::Prospect => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Customer => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::Inactive => 'bg-slate-100 text-slate-800 dark:bg-slate-800/60 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            self::Dormant => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 border-red-200 dark:border-red-800',
        };
    }

    /**
     * All status values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
