<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum AllocationStrategy: string
{
    case Priority = 'priority';
    case FIFO = 'fifo';
    case LeastStock = 'least_stock';
    case SingleLocation = 'single_location';

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Priority => 'Priority (Highest First)',
            self::FIFO => 'FIFO (First In, First Out)',
            self::LeastStock => 'Least Stock (Balance Inventory)',
            self::SingleLocation => 'Single Location (No Split)',
        };
    }

    /**
     * Get a description of the strategy.
     */
    public function description(): string
    {
        return match ($this) {
            self::Priority => 'Allocate from locations with highest priority first',
            self::FIFO => 'Allocate from locations with oldest stock first',
            self::LeastStock => 'Allocate to balance inventory levels across locations',
            self::SingleLocation => 'Allocate from a single location or fail if insufficient',
        };
    }

    /**
     * Check if this strategy allows splitting across locations.
     */
    public function allowsSplit(): bool
    {
        return $this !== self::SingleLocation;
    }
}
