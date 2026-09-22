<?php

namespace App\Enums;

/**
 * System user roles for Bamcom AI CRM.
 */
enum UserRole: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case SalesManager = 'Sales Manager';
    case SalesExecutive = 'Sales Executive';
    case CustomerSupport = 'Customer Support';
    case Marketing = 'Marketing';
    case InspectionOfficer = 'Inspection Officer';
    case Management = 'Management';

    /**
     * Retrieve the human-friendly label for the role.
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Retrieve theme-compatible badge colors for the role.
     *
     * @return array{bg: string, text: string, border: string}
     */
    public function badgeClasses(): array
    {
        return match ($this) {
            self::SuperAdmin => [
                'bg' => 'bg-red-100 dark:bg-red-950/60',
                'text' => 'text-red-700 dark:text-red-300',
                'border' => 'border-red-200 dark:border-red-800',
            ],
            self::Admin => [
                'bg' => 'bg-blue-100 dark:bg-blue-950/60',
                'text' => 'text-blue-700 dark:text-blue-300',
                'border' => 'border-blue-200 dark:border-blue-800',
            ],
            self::SalesManager => [
                'bg' => 'bg-emerald-100 dark:bg-emerald-950/60',
                'text' => 'text-emerald-700 dark:text-emerald-300',
                'border' => 'border-emerald-200 dark:border-emerald-800',
            ],
            self::SalesExecutive => [
                'bg' => 'bg-teal-100 dark:bg-teal-950/60',
                'text' => 'text-teal-700 dark:text-teal-300',
                'border' => 'border-teal-200 dark:border-teal-800',
            ],
            self::CustomerSupport => [
                'bg' => 'bg-sky-100 dark:bg-sky-950/60',
                'text' => 'text-sky-700 dark:text-sky-300',
                'border' => 'border-sky-200 dark:border-sky-800',
            ],
            self::Marketing => [
                'bg' => 'bg-purple-100 dark:bg-purple-950/60',
                'text' => 'text-purple-700 dark:text-purple-300',
                'border' => 'border-purple-200 dark:border-purple-800',
            ],
            self::InspectionOfficer => [
                'bg' => 'bg-amber-100 dark:bg-amber-950/60',
                'text' => 'text-amber-700 dark:text-amber-300',
                'border' => 'border-amber-200 dark:border-amber-800',
            ],
            self::Management => [
                'bg' => 'bg-indigo-100 dark:bg-indigo-950/60',
                'text' => 'text-indigo-700 dark:text-indigo-300',
                'border' => 'border-indigo-200 dark:border-indigo-800',
            ],
        };
    }

    /**
     * Get all available role string values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
