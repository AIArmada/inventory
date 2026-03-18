<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Strategies;

use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * FEFO (First Expired, First Out) allocation strategy.
 * Prioritizes batches that expire soonest to minimize waste.
 */
/** @implements AllocationStrategyInterface<InventoryBatch> */
final class FefoStrategy implements AllocationStrategyInterface
{
    public function name(): string
    {
        return 'fefo';
    }

    public function label(): string
    {
        return 'First Expired, First Out';
    }

    public function description(): string
    {
        return 'Allocates inventory from batches with the earliest expiry dates first, minimizing waste for perishable goods.';
    }

    /**
     * @return array<int, array{batch_id: string, location_id: string|null, quantity: int, expires_at: Carbon|null}>
     */
    public function allocate(Model $model, int $quantity, ?AllocationContext $context = null): array
    {
        $context = $context ?? new AllocationContext;

        if (InventoryOwnerScope::isEnabled() && $context->locationId !== null) {
            $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($context->locationId)
                ->exists();

            if (! $isAllowed) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        $query = InventoryBatch::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->allocatable()
            ->fefo();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        if ($context->locationId !== null) {
            $query->atLocation($context->locationId);
        }

        if ($context->excludeExpiringSoon) {
            $query->notExpiringSoon($context->minDaysToExpiry);
        }

        $batches = $query->get();

        return $this->buildAllocations($batches, $quantity);
    }

    /**
     * Check if strategy can fulfill the requested quantity.
     */
    public function canFulfill(Model $model, int $quantity, ?AllocationContext $context = null): bool
    {
        $context = $context ?? new AllocationContext;

        if (InventoryOwnerScope::isEnabled() && $context->locationId !== null) {
            $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($context->locationId)
                ->exists();

            if (! $isAllowed) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        $query = InventoryBatch::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->allocatable();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        if ($context->locationId !== null) {
            $query->atLocation($context->locationId);
        }

        if ($context->excludeExpiringSoon) {
            $query->notExpiringSoon($context->minDaysToExpiry);
        }

        $available = $query->sum('available_quantity');

        return $available >= $quantity;
    }

    /**
     * Get recommended allocation order for display.
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getRecommendedOrder(Model $model, ?AllocationContext $context = null): Collection
    {
        $context = $context ?? new AllocationContext;

        if (InventoryOwnerScope::isEnabled() && $context->locationId !== null) {
            $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($context->locationId)
                ->exists();

            if (! $isAllowed) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        $query = InventoryBatch::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->allocatable()
            ->fefo();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        if ($context->locationId !== null) {
            $query->atLocation($context->locationId);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, InventoryBatch>  $batches
     * @return array<int, array{batch_id: string, location_id: string|null, quantity: int, expires_at: Carbon|null}>
     */
    private function buildAllocations(Collection $batches, int $quantity): array
    {
        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->available_quantity;
            $allocate = min($available, $remaining);

            if ($allocate > 0) {
                $allocations[] = [
                    'batch_id' => $batch->id,
                    'location_id' => $batch->location_id,
                    'quantity' => $allocate,
                    'expires_at' => $batch->expires_at,
                ];

                $remaining -= $allocate;
            }
        }

        return $allocations;
    }
}
