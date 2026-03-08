<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum ReorderUrgency: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Calculate urgency based on days until stockout.
     */
    public static function fromDaysUntilStockout(?int $days, int $leadTime = 0): self
    {
        if ($days === null) {
            return self::Low;
        }

        $buffer = $days - $leadTime;

        return match (true) {
            $buffer <= 0 => self::Critical,
            $buffer <= 3 => self::High,
            $buffer <= 7 => self::Normal,
            default => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Critical => 1,
            self::High => 2,
            self::Normal => 3,
            self::Low => 4,
        };
    }
}
