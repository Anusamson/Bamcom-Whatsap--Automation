<?php

namespace App\Enums;

/**
 * Sales qualification state of an opportunity.
 */
enum QualificationStatus: string
{
    case Unqualified = 'unqualified';
    case InReview = 'in_review';
    case Qualified = 'qualified';
    case Disqualified = 'disqualified';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unqualified => 'Unqualified',
            self::InReview => 'In Review',
            self::Qualified => 'Sales Qualified',
            self::Disqualified => 'Disqualified',
        };
    }

    /**
     * Tailwind CSS badge styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Unqualified => 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            self::InReview => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::Qualified => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::Disqualified => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        };
    }

    /**
     * All qualification values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
