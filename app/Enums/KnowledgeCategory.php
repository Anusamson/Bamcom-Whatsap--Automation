<?php

namespace App\Enums;

/**
 * Functional categories for the Bamcom AI Knowledge Base.
 */
enum KnowledgeCategory: string
{
    case CompanyInformation = 'company_information';
    case Faq = 'faq';
    case SalesInformation = 'sales_information';
    case PropertyKnowledge = 'property_knowledge';
    case InspectionPolicy = 'inspection_policy';
    case PaymentPolicy = 'payment_policy';
    case ObjectionHandling = 'objection_handling';
    case SalesScript = 'sales_script';

    /**
     * User-friendly label for administration interfaces and summaries.
     */
    public function label(): string
    {
        return match ($this) {
            self::CompanyInformation => 'Company Information',
            self::Faq => 'Frequently Asked Questions',
            self::SalesInformation => 'Sales Information',
            self::PropertyKnowledge => 'Property Knowledge',
            self::InspectionPolicy => 'Inspection Policies',
            self::PaymentPolicy => 'Payment Policies',
            self::ObjectionHandling => 'Objection Handling',
            self::SalesScript => 'Approved Sales Scripts',
        };
    }

    /**
     * Short contextual description of what this category governs.
     */
    public function description(): string
    {
        return match ($this) {
            self::CompanyInformation => 'Corporate background, CAC registration, executive leadership, addresses, and trust markers.',
            self::Faq => 'Authoritative answers to common client questions regarding titles, purchase steps, and allocations.',
            self::SalesInformation => 'Active promotional campaigns, discount structures, incentives, and commission terms.',
            self::PropertyKnowledge => 'Qualitative insights, corridor expansion, neighborhood developments, and estate topography.',
            self::InspectionPolicy => 'Site inspection schedules, physical pickup logistics, guidelines, and virtual inspection rules.',
            self::PaymentPolicy => 'Deposit requirements, installment spread milestones, payment confirmation, and allocation releases.',
            self::ObjectionHandling => 'Pre-approved battle-tested frameworks to address buyer concerns (trust, pricing, title legitimacy).',
            self::SalesScript => 'Approved opening hooks, qualification questions, urgency drivers, and closing scripts for AI and human reps.',
        };
    }

    /**
     * Suggested Lucide icon name for UI representation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::CompanyInformation => 'Building',
            self::Faq => 'HelpCircle',
            self::SalesInformation => 'BadgePercent',
            self::PropertyKnowledge => 'MapPin',
            self::InspectionPolicy => 'CalendarCheck',
            self::PaymentPolicy => 'CreditCard',
            self::ObjectionHandling => 'ShieldAlert',
            self::SalesScript => 'FileText',
        };
    }

    /**
     * UI color accents for category badges.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::CompanyInformation => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            self::Faq => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            self::SalesInformation => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800',
            self::PropertyKnowledge => 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300 border-teal-200 dark:border-teal-800',
            self::InspectionPolicy => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            self::PaymentPolicy => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800',
            self::ObjectionHandling => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            self::SalesScript => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800',
        };
    }
}
