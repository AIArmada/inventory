<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exceptions;

use Exception;

/**
 * Exception thrown when there is insufficient inventory for an operation.
 *
 * @example Basic usage
 * ```php
 * throw new InsufficientInventoryException(
 *     'Not enough stock',
 *     itemId: $product->id,
 *     requestedQuantity: 10,
 *     availableQuantity: 5
 * );
 * ```
 * @example With location context
 * ```php
 * throw InsufficientInventoryException::forLocation(
 *     $locationId,
 *     requested: 10,
 *     available: 5
 * );
 * ```
 */
class InsufficientInventoryException extends Exception
{
    public function __construct(
        string $message,
        private readonly string | int $itemId,
        private readonly int $requestedQuantity,
        private readonly int $availableQuantity,
        private readonly ?string $locationId = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Create exception for a specific location.
     */
    public static function forLocation(string $locationId, int $requested, int $available): self
    {
        return new self(
            message: sprintf(
                'Insufficient stock at location %s. Requested: %d, Available: %d',
                $locationId,
                $requested,
                $available
            ),
            itemId: $locationId,
            requestedQuantity: $requested,
            availableQuantity: $available,
            locationId: $locationId,
        );
    }

    /**
     * Create exception for an item across all locations.
     */
    public static function forItem(string | int $itemId, int $requested, int $available): self
    {
        return new self(
            message: sprintf(
                'Insufficient inventory for item %s. Requested: %d, Available: %d',
                $itemId,
                $requested,
                $available
            ),
            itemId: $itemId,
            requestedQuantity: $requested,
            availableQuantity: $available,
        );
    }

    public function getItemId(): string | int
    {
        return $this->itemId;
    }

    public function getRequestedQuantity(): int
    {
        return $this->requestedQuantity;
    }

    public function getAvailableQuantity(): int
    {
        return $this->availableQuantity;
    }

    public function getLocationId(): ?string
    {
        return $this->locationId;
    }

    public function getShortfall(): int
    {
        return $this->requestedQuantity - $this->availableQuantity;
    }
}
