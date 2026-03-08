<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Traits;

use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryAllocationService;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasInventory
{
    /**
     * Get all inventory levels for the model.
     *
     * @return MorphMany<InventoryLevel, $this>
     */
    public function inventoryLevels(): MorphMany
    {
        return $this->morphMany(InventoryLevel::class, 'inventoryable')
            ->orderByDesc('quantity_on_hand');
    }

    /**
     * Get all inventory movements for the model.
     *
     * @return MorphMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'inventoryable')
            ->orderByDesc('occurred_at');
    }

    /**
     * Get all inventory allocations for the model.
     *
     * @return MorphMany<InventoryAllocation, $this>
     */
    public function inventoryAllocations(): MorphMany
    {
        return $this->morphMany(InventoryAllocation::class, 'inventoryable');
    }

    /**
     * Get total quantity on hand across all locations.
     */
    public function getTotalOnHand(): int
    {
        return $this->getInventoryService()->getTotalOnHand($this);
    }

    /**
     * Get total available quantity across all locations.
     */
    public function getTotalAvailable(): int
    {
        return $this->getInventoryService()->getTotalAvailable($this);
    }

    /**
     * Check if sufficient inventory exists across all locations.
     */
    public function hasInventory(int $quantity = 1): bool
    {
        return $this->getInventoryService()->hasInventory($this, $quantity);
    }

    /**
     * Get inventory level at a specific location.
     */
    public function getInventoryAtLocation(string $locationId): ?InventoryLevel
    {
        return $this->getInventoryService()->getLevel($this, $locationId);
    }

    /**
     * Get availability across all locations.
     *
     * @return array<string, int> Location ID => available quantity
     */
    public function getAvailability(): array
    {
        return $this->getInventoryService()->getAvailability($this);
    }

    /**
     * Get the allocation strategy for this product.
     * Override in model to set per-product strategy.
     */
    public function getAllocationStrategy(): ?AllocationStrategy
    {
        return null; // Use global config by default
    }

    /**
     * Receive inventory at a location.
     */
    public function receive(
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return $this->getInventoryService()->receive(
            $this,
            $locationId,
            $quantity,
            $reason,
            $note,
            $userId
        );
    }

    /**
     * Receive inventory at the default location.
     */
    public function receiveAtDefault(
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return $this->getInventoryService()->receiveAtDefault(
            $this,
            $quantity,
            $reason,
            $note,
            $userId
        );
    }

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
    ): InventoryMovement {
        return $this->getInventoryService()->ship(
            $this,
            $locationId,
            $quantity,
            $reason,
            $reference,
            $note,
            $userId
        );
    }

    /**
     * Ship inventory from the default location.
     */
    public function shipFromDefault(
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return $this->getInventoryService()->shipFromDefault(
            $this,
            $quantity,
            $reason,
            $reference,
            $note,
            $userId
        );
    }

    /**
     * Transfer inventory between locations.
     */
    public function transfer(
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return $this->getInventoryService()->transfer(
            $this,
            $fromLocationId,
            $toLocationId,
            $quantity,
            $note,
            $userId
        );
    }

    /**
     * Adjust inventory to a specific quantity at a location.
     */
    public function adjustInventory(
        string $locationId,
        int $newQuantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return $this->getInventoryService()->adjust(
            $this,
            $locationId,
            $newQuantity,
            $reason,
            $note,
            $userId
        );
    }

    /**
     * Allocate inventory for a cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function allocate(int $quantity, string $cartId, int $ttlMinutes = 30): Collection
    {
        return $this->getAllocationService()->allocate(
            $this,
            $quantity,
            $cartId,
            $ttlMinutes
        );
    }

    /**
     * Release allocated inventory for a cart.
     */
    public function release(string $cartId): int
    {
        return $this->getAllocationService()->release($this, $cartId);
    }

    /**
     * Get allocations for a cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function getAllocations(string $cartId): Collection
    {
        return $this->getAllocationService()->getAllocations($this, $cartId);
    }

    /**
     * Get movement history.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getInventoryHistory(int $limit = 50): Collection
    {
        return $this->getInventoryService()->getMovementHistory($this, $limit);
    }

    /**
     * Check if inventory is low at any location.
     */
    public function isLowInventory(?int $threshold = null): bool
    {
        $threshold ??= config('inventory.default_reorder_point', 10);

        return $this->inventoryLevels()
            ->get()
            ->contains(fn (InventoryLevel $level): bool => $level->isLowStock($threshold));
    }

    /**
     * Get the inventory service instance.
     */
    protected function getInventoryService(): InventoryService
    {
        return app(InventoryService::class);
    }

    /**
     * Get the allocation service instance.
     */
    protected function getAllocationService(): InventoryAllocationService
    {
        return app(InventoryAllocationService::class);
    }
}
