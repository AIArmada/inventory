<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BatchAllocationService
{
    public function __construct(
        private BatchService $batchService
    ) {}

    /**
     * Allocate quantity using FEFO (First Expired, First Out) strategy.
     *
     * @return array<array{batch: InventoryBatch, quantity: int}>
     */
    public function allocateFefo(Model $model, int $quantity, ?string $locationId = null): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        $batches = $this->batchService->getAllocatableBatches($model, $locationId);

        return $this->allocateFromBatches($batches, $quantity);
    }

    /**
     * Allocate quantity using FIFO (First In, First Out) strategy.
     *
     * @return array<array{batch: InventoryBatch, quantity: int}>
     */
    public function allocateFifo(Model $model, int $quantity, ?string $locationId = null): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if (InventoryOwnerScope::isEnabled() && $locationId !== null) {
            $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereKey($locationId)
                ->exists();

            if (! $isAllowed) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        $query = InventoryBatch::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->allocatable()
            ->fifo();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return $this->allocateFromBatches($query->get(), $quantity);
    }

    /**
     * Reserve quantity across batches.
     *
     * @param  array<array{batch: InventoryBatch, quantity: int}>  $allocations
     */
    public function reserveBatches(array $allocations): void
    {
        DB::transaction(function () use ($allocations): void {
            foreach ($allocations as $allocation) {
                $allocation['batch']->incrementReserved($allocation['quantity']);
            }
        });
    }

    /**
     * Release reserved quantity from batches.
     *
     * @param  array<array{batch: InventoryBatch, quantity: int}>  $allocations
     */
    public function releaseBatches(array $allocations): void
    {
        DB::transaction(function () use ($allocations): void {
            foreach ($allocations as $allocation) {
                $allocation['batch']->decrementReserved($allocation['quantity']);
            }
        });
    }

    /**
     * Commit allocations (deduct from on-hand after payment).
     *
     * @param  array<array{batch: InventoryBatch, quantity: int}>  $allocations
     */
    public function commitBatches(array $allocations): void
    {
        DB::transaction(function () use ($allocations): void {
            foreach ($allocations as $allocation) {
                $batch = $allocation['batch'];
                $quantity = $allocation['quantity'];

                $batch->decrementReserved($quantity);
                $batch->decrementOnHand($quantity);
            }
        });
    }

    /**
     * Check availability across batches.
     */
    public function checkAvailability(Model $model, int $quantity, ?string $locationId = null): bool
    {
        return $this->batchService->getTotalAvailable($model, $locationId) >= $quantity;
    }

    /**
     * Get allocation plan for a quantity.
     *
     * @return array{
     *     available: bool,
     *     total_available: int,
     *     requested: int,
     *     allocations: array<array{batch_id: string, batch_number: string, quantity: int, expires_at: string|null}>
     * }
     */
    public function getFefoAllocationPlan(Model $model, int $quantity, ?string $locationId = null): array
    {
        $batches = $this->batchService->getAllocatableBatches($model, $locationId);
        $totalAvailable = $batches->sum(fn (InventoryBatch $b): int => $b->available);

        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->available;

            if ($available <= 0) {
                continue;
            }

            $toAllocate = min($remaining, $available);
            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $toAllocate,
                'expires_at' => $batch->expires_at?->toDateString(),
            ];

            $remaining -= $toAllocate;
        }

        return [
            'available' => $remaining === 0,
            'total_available' => $totalAvailable,
            'requested' => $quantity,
            'allocations' => $allocations,
        ];
    }

    /**
     * Allocate from a collection of batches.
     *
     * @param  Collection<int, InventoryBatch>  $batches
     * @return array<array{batch: InventoryBatch, quantity: int}>
     */
    private function allocateFromBatches(Collection $batches, int $quantity): array
    {
        $allocations = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->available;

            if ($available <= 0) {
                continue;
            }

            $toAllocate = min($remaining, $available);
            $allocations[] = [
                'batch' => $batch,
                'quantity' => $toAllocate,
            ];

            $remaining -= $toAllocate;
        }

        if ($remaining > 0) {
            throw new InvalidArgumentException(
                sprintf('Insufficient batch inventory. Requested: %d, Available: %d', $quantity, $quantity - $remaining)
            );
        }

        return $allocations;
    }
}
