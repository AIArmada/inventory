<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Collection;
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
     * Get all inventory allocations for the model.
     *
     * @return MorphMany<InventoryAllocation, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function inventoryAllocations(): MorphMany;

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

    /**
     * Receive inventory at a location.
     */
    public function receive(
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement;

    /**
     * Ship inventory from a location.
     */
    public function ship(
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement;

    /**
     * Transfer inventory between locations.
     */
    public function transfer(
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement;

    /**
     * Allocate inventory for a cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function allocate(int $quantity, string $cartId, int $ttlMinutes = 30): Collection;

    /**
     * Release allocated inventory for a cart.
     */
    public function release(string $cartId): int;
}
