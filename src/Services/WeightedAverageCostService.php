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

final class WeightedAverageCostService
{
    /**
     * Record a purchase and update weighted average.
     *
     * @return array{new_average_cost: int, layer: InventoryCostLayer}
     */
    public function recordPurchase(
        Model $model,
        int $quantity,
        int $unitCostMinor,
        ?string $locationId = null,
        ?string $batchId = null,
        ?string $reference = null,
        ?Carbon $layerDate = null
    ): array {
        return DB::transaction(function () use ($model, $quantity, $unitCostMinor, $locationId, $batchId, $reference, $layerDate): array {
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

            $currentValuation = $this->calculateValuation($model, $locationId);

            $totalQuantity = $currentValuation['quantity'] + $quantity;
            $totalValue = $currentValuation['value'] + ($quantity * $unitCostMinor);
            $newAverageCost = $totalQuantity > 0 ? (int) round($totalValue / $totalQuantity) : $unitCostMinor;

            $layer = InventoryCostLayer::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'location_id' => $locationId,
                'batch_id' => $batchId,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'unit_cost_minor' => $newAverageCost,
                'total_cost_minor' => $quantity * $newAverageCost,
                'currency' => config('inventory.defaults.currency', 'MYR'),
                'reference' => $reference,
                'costing_method' => CostingMethod::WeightedAverage,
                'layer_date' => $layerDate ?? now(),
            ]);

            $this->updateExistingLayers($model, $newAverageCost, $locationId);

            return [
                'new_average_cost' => $newAverageCost,
                'layer' => $layer,
            ];
        });
    }

    /**
     * Consume quantity using weighted average cost.
     *
     * @return array{consumed: int, cost: int, unit_cost: int}
     */
    public function consume(
        Model $model,
        int $quantity,
        ?string $locationId = null
    ): array {
        return DB::transaction(function () use ($model, $quantity, $locationId): array {
            $valuation = $this->calculateValuation($model, $locationId);

            if ($valuation['quantity'] < $quantity) {
                $quantity = $valuation['quantity'];
            }

            $unitCost = $valuation['average_cost'];
            $totalCost = $quantity * $unitCost;

            $query = InventoryCostLayer::query()
                ->forModel($model)
                ->withRemainingQuantity()
                ->usingMethod(CostingMethod::WeightedAverage)
                ->orderBy('layer_date', 'asc');

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

            foreach ($layers as $layer) {
                if ($remaining <= 0) {
                    break;
                }

                $consumed = $layer->consume($remaining);
                $remaining -= $consumed;
            }

            return [
                'consumed' => $quantity,
                'cost' => $totalCost,
                'unit_cost' => $unitCost,
            ];
        });
    }

    /**
     * Calculate weighted average valuation for a model.
     *
     * @return array{quantity: int, value: int, average_cost: int}
     */
    public function calculateValuation(Model $model, ?string $locationId = null): array
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::WeightedAverage);

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

        $totalQuantity = (int) $query->sum('remaining_quantity');

        $totalValue = (int) $query->selectRaw('SUM(remaining_quantity * unit_cost_minor) as total')
            ->value('total');

        return [
            'quantity' => $totalQuantity,
            'value' => $totalValue,
            'average_cost' => $totalQuantity > 0 ? (int) round($totalValue / $totalQuantity) : 0,
        ];
    }

    /**
     * Get current weighted average cost.
     */
    public function getCurrentAverageCost(Model $model, ?string $locationId = null): int
    {
        $valuation = $this->calculateValuation($model, $locationId);

        return $valuation['average_cost'];
    }

    /**
     * Recalculate and update all layer costs.
     */
    public function recalculate(Model $model, ?string $locationId = null): int
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::WeightedAverage);

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

        if ($layers->isEmpty()) {
            return 0;
        }

        $totalQuantity = $layers->sum('remaining_quantity');
        $totalValue = $layers->sum(fn ($layer) => $layer->remaining_quantity * $layer->unit_cost_minor);

        $newAverageCost = $totalQuantity > 0 ? (int) round($totalValue / $totalQuantity) : 0;

        $this->updateExistingLayers($model, $newAverageCost, $locationId);

        return $newAverageCost;
    }

    /**
     * Check if sufficient quantity is available.
     */
    public function hasAvailableQuantity(Model $model, int $quantity, ?string $locationId = null): bool
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::WeightedAverage);

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

    /**
     * Update existing layers with new average cost.
     */
    private function updateExistingLayers(Model $model, int $newAverageCost, ?string $locationId = null): void
    {
        $query = InventoryCostLayer::query()
            ->forModel($model)
            ->withRemainingQuantity()
            ->usingMethod(CostingMethod::WeightedAverage);

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

        $query->update(['unit_cost_minor' => $newAverageCost]);
    }
}
