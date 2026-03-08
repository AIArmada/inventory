<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum MovementType: string
{
    case Receipt = 'receipt';
    case Shipment = 'shipment';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Allocation = 'allocation';
    case Release = 'release';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Shipment => 'Shipment',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Adjustment',
            self::Allocation => 'Allocation',
            self::Release => 'Release',
        };
    }

    /**
     * Get the badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Receipt => 'success',
            self::Shipment => 'danger',
            self::Transfer => 'info',
            self::Adjustment => 'warning',
            self::Allocation => 'primary',
            self::Release => 'gray',
        };
    }

    /**
     * Check if this movement increases inventory at a location.
     */
    public function isInbound(): bool
    {
        return in_array($this, [self::Receipt, self::Release], true);
    }

    /**
     * Check if this movement decreases inventory at a location.
     */
    public function isOutbound(): bool
    {
        return in_array($this, [self::Shipment, self::Allocation], true);
    }
}
