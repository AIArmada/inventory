<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Enums\ReorderUrgency;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Models\InventorySupplierLeadtime;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class ReplenishmentService
{
    public function __construct(
        private DemandForecastService $demandForecastService
    ) {}

    /**
     * Generate reorder suggestions for all items below reorder point.
     *
     * @return Collection<int, InventoryReorderSuggestion>
     */
    public function generateSuggestions(?string $locationId = null): Collection
    {
        if ($locationId !== null && InventoryOwnerScope::isEnabled()) {
            $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query()->whereKey($locationId))->exists();

            if (! $isAllowed) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        $query = InventoryLevel::query()
            ->needsReorder()
            ->with(['location']);

        InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        $levels = $query->get();
        $suggestions = new Collection;

        foreach ($levels as $level) {
            $model = $level->inventoryable;
            if ($model === null) {
                continue;
            }

            $existing = InventoryReorderSuggestion::query()
                ->forModel($model)
                ->where('location_id', $level->location_id)
                ->actionable()
                ->exists();

            if ($existing) {
                continue;
            }

            $suggestion = $this->createSuggestion($model, $level);
            if ($suggestion !== null) {
                $suggestions->push($suggestion);
            }
        }

        return $suggestions;
    }

    /**
     * Create a reorder suggestion for a specific model.
     */
    public function createSuggestion(
        Model $model,
        InventoryLevel $level
    ): ?InventoryReorderSuggestion {
        $supplier = $this->getPrimarySupplier($model);
        $leadTimeDays = $supplier?->lead_time_days ?? $level->lead_time_days ?? 7;

        $avgDailyDemand = (int) ceil($this->demandForecastService->calculateAverageDailyDemand(
            $model,
            30,
            $level->location_id
        ));

        if ($avgDailyDemand === 0) {
            $avgDailyDemand = 1;
        }

        $currentStock = $level->quantity_available;
        $reorderPoint = $level->safety_stock + ($avgDailyDemand * $leadTimeDays);
        $daysUntilStockout = $avgDailyDemand > 0 ? (int) floor($currentStock / $avgDailyDemand) : null;
        $expectedStockout = $daysUntilStockout !== null ? today()->addDays($daysUntilStockout) : null;

        $eoq = $this->calculateEOQ($model, $avgDailyDemand, $supplier);
        $suggestedQuantity = $this->calculateSuggestedQuantity($level, $avgDailyDemand, $leadTimeDays, $eoq);

        if ($supplier !== null) {
            $suggestedQuantity = $supplier->roundToOrderMultiple($suggestedQuantity);
        }

        $urgency = ReorderUrgency::fromDaysUntilStockout($daysUntilStockout, $leadTimeDays);

        return InventoryReorderSuggestion::create([
            'inventoryable_type' => $model->getMorphClass(),
            'inventoryable_id' => $model->getKey(),
            'location_id' => $level->location_id,
            'supplier_leadtime_id' => $supplier?->id,
            'status' => ReorderSuggestionStatus::Pending,
            'current_stock' => $currentStock,
            'reorder_point' => $reorderPoint,
            'suggested_quantity' => $suggestedQuantity,
            'economic_order_quantity' => $eoq,
            'average_daily_demand' => $avgDailyDemand,
            'lead_time_days' => $leadTimeDays,
            'expected_stockout_date' => $expectedStockout,
            'urgency' => $urgency,
            'trigger_reason' => 'Stock below reorder point',
            'calculation_details' => [
                'safety_stock' => $level->safety_stock,
                'max_stock' => $level->max_stock,
                'current_reserved' => $level->quantity_reserved,
                'demand_period_days' => 30,
            ],
        ]);
    }

    /**
     * Calculate Economic Order Quantity (EOQ).
     */
    public function calculateEOQ(
        Model $model,
        int $annualDemand,
        ?InventorySupplierLeadtime $supplier = null,
        int $orderingCostMinor = 5000,
        float $holdingCostPercentage = 0.25
    ): int {
        $annualDemand *= 365;

        if ($annualDemand === 0) {
            return $supplier?->minimum_order_quantity ?? 1;
        }

        $unitCost = $supplier?->unit_cost_minor ?? 1000;
        $holdingCost = $unitCost * $holdingCostPercentage;

        if ($holdingCost <= 0) {
            $holdingCost = 1;
        }

        $eoq = (int) ceil(sqrt((2 * $annualDemand * $orderingCostMinor) / $holdingCost));

        return max($eoq, $supplier?->minimum_order_quantity ?? 1);
    }

    /**
     * Get primary supplier for a model.
     */
    public function getPrimarySupplier(Model $model): ?InventorySupplierLeadtime
    {
        return InventorySupplierLeadtime::query()
            ->forModel($model)
            ->active()
            ->primary()
            ->first()
            ?? InventorySupplierLeadtime::query()
                ->forModel($model)
                ->active()
                ->orderedByLeadTime()
                ->first();
    }

    /**
     * Get all pending suggestions by urgency.
     *
     * @return Collection<int, InventoryReorderSuggestion>
     */
    public function getPendingSuggestions(): Collection
    {
        $query = InventoryReorderSuggestion::query()
            ->pending()
            ->byUrgency()
            ->with(['inventoryable', 'location', 'supplierLeadtime']);

        $this->applyOwnerScopeToSuggestionQuery($query);

        return $query->get();
    }

    /**
     * Get critical suggestions requiring immediate action.
     *
     * @return Collection<int, InventoryReorderSuggestion>
     */
    public function getCriticalSuggestions(): Collection
    {
        $query = InventoryReorderSuggestion::query()
            ->actionable()
            ->critical()
            ->with(['inventoryable', 'location', 'supplierLeadtime']);

        $this->applyOwnerScopeToSuggestionQuery($query);

        return $query->get();
    }

    /**
     * Approve a suggestion.
     */
    public function approve(InventoryReorderSuggestion $suggestion, ?string $approvedBy = null): bool
    {
        return $suggestion->approve($approvedBy);
    }

    /**
     * Bulk approve suggestions.
     *
     * @param  Collection<int, InventoryReorderSuggestion>  $suggestions
     */
    public function bulkApprove(Collection $suggestions, ?string $approvedBy = null): int
    {
        $approved = 0;

        foreach ($suggestions as $suggestion) {
            if ($suggestion->approve($approvedBy)) {
                $approved++;
            }
        }

        return $approved;
    }

    /**
     * Expire old actionable suggestions.
     */
    public function expireOld(int $daysOld = 14): int
    {
        $cutoff = now()->subDays($daysOld);

        $oldQuery = InventoryReorderSuggestion::query()
            ->actionable()
            ->where('created_at', '<', $cutoff);

        $this->applyOwnerScopeToSuggestionQuery($oldQuery);

        $old = $oldQuery->get();

        $expired = 0;

        foreach ($old as $suggestion) {
            if ($suggestion->expire()) {
                $expired++;
            }
        }

        return $expired;
    }

    /**
     * Get replenishment statistics.
     *
     * @return array{pending: int, approved: int, ordered: int, critical: int, total_value: int}
     */
    public function getStatistics(): array
    {
        $suggestionsQuery = InventoryReorderSuggestion::query()
            ->actionable()
            ->with('supplierLeadtime');

        $this->applyOwnerScopeToSuggestionQuery($suggestionsQuery);

        $suggestions = $suggestionsQuery->get();

        $pending = $suggestions->where('status', ReorderSuggestionStatus::Pending)->count();
        $approved = $suggestions->where('status', ReorderSuggestionStatus::Approved)->count();
        $orderedQuery = InventoryReorderSuggestion::query()
            ->where('status', ReorderSuggestionStatus::Ordered);

        $this->applyOwnerScopeToSuggestionQuery($orderedQuery);

        $ordered = $orderedQuery->count();
        $critical = $suggestions->where('urgency', ReorderUrgency::Critical)->count();

        $totalValue = $suggestions->sum(function ($s) {
            $unitCost = $s->supplierLeadtime?->unit_cost_minor ?? 0;

            return $s->suggested_quantity * $unitCost;
        });

        return [
            'pending' => $pending,
            'approved' => $approved,
            'ordered' => $ordered,
            'critical' => $critical,
            'total_value' => (int) $totalValue,
        ];
    }

    /**
     * @param  Builder<InventoryReorderSuggestion>  $query
     */
    private function applyOwnerScopeToSuggestionQuery(Builder $query): void
    {
        if (! InventoryOwnerScope::isEnabled()) {
            return;
        }

        $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::resolveOwner() === null;

        $query->where(function (Builder $builder) use ($includeNullLocation): void {
            $builder->whereHas('location', fn (Builder $locationQuery): Builder => InventoryOwnerScope::applyToLocationQuery($locationQuery));

            if ($includeNullLocation) {
                $builder->orWhereNull('location_id');
            }
        });
    }

    /**
     * Calculate suggested quantity.
     */
    private function calculateSuggestedQuantity(
        InventoryLevel $level,
        int $avgDailyDemand,
        int $leadTimeDays,
        ?int $eoq
    ): int {
        $safetyStock = $level->safety_stock ?? 0;
        $maxStock = $level->max_stock ?? ($safetyStock * 4);
        $currentStock = $level->quantity_available;

        $orderUpToLevel = $maxStock;

        $needed = $orderUpToLevel - $currentStock;

        $reviewPeriodDemand = $avgDailyDemand * ($leadTimeDays + 7);
        $minOrder = max($reviewPeriodDemand, $eoq ?? 1);

        return max($needed, $minOrder);
    }
}
