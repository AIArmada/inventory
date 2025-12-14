<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Events\BatchCreated;
use AIArmada\Inventory\Events\BatchExpired;
use AIArmada\Inventory\Events\BatchRecalled;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class BatchService
{
    /**
     * Create a new batch.
     */
    public function createBatch(
        Model $model,
        string $batchNumber,
        string $locationId,
        int $quantity,
        ?Carbon $expiresAt = null,
        ?Carbon $manufacturedAt = null,
        ?string $lotNumber = null,
        ?int $unitCostMinor = null,
        ?string $supplierId = null,
        ?string $purchaseOrderNumber = null
    ): InventoryBatch {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return DB::transaction(function () use (
            $model,
            $batchNumber,
            $locationId,
            $quantity,
            $expiresAt,
            $manufacturedAt,
            $lotNumber,
            $unitCostMinor,
            $supplierId,
            $purchaseOrderNumber
        ): InventoryBatch {
            $batch = InventoryBatch::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'batch_number' => $batchNumber,
                'lot_number' => $lotNumber,
                'location_id' => $locationId,
                'quantity_received' => $quantity,
                'quantity_on_hand' => $quantity,
                'quantity_reserved' => 0,
                'manufactured_at' => $manufacturedAt,
                'received_at' => now(),
                'expires_at' => $expiresAt,
                'status' => BatchStatus::Active->value,
                'unit_cost_minor' => $unitCostMinor,
                'supplier_id' => $supplierId,
                'purchase_order_number' => $purchaseOrderNumber,
            ]);

            Event::dispatch(new BatchCreated($batch, $model));

            return $batch;
        });
    }

    /**
     * Find batch by batch number.
     */
    public function findByBatchNumber(string $batchNumber): ?InventoryBatch
    {
        return InventoryBatch::where('batch_number', $batchNumber)->first();
    }

    /**
     * Get batches for a model.
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getBatchesForModel(Model $model): Collection
    {
        return InventoryBatch::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->orderBy('expires_at')
            ->orderBy('received_at')
            ->get();
    }

    /**
     * Get allocatable batches for a model using FEFO.
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getAllocatableBatches(Model $model, ?string $locationId = null): Collection
    {
        $query = InventoryBatch::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->allocatable()
            ->fefo();

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return $query->get();
    }

    /**
     * Get total available quantity across all batches.
     */
    public function getTotalAvailable(Model $model, ?string $locationId = null): int
    {
        $query = InventoryBatch::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->allocatable();

        if ($locationId !== null) {
            $query->atLocation($locationId);
        }

        return (int) $query->sum(DB::raw('quantity_on_hand - quantity_reserved'));
    }

    /**
     * Quarantine a batch.
     */
    public function quarantine(InventoryBatch $batch, string $reason): InventoryBatch
    {
        return DB::transaction(function () use ($batch, $reason): InventoryBatch {
            $batch->quarantine($reason);

            // Release any allocations from this batch
            foreach ($batch->allocations as $allocation) {
                $allocation->level?->decrementReserved($allocation->quantity);
                $allocation->delete();
            }

            return $batch->refresh();
        });
    }

    /**
     * Recall multiple batches.
     *
     * @param  Collection<int, InventoryBatch>|array<InventoryBatch>  $batches
     * @return Collection<int, InventoryBatch>
     */
    public function recallBatches(Collection | array $batches, string $reason): Collection
    {
        if (is_array($batches)) {
            $batches = new Collection($batches);
        }

        return DB::transaction(function () use ($batches, $reason): Collection {
            foreach ($batches as $batch) {
                $batch->recall($reason);

                // Release allocations
                foreach ($batch->allocations as $allocation) {
                    $allocation->level?->decrementReserved($allocation->quantity);
                    $allocation->delete();
                }
            }

            // Get representative inventoryable for event
            $firstBatch = $batches->first();

            if ($firstBatch?->inventoryable !== null) {
                Event::dispatch(new BatchRecalled($batches, $reason, $firstBatch->inventoryable));
            }

            return $batches;
        });
    }

    /**
     * Transfer batch to a new location.
     */
    public function transferBatch(InventoryBatch $batch, InventoryLocation $newLocation): InventoryBatch
    {
        if ($batch->quantity_reserved > 0) {
            throw new InvalidArgumentException('Cannot transfer batch with reserved quantity');
        }

        return DB::transaction(function () use ($batch, $newLocation): InventoryBatch {
            $batch->update([
                'location_id' => $newLocation->id,
            ]);

            return $batch->refresh();
        });
    }

    /**
     * Split a batch into two.
     */
    public function splitBatch(InventoryBatch $batch, int $quantityToSplit, string $newBatchNumber): InventoryBatch
    {
        if ($quantityToSplit <= 0 || $quantityToSplit >= $batch->available) {
            throw new InvalidArgumentException('Invalid quantity to split');
        }

        return DB::transaction(function () use ($batch, $quantityToSplit, $newBatchNumber): InventoryBatch {
            // Create new batch
            $newBatch = InventoryBatch::create([
                'inventoryable_type' => $batch->inventoryable_type,
                'inventoryable_id' => $batch->inventoryable_id,
                'batch_number' => $newBatchNumber,
                'lot_number' => $batch->lot_number,
                'supplier_batch_number' => $batch->supplier_batch_number,
                'location_id' => $batch->location_id,
                'quantity_received' => $quantityToSplit,
                'quantity_on_hand' => $quantityToSplit,
                'quantity_reserved' => 0,
                'manufactured_at' => $batch->manufactured_at,
                'received_at' => now(),
                'expires_at' => $batch->expires_at,
                'status' => $batch->status,
                'unit_cost_minor' => $batch->unit_cost_minor,
                'currency' => $batch->currency,
                'supplier_id' => $batch->supplier_id,
                'metadata' => [
                    'split_from' => $batch->batch_number,
                    'split_at' => now()->toIso8601String(),
                ],
            ]);

            // Reduce original batch
            $batch->decrement('quantity_on_hand', $quantityToSplit);

            return $newBatch;
        });
    }

    /**
     * Merge batches into one.
     *
     * @param  Collection<int, InventoryBatch>  $batches
     */
    public function mergeBatches(Collection $batches, string $newBatchNumber): InventoryBatch
    {
        if ($batches->count() < 2) {
            throw new InvalidArgumentException('Need at least 2 batches to merge');
        }

        // Validate all batches are for the same item
        $types = $batches->pluck('inventoryable_type')->unique();
        $ids = $batches->pluck('inventoryable_id')->unique();

        if ($types->count() > 1 || $ids->count() > 1) {
            throw new InvalidArgumentException('All batches must be for the same item');
        }

        // Check no reserved quantities
        if ($batches->sum('quantity_reserved') > 0) {
            throw new InvalidArgumentException('Cannot merge batches with reserved quantities');
        }

        return DB::transaction(function () use ($batches, $newBatchNumber): InventoryBatch {
            $firstBatch = $batches->first();
            $totalQuantity = $batches->sum('quantity_on_hand');
            $earliestExpiry = $batches->whereNotNull('expires_at')->min('expires_at');

            // Create merged batch
            $mergedBatch = InventoryBatch::create([
                'inventoryable_type' => $firstBatch->inventoryable_type,
                'inventoryable_id' => $firstBatch->inventoryable_id,
                'batch_number' => $newBatchNumber,
                'location_id' => $firstBatch->location_id,
                'quantity_received' => $totalQuantity,
                'quantity_on_hand' => $totalQuantity,
                'quantity_reserved' => 0,
                'received_at' => now(),
                'expires_at' => $earliestExpiry,
                'status' => BatchStatus::Active->value,
                'metadata' => [
                    'merged_from' => $batches->pluck('batch_number')->toArray(),
                    'merged_at' => now()->toIso8601String(),
                ],
            ]);

            // Delete old batches
            foreach ($batches as $batch) {
                $batch->delete();
            }

            return $mergedBatch;
        });
    }

    /**
     * Get batches expiring within days.
     *
     * @return Collection<int, InventoryBatch>
     */
    public function getExpiringBatches(int $days = 30): Collection
    {
        return InventoryBatch::query()
            ->active()
            ->expiringSoon($days)
            ->with(['location', 'inventoryable'])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Check and update expired batches.
     */
    public function processExpiredBatches(): int
    {
        return DB::transaction(function (): int {
            $expiredBatches = InventoryBatch::query()
                ->where('status', BatchStatus::Active->value)
                ->expired()
                ->get();

            foreach ($expiredBatches as $batch) {
                $batch->markExpired();

                if ($batch->inventoryable !== null) {
                    Event::dispatch(new BatchExpired($batch, $batch->inventoryable));
                }
            }

            return $expiredBatches->count();
        });
    }
}
