<?php

namespace App\Enums;

enum TitleDocument: string
{
    case GovernorsConsent = 'governors_consent';
    case CertificateOfOccupancy = 'c_of_o';
    case Gazette = 'gazette';
    case Excision = 'excision';
    case RegisteredSurvey = 'registered_survey';
    case DeedOfAssignment = 'deed_of_assignment';

    public function label(): string
    {
        return match ($this) {
            self::GovernorsConsent => "Governor's Consent",
            self::CertificateOfOccupancy => 'C of O (Certificate of Occupancy)',
            self::Gazette => 'Government Gazette',
            self::Excision => 'Government Excision',
            self::RegisteredSurvey => 'Registered Survey & Deed of Assignment',
            self::DeedOfAssignment => 'Deed of Assignment',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::GovernorsConsent, self::CertificateOfOccupancy => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
            self::Gazette, self::Excision => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-300 dark:border-blue-800',
            self::RegisteredSurvey, self::DeedOfAssignment => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
