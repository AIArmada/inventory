<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Interface for models that can have inventory tracked across locations.
 */
interface InventoryableInterface
{
    /**
     * Get all inventory levels for the model.
     *
     * @return MorphMany<InventoryLevel, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function inventoryLevels(): MorphMany;

    /**
     * Get all inventory movements for the model.
     *
     * @return MorphMany<InventoryMovement, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function inventoryMovements(): MorphMany;

    /**
     * Get total quantity on hand across all locations.
     */
    public function getTotalOnHand(): int;

    /**
     * Get total available quantity across all locations.
     */
    public function getTotalAvailable(): int;

    /**
     * Check if sufficient inventory exists across all locations.
     */
    public function hasInventory(int $quantity): bool;

    /**
     * Get inventory level at a specific location.
     */
    public function getInventoryAtLocation(string $locationId): ?InventoryLevel;

    /**
     * Get the allocation strategy for this product.
     * Return null to use the global config strategy.
     */
    public function getAllocationStrategy(): ?AllocationStrategy;
}
