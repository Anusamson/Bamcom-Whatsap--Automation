<?php

namespace App\Enums;

/**
 * Functional activity types for CRM tasks.
 */
enum TaskType: string
{
    case Call = 'call';
    case WhatsApp = 'whatsapp';
    case FollowUp = 'follow_up';
    case Meeting = 'meeting';
    case SiteInspection = 'site_inspection';
    case DocumentPreparation = 'document_preparation';
    case PaymentFollowup = 'payment_followup';
    case General = 'general';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Call => 'Phone Call',
            self::WhatsApp => 'WhatsApp Follow-up',
            self::FollowUp => 'General Follow-up',
            self::Meeting => 'Client Meeting',
            self::SiteInspection => 'Site Inspection',
            self::DocumentPreparation => 'Documentation & Contracts',
            self::PaymentFollowup => 'Payment & Milestone',
            self::General => 'General Task',
        };
    }

    /**
     * Tailwind badge styling.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Call => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800',
            self::WhatsApp => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::FollowUp => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::Meeting => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            self::SiteInspection => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::DocumentPreparation => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            self::PaymentFollowup => 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300 border-teal-200 dark:border-teal-800',
            self::General => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        };
    }

    /**
     * Lucide icon identifier for UI rendering.
     */
    public function iconName(): string
    {
        return match ($this) {
            self::Call => 'Phone',
            self::WhatsApp => 'MessageSquare',
            self::FollowUp => 'Clock',
            self::Meeting => 'Users',
            self::SiteInspection => 'Compass',
            self::DocumentPreparation => 'FileText',
            self::PaymentFollowup => 'CreditCard',
            self::General => 'CheckSquare',
        };
    }

    /**
     * All valid type values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
