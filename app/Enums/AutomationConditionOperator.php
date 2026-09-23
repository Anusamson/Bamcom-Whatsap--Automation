<?php

namespace App\Enums;

enum AutomationConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case GreaterThan = 'greater_than';
    case GreaterThanOrEqual = 'greater_than_or_equal';
    case LessThan = 'less_than';
    case LessThanOrEqual = 'less_than_or_equal';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case In = 'in';
    case NotIn = 'not_in';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';

    /**
     * Evaluate actual value against expected value.
     */
    public function evaluate(mixed $actual, mixed $expected): bool
    {
        return match ($this) {
            self::Equals => $this->compareEquals($actual, $expected),
            self::NotEquals => ! $this->compareEquals($actual, $expected),
            self::GreaterThan => (float) $actual > (float) $expected,
            self::GreaterThanOrEqual => (float) $actual >= (float) $expected,
            self::LessThan => (float) $actual < (float) $expected,
            self::LessThanOrEqual => (float) $actual <= (float) $expected,
            self::Contains => str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            self::NotContains => ! str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            self::In => $this->compareIn($actual, $expected),
            self::NotIn => ! $this->compareIn($actual, $expected),
            self::IsEmpty => empty($actual) && $actual !== 0 && $actual !== '0',
            self::IsNotEmpty => ! empty($actual) || $actual === 0 || $actual === '0',
            self::StartsWith => str_starts_with(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            self::EndsWith => str_ends_with(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
        };
    }

    protected function compareEquals(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual == (float) $expected;
        }

        if (is_bool($actual) || is_bool($expected)) {
            return filter_var($actual, FILTER_VALIDATE_BOOLEAN) === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        return mb_strtolower((string) $actual) === mb_strtolower((string) $expected);
    }

    protected function compareIn(mixed $actual, mixed $expected): bool
    {
        $list = is_array($expected) ? $expected : array_map('trim', explode(',', (string) $expected));
        $normalizedActual = mb_strtolower((string) $actual);

        foreach ($list as $item) {
            if (mb_strtolower((string) $item) === $normalizedActual) {
                return true;
            }
        }

        return false;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
