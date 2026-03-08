<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Models\InventoryStandardCost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class StandardCostService
{
    /**
     * Set a new standard cost for a model.
     */
    public function setStandardCost(
        Model $model,
        int $standardCostMinor,
        Carbon $effectiveFrom,
        ?Carbon $effectiveTo = null,
        ?string $approvedBy = null,
        ?string $notes = null,
        ?array $metadata = null
    ): InventoryStandardCost {
        return DB::transaction(function () use (
            $model,
            $standardCostMinor,
            $effectiveFrom,
            $effectiveTo,
            $approvedBy,
            $notes,
            $metadata
        ): InventoryStandardCost {
            $currentCost = $this->getCurrentStandardCost($model);
            if ($currentCost !== null && $effectiveFrom <= now()) {
                $currentCost->update(['effective_to' => $effectiveFrom]);
            }

            return InventoryStandardCost::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'standard_cost_minor' => $standardCostMinor,
                'currency' => config('inventory.defaults.currency', 'MYR'),
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'approved_by' => $approvedBy,
                'notes' => $notes,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Get current standard cost for a model.
     */
    public function getCurrentStandardCost(Model $model): ?InventoryStandardCost
    {
        return InventoryStandardCost::query()
            ->forModel($model)
            ->current()
            ->first();
    }

    /**
     * Get standard cost effective at a specific date.
     */
    public function getStandardCostAt(Model $model, Carbon $date): ?InventoryStandardCost
    {
        return InventoryStandardCost::query()
            ->forModel($model)
            ->effectiveAt($date)
            ->first();
    }

    /**
     * Get current standard cost value in minor units.
     */
    public function getCurrentCostValue(Model $model): ?int
    {
        $cost = $this->getCurrentStandardCost($model);

        return $cost?->standard_cost_minor;
    }

    /**
     * Get cost value at a specific date.
     */
    public function getCostValueAt(Model $model, Carbon $date): ?int
    {
        $cost = $this->getStandardCostAt($model, $date);

        return $cost?->standard_cost_minor;
    }

    /**
     * Calculate valuation using standard cost.
     *
     * @return array{quantity: int, value: int, unit_cost: int|null}
     */
    public function calculateValuation(Model $model, int $quantity): array
    {
        $standardCost = $this->getCurrentCostValue($model);

        return [
            'quantity' => $quantity,
            'value' => $standardCost !== null ? $quantity * $standardCost : 0,
            'unit_cost' => $standardCost,
        ];
    }

    /**
     * Calculate variance between actual and standard cost.
     *
     * @return array{variance: int, variance_percentage: float|null, favorable: bool|null}
     */
    public function calculateVariance(Model $model, int $actualCostMinor): array
    {
        $standardCost = $this->getCurrentCostValue($model);

        if ($standardCost === null) {
            return [
                'variance' => 0,
                'variance_percentage' => null,
                'favorable' => null,
            ];
        }

        $variance = $actualCostMinor - $standardCost;
        $variancePercentage = $standardCost > 0
            ? ($variance / $standardCost) * 100
            : null;

        return [
            'variance' => $variance,
            'variance_percentage' => $variancePercentage,
            'favorable' => $variance < 0,
        ];
    }

    /**
     * Get standard cost history for a model.
     *
     * @return Collection<int, InventoryStandardCost>
     */
    public function getCostHistory(Model $model): Collection
    {
        return InventoryStandardCost::query()
            ->forModel($model)
            ->orderBy('effective_from', 'desc')
            ->get();
    }

    /**
     * Get upcoming standard cost changes.
     *
     * @return Collection<int, InventoryStandardCost>
     */
    public function getFutureCosts(Model $model): Collection
    {
        return InventoryStandardCost::query()
            ->forModel($model)
            ->future()
            ->orderBy('effective_from', 'asc')
            ->get();
    }

    /**
     * Expire the current standard cost.
     */
    public function expireCurrentCost(Model $model): bool
    {
        $currentCost = $this->getCurrentStandardCost($model);

        if ($currentCost === null) {
            return false;
        }

        return $currentCost->expire();
    }

    /**
     * Check if model has a current standard cost.
     */
    public function hasStandardCost(Model $model): bool
    {
        return InventoryStandardCost::query()
            ->forModel($model)
            ->current()
            ->exists();
    }

    /**
     * Schedule a future standard cost change.
     */
    public function scheduleCostChange(
        Model $model,
        int $newCostMinor,
        Carbon $effectiveFrom,
        ?string $approvedBy = null,
        ?string $notes = null
    ): InventoryStandardCost {
        if ($effectiveFrom <= now()) {
            throw new InvalidArgumentException('Scheduled cost changes must be in the future');
        }

        $currentCost = $this->getCurrentStandardCost($model);
        if ($currentCost !== null) {
            $currentCost->update(['effective_to' => $effectiveFrom]);
        }

        return InventoryStandardCost::create([
            'inventoryable_type' => $model->getMorphClass(),
            'inventoryable_id' => $model->getKey(),
            'standard_cost_minor' => $newCostMinor,
            'currency' => config('inventory.defaults.currency', 'MYR'),
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'approved_by' => $approvedBy,
            'notes' => $notes,
        ]);
    }

    /**
     * Cancel a scheduled future cost change.
     */
    public function cancelScheduledCost(InventoryStandardCost $scheduledCost): bool
    {
        if (! $scheduledCost->isFuture()) {
            throw new InvalidArgumentException('Can only cancel future scheduled costs');
        }

        return $scheduledCost->delete();
    }
}
