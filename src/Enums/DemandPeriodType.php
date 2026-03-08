<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum DemandPeriodType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    public function daysPerPeriod(): int
    {
        return match ($this) {
            self::Daily => 1,
            self::Weekly => 7,
            self::Monthly => 30,
        };
    }
}
