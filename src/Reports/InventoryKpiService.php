<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Reports;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Calculates key inventory performance indicators.
 */
final class InventoryKpiService
{
    /**
     * Calculate inventory turnover ratio.
     * Formula: COGS / Average Inventory Value
     */
    public function calculateTurnoverRatio(
        string $inventoryableType,
        string $inventoryableId,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subYear();
        $endDate ??= CarbonImmutable::now();

        $cogs = $this->calculateCostOfGoodsSold(
            $inventoryableType,
            $inventoryableId,
            $startDate,
            $endDate,
        );

        $averageInventory = $this->calculateAverageInventoryValue(
            $inventoryableType,
            $inventoryableId,
            $startDate,
            $endDate,
        );

        if ($averageInventory === 0) {
            return 0.0;
        }

        return round($cogs / $averageInventory, 2);
    }

    /**
     * Calculate days of inventory on hand.
     * Formula: (Average Inventory / COGS) × Days in Period
     */
    public function calculateDaysOnHand(
        string $inventoryableType,
        string $inventoryableId,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subYear();
        $endDate ??= CarbonImmutable::now();

        $turnover = $this->calculateTurnoverRatio(
            $inventoryableType,
            $inventoryableId,
            $startDate,
            $endDate,
        );

        if ($turnover === 0.0) {
            return 0.0;
        }

        $daysInPeriod = $startDate->diffInDays($endDate);

        return round($daysInPeriod / $turnover, 1);
    }

    /**
     * Calculate order fill rate.
     * Formula: Orders Completely Filled / Total Orders × 100
     */
    public function calculateFillRate(
        string $inventoryableType,
        string $inventoryableId,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $totalShipmentsQuery = InventoryMovement::query()
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId)
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($totalShipmentsQuery);
        }

        $totalShipments = $totalShipmentsQuery->count();

        if ($totalShipments === 0) {
            return 100.0;
        }

        $fulfilledShipmentsQuery = InventoryMovement::query()
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId)
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->where(function ($query): void {
                $query->whereNull('reason')
                    ->orWhere('reason', '!=', 'partial');
            });

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($fulfilledShipmentsQuery);
        }

        $fulfilledShipments = $fulfilledShipmentsQuery->count();

        return round(($fulfilledShipments / $totalShipments) * 100, 2);
    }

    /**
     * Calculate stockout rate.
     * Formula: Days Out of Stock / Total Days × 100
     */
    public function calculateStockoutRate(
        string $inventoryableType,
        string $inventoryableId,
        ?string $locationId = null,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $totalDays = $startDate->diffInDays($endDate);
        if ($totalDays === 0) {
            return 0.0;
        }

        if ($locationId !== null) {
            $this->getScopedLocationOrFail($locationId);
        }

        // Count days where stock went to zero
        $stockoutEventsQuery = InventoryMovement::query()
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId)
            ->when($locationId, fn ($q) => $q->where('from_location_id', $locationId))
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($stockoutEventsQuery);
        }

        $stockoutEvents = $stockoutEventsQuery->count();

        return round(($stockoutEvents / $totalDays) * 100, 2);
    }

    /**
     * Calculate inventory accuracy.
     * Formula: Correct Counts / Total Counts × 100
     */
    public function calculateInventoryAccuracy(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        // Count cycle count adjustments
        $totalCountsQuery = InventoryMovement::query()
            ->where('type', MovementType::Adjustment->value)
            ->where('reason', 'cycle_count')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($totalCountsQuery);
        }

        $totalCounts = $totalCountsQuery->count();

        if ($totalCounts === 0) {
            return 100.0;
        }

        // Accurate counts are those with zero adjustment
        $accurateCountsQuery = InventoryMovement::query()
            ->where('type', MovementType::Adjustment->value)
            ->where('reason', 'cycle_count')
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->where('quantity', 0);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($accurateCountsQuery);
        }

        $accurateCounts = $accurateCountsQuery->count();

        return round(($accurateCounts / $totalCounts) * 100, 2);
    }

    /**
     * Calculate carrying cost as percentage of inventory value.
     */
    public function calculateCarryingCostRate(
        int $annualCarryingCostMinor,
        int $averageInventoryValueMinor,
    ): float {
        if ($averageInventoryValueMinor === 0) {
            return 0.0;
        }

        return round(($annualCarryingCostMinor / $averageInventoryValueMinor) * 100, 2);
    }

    /**
     * Get comprehensive KPI dashboard data.
     *
     * @return array{
     *     total_sku_count: int,
     *     total_inventory_value: int,
     *     average_turnover_ratio: float,
     *     average_days_on_hand: float,
     *     overall_fill_rate: float,
     *     inventory_accuracy: float,
     *     low_stock_items: int,
     *     out_of_stock_items: int,
     * }
     */
    public function getDashboardKpis(): array
    {
        $stockLevelsQuery = InventoryLevel::query()
            ->select([
                'inventoryable_type',
                'inventoryable_id',
                DB::raw('SUM(quantity_on_hand) as total_quantity'),
            ])
            ->groupBy('inventoryable_type', 'inventoryable_id');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($stockLevelsQuery, 'location');
        }

        $stockLevels = $stockLevelsQuery->get();

        $totalSkus = $stockLevels->count();
        $totalValue = 0; // Value calculation requires cost layer integration

        $lowStockQuery = InventoryLevel::query()
            ->lowStock();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($lowStockQuery, 'location');
        }

        $lowStockItems = (int) $lowStockQuery
            ->selectRaw("COUNT(DISTINCT CONCAT(inventoryable_type, ':', inventoryable_id)) as aggregate")
            ->value('aggregate');

        $outOfStockQuery = InventoryLevel::query()
            ->where('quantity_on_hand', '<=', 0);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($outOfStockQuery, 'location');
        }

        $outOfStockItems = (int) $outOfStockQuery
            ->selectRaw("COUNT(DISTINCT CONCAT(inventoryable_type, ':', inventoryable_id)) as aggregate")
            ->value('aggregate');

        return [
            'total_sku_count' => $totalSkus,
            'total_inventory_value' => (int) $totalValue,
            'average_turnover_ratio' => $this->calculateOverallTurnoverRatio(),
            'average_days_on_hand' => $this->calculateOverallDaysOnHand(),
            'overall_fill_rate' => $this->calculateOverallFillRate(),
            'inventory_accuracy' => $this->calculateInventoryAccuracy(),
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
        ];
    }

    /**
     * Get trend data for KPIs over time.
     *
     * @return Collection<int, array{date: string, turnover: float, fill_rate: float, accuracy: float}>
     */
    public function getKpiTrends(int $months = 6): Collection
    {
        $trends = collect();
        $endDate = CarbonImmutable::now();

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = $endDate->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->endOfMonth();

            $trends->push([
                'date' => $monthStart->format('Y-m'),
                'turnover' => $this->calculateOverallTurnoverRatio($monthStart, $monthEnd),
                'fill_rate' => $this->calculateOverallFillRate($monthStart, $monthEnd),
                'accuracy' => $this->calculateInventoryAccuracy($monthStart, $monthEnd),
            ]);
        }

        return $trends;
    }

    /**
     * Calculate COGS from shipment movements.
     */
    private function calculateCostOfGoodsSold(
        string $inventoryableType,
        string $inventoryableId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): int {
        // Sum quantity from shipment movements
        $query = InventoryMovement::query()
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId)
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        return (int) $query->sum('quantity');
    }

    /**
     * Calculate average inventory value over a period.
     */
    private function calculateAverageInventoryValue(
        string $inventoryableType,
        string $inventoryableId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): int {
        // Get current stock level as approximation
        $query = InventoryLevel::query()
            ->where('inventoryable_type', $inventoryableType)
            ->where('inventoryable_id', $inventoryableId);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        $currentStock = (int) $query->sum('quantity_on_hand');

        return $currentStock;
    }

    /**
     * Calculate overall turnover ratio across all inventory.
     */
    private function calculateOverallTurnoverRatio(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subYear();
        $endDate ??= CarbonImmutable::now();

        $totalCogsQuery = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($totalCogsQuery);
        }

        $totalCogs = (int) $totalCogsQuery->sum('quantity');

        $totalInventoryQuery = InventoryLevel::query();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($totalInventoryQuery, 'location');
        }

        $totalInventory = (int) $totalInventoryQuery->sum('quantity_on_hand');

        if ($totalInventory === 0) {
            return 0.0;
        }

        return round($totalCogs / $totalInventory, 2);
    }

    /**
     * Calculate overall days on hand.
     */
    private function calculateOverallDaysOnHand(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $turnover = $this->calculateOverallTurnoverRatio($startDate, $endDate);

        if ($turnover === 0.0) {
            return 0.0;
        }

        return round(365 / $turnover, 1);
    }

    /**
     * Calculate overall fill rate.
     */
    private function calculateOverallFillRate(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): float {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $totalShipmentsQuery = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($totalShipmentsQuery);
        }

        $totalShipments = $totalShipmentsQuery->count();

        if ($totalShipments === 0) {
            return 100.0;
        }

        // Count shipments that are not marked as partial
        $fulfilledShipmentsQuery = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->where(function ($query): void {
                $query->whereNull('reason')
                    ->orWhere('reason', '!=', 'partial');
            });

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($fulfilledShipmentsQuery);
        }

        $fulfilledShipments = $fulfilledShipmentsQuery->count();

        return round(($fulfilledShipments / $totalShipments) * 100, 2);
    }

    private function getScopedLocationOrFail(string $locationId): InventoryLocation
    {
        $query = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());

        $location = $query->whereKey($locationId)->first();

        if ($location === null) {
            throw new InvalidArgumentException('Invalid location for current owner');
        }

        return $location;
    }
}
