<?php

namespace App\Enums;

/**
 * UI theme preferences for Bamcom AI CRM dashboard.
 */
enum ThemeMode: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    /**
     * Retrieve the display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Light => 'Light Mode',
            self::Dark => 'Dark Mode',
            self::System => 'System Default',
        };
    }

    /**
     * Get all available theme mode values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
