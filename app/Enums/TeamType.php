<?php

namespace App\Enums;

/**
 * Team categorization types in Bamcom AI CRM.
 */
enum TeamType: string
{
    case Sales = 'sales';
    case Support = 'support';
    case Marketing = 'marketing';
    case Inspection = 'inspection';
    case General = 'general';

    /**
     * Get the human-readable label for the team type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales Team',
            self::Support => 'Customer Support Team',
            self::Marketing => 'Marketing Team',
            self::Inspection => 'Field Inspection Team',
            self::General => 'General Team',
        };
    }

    /**
     * Determine if this team is a dedicated sales team.
     */
    public function isSales(): bool
    {
        return $this === self::Sales;
    }

    /**
     * Badge styles matching brand theme colors.
     *
     * @return array{bg: string, text: string, border: string}
     */
    public function badgeClasses(): array
    {
        return match ($this) {
            self::Sales => [
                'bg' => 'bg-emerald-100 dark:bg-emerald-950/60',
                'text' => 'text-emerald-700 dark:text-emerald-300',
                'border' => 'border-emerald-200 dark:border-emerald-800',
            ],
            self::Support => [
                'bg' => 'bg-sky-100 dark:bg-sky-950/60',
                'text' => 'text-sky-700 dark:text-sky-300',
                'border' => 'border-sky-200 dark:border-sky-800',
            ],
            self::Marketing => [
                'bg' => 'bg-purple-100 dark:bg-purple-950/60',
                'text' => 'text-purple-700 dark:text-purple-300',
                'border' => 'border-purple-200 dark:border-purple-800',
            ],
            self::Inspection => [
                'bg' => 'bg-amber-100 dark:bg-amber-950/60',
                'text' => 'text-amber-700 dark:text-amber-300',
                'border' => 'border-amber-200 dark:border-amber-800',
            ],
            self::General => [
                'bg' => 'bg-slate-100 dark:bg-slate-800',
                'text' => 'text-slate-700 dark:text-slate-300',
                'border' => 'border-slate-200 dark:border-slate-700',
            ],
        };
    }

    /**
     * Return all enum string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
