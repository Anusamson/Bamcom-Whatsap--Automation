<?php

namespace App\Enums;

enum AutomationLogStatus: string
{
    case Pending = 'pending';
    case Delayed = 'delayed';
    case Executing = 'executing';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Delayed => 'Delayed (Queued)',
            self::Executing => 'Executing',
            self::Success => 'Success',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
