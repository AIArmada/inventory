<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exceptions;

use Exception;

/**
 * @deprecated Use InsufficientInventoryException::forLocation() instead.
 *             This class will be removed in a future version.
 */
final class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly string $locationId,
        public readonly int $requested,
        public readonly int $available,
        string $message = 'Insufficient stock available',
    ) {
        parent::__construct($message);
    }

    /**
     * Create exception with details.
     *
     * @deprecated Use InsufficientInventoryException::forLocation() instead.
     */
    public static function forLocation(string $locationId, int $requested, int $available): self
    {
        return new self(
            locationId: $locationId,
            requested: $requested,
            available: $available,
            message: sprintf(
                'Insufficient stock at location %s. Requested: %d, Available: %d',
                $locationId,
                $requested,
                $available
            ),
        );
    }
}
