<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum AlertStatus: string
{
    case None = 'none';
    case LowStock = 'low_stock';
    case SafetyBreached = 'safety_breached';
    case OutOfStock = 'out_of_stock';
    case OverStock = 'over_stock';
    case Expiring = 'expiring';
    case Expired = 'expired';

    /**
     * Get all critical statuses.
     *
     * @return array<self>
     */
    public static function criticalStatuses(): array
    {
        return [self::OutOfStock, self::SafetyBreached, self::Expired];
    }

    /**
     * Get all warning statuses.
     *
     * @return array<self>
     */
    public static function warningStatuses(): array
    {
        return [self::LowStock, self::Expiring];
    }

    /**
     * Get all options for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'Normal',
            self::LowStock => 'Low Stock',
            self::SafetyBreached => 'Safety Stock Breached',
            self::OutOfStock => 'Out of Stock',
            self::OverStock => 'Over Stocked',
            self::Expiring => 'Expiring Soon',
            self::Expired => 'Expired',
        };
    }

    /**
     * Get the badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::None => 'success',
            self::LowStock => 'warning',
            self::SafetyBreached => 'danger',
            self::OutOfStock => 'danger',
            self::OverStock => 'info',
            self::Expiring => 'warning',
            self::Expired => 'danger',
        };
    }

    /**
     * Get the severity level (1-5, higher is more severe).
     */
    public function severity(): int
    {
        return match ($this) {
            self::None => 1,
            self::OverStock => 2,
            self::LowStock => 3,
            self::Expiring => 3,
            self::SafetyBreached => 4,
            self::Expired => 5,
            self::OutOfStock => 5,
        };
    }

    /**
     * Check if this status requires immediate attention.
     */
    public function requiresImmediateAttention(): bool
    {
        return $this->severity() >= 4;
    }
}
