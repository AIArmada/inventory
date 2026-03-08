<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryCostLayer;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FifoCostService
{
    /**
     * Add a new FIFO cost layer.
     */
    public function addLayer(
        Model $model,
        int $quantity,
        int $unitCostMinor,
        ?string $locationId = null,
        ?string $batchId = null,
        ?string $reference = null,
        ?Carbon $layerDate = null
    ): InventoryCostLayer {
        if (InventoryOwnerScope::isEnabled()) {
            if ($locationId === null && InventoryOwnerScope::resolveOwner() !== null) {
                throw new InvalidArgumentException('Location is required when owner scoping is enabled');
            }

            if ($locationId !== null) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            if ($batchId !== null) {
                $isAllowed = InventoryOwnerScope::applyToQueryByLocationRelation(InventoryBatch::query(), 'location')
                    ->whereKey($batchId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid batch for current owner');
                }
            }
        }

        return InventoryCostLayer::create([
            'inventoryable_type' => $model->getMorphClass(),
            'inventoryable_id' => $model->getKey(),
            'location_id' => $locationId,
            'batch_id' => $batchId,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost_minor' => $unitCostMinor,
            'total_cost_minor' => $quantity * $unitCostMinor,
            'currency' => config('inventory.defaults.currency', 'MYR'),
            'reference' => $reference,
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => $layerDate ?? now(),
        ]);
    }

    /**
     * Consume quantity using FIFO ordering.
     *
     * @return array{consumed: int, cost: int, layers: array<int, array{layer_id: string, quantity: int, unit_cost: int}>}
     */
    public function consume(
        Model $model,
        int $quantity,
        ?string $locationId = null
    ): array {
        return DB::transaction(function () use ($model, $quantity, $locationId): array {
            $query = InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::Fifo)
                ->fifoOrder();

            if (InventoryOwnerScope::isEnabled()) {
                $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

                $query->where(function ($builder) use ($includeNullLocation): void {
                    InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                    if ($includeNullLocation) {
                        $builder->orWhereNull('location_id');
                    }
                });
            }

            if ($locationId !== null) {
                if (InventoryOwnerScope::isEnabled()) {
                    $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                        ->whereKey($locationId)
                        ->exists();

                    if (! $isAllowed) {
                        throw new InvalidArgumentException('Invalid location for current owner');
                    }
                }

                $query->where('location_id', $locationId);
            }

            $layers = $query->get();

            $totalConsumed = 0;
            $totalCost = 0;
            $consumedLayers = [];

            foreach ($layers as $layer) {
                if ($totalConsumed >= $quantity) {
                    break;
                }

                $neededQuantity = $quantity - $totalConsumed;
                $consumed = $layer->consume($neededQuantity);

                $totalConsumed += $consumed;
                $totalCost += $consumed * $layer->unit_cost_minor;

                $consumedLayers[] = [
                    'layer_id' => $layer->id,
                    'quantity' => $consumed,
                    'unit_cost' => $layer->unit_cost_minor,
                ];
            }

            return [
                'consumed' => $totalConsumed,
                'cost' => $totalCost,
                'layers' => $consumedLayers,
            ];
        });
    }

    /**
     * Calculate FIFO valuation for a model.
     *
     * @return array{quantity: int, value: int, average_cost: int, layers: int}
     */
    public function calculateValuation(Model $model, ?string $locationId = null): array
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::Fifo);

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $query->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        $layers = $query->get();

        $totalQuantity = 0;
        $totalValue = 0;
        $layerCount = 0;

        foreach ($layers as $layer) {
            $totalQuantity += $layer->remaining_quantity;
            $totalValue += $layer->remainingValue();
            $layerCount++;
        }

        return [
            'quantity' => $totalQuantity,
            'value' => $totalValue,
            'average_cost' => $totalQuantity > 0 ? (int) round($totalValue / $totalQuantity) : 0,
            'layers' => $layerCount,
        ];
    }

    /**
     * Get estimated COGS for a given quantity using FIFO.
     */
    public function estimateCogs(Model $model, int $quantity, ?string $locationId = null): int
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::Fifo)
            ->fifoOrder();

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $query->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        $layers = $query->get();

        $remaining = $quantity;
        $cost = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $layer->remaining_quantity);
            $cost += $take * $layer->unit_cost_minor;
            $remaining -= $take;
        }

        return $cost;
    }

    /**
     * Get all active layers for a model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, InventoryCostLayer>
     */
    public function getActiveLayers(Model $model, ?string $locationId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::Fifo)
            ->fifoOrder();

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $query->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        return $query->get();
    }

    /**
     * Get oldest layer for a model.
     */
    public function getOldestLayer(Model $model, ?string $locationId = null): ?InventoryCostLayer
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::Fifo)
            ->fifoOrder();

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $query->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        return $query->first();
    }

    /**
     * Check if sufficient quantity is available.
     */
    public function hasAvailableQuantity(Model $model, int $quantity, ?string $locationId = null): bool
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::Fifo);

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $query->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        if ($locationId !== null) {
            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        return $query->sum('remaining_quantity') >= $quantity;
    }
}
