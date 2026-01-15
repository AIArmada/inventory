<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

use Carbon\CarbonImmutable;

/**
 * Reset frequency for document sequence numbers.
 */
enum ResetFrequency: string
{
    case Never = 'never';
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never',
            self::Daily => 'Daily',
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Never => 'Sequence continues indefinitely',
            self::Daily => 'Resets at midnight each day',
            self::Monthly => 'Resets on the first of each month',
            self::Yearly => 'Resets on January 1st each year',
        };
    }

    /**
     * Get the current period key for this reset frequency.
     */
    public function getCurrentPeriodKey(): string
    {
        return match ($this) {
            self::Never => 'all',
            self::Daily => CarbonImmutable::now()->format('Y-m-d'),
            self::Monthly => CarbonImmutable::now()->format('Y-m'),
            self::Yearly => CarbonImmutable::now()->format('Y'),
        };
    }

    /**
     * Get the format token to include in document numbers.
     */
    public function getFormatToken(): ?string
    {
        return match ($this) {
            self::Never => null,
            self::Daily => '{YYMMDD}',
            self::Monthly => '{YYMM}',
            self::Yearly => '{YY}',
        };
    }
}
