<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Reports;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates stock level and availability reports.
 */
final class StockLevelReport
{
    /**
     * Get current stock summary by location.
     *
     * @return Collection<int, array{
     *     location_id: string,
     *     location_name: string,
     *     sku_count: int,
     *     total_quantity: int,
     *     total_value: int,
     *     low_stock_count: int,
     *     out_of_stock_count: int,
     * }>
     */
    public function getStockByLocation(): Collection
    {
        $query = InventoryLevel::query()
            ->select([
                'location_id',
                DB::raw('COUNT(DISTINCT CONCAT(inventoryable_type, inventoryable_id)) as sku_count'),
                DB::raw('SUM(quantity_on_hand) as total_quantity'),
            ])
            ->with('location:id,name')
            ->groupBy('location_id');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        return $query->get()
            ->map(fn ($row) => [
                'location_id' => $row->location_id,
                'location_name' => $row->location?->name ?? 'Unknown',
                'sku_count' => (int) $row->sku_count,
                'total_quantity' => (int) $row->total_quantity,
                'total_value' => 0, // Requires cost layer integration
                'low_stock_count' => 0,
                'out_of_stock_count' => 0,
            ]);
    }

    /**
     * Get ABC analysis (Pareto classification).
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     total_value: int,
     *     cumulative_percentage: float,
     *     classification: string,
     * }>
     */
    public function getAbcAnalysis(): Collection
    {
        $stocksQuery = InventoryLevel::query()
            ->select([
                'inventoryable_type',
                'inventoryable_id',
                DB::raw('SUM(quantity_on_hand) as total_quantity'),
            ])
            ->groupBy('inventoryable_type', 'inventoryable_id')

            ->orderByDesc('total_quantity');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($stocksQuery, 'location');
        }

        $stocks = $stocksQuery->get();

        $totalQuantity = $stocks->sum('total_quantity');
        if ($totalQuantity === 0) {
            return collect();
        }

        $cumulativeValue = 0;

        return $stocks->map(function ($stock) use ($totalQuantity, &$cumulativeValue) {
            $cumulativeValue += $stock->total_quantity;
            $cumulativePercentage = ($cumulativeValue / $totalQuantity) * 100;

            $classification = match (true) {
                $cumulativePercentage <= 80 => 'A',
                $cumulativePercentage <= 95 => 'B',
                default => 'C',
            };

            return [
                'inventoryable_type' => $stock->inventoryable_type,
                'inventoryable_id' => $stock->inventoryable_id,
                'total_value' => (int) $stock->total_quantity,
                'cumulative_percentage' => round($cumulativePercentage, 2),
                'classification' => $classification,
            ];
        });
    }

    /**
     * Get aging analysis for batches.
     *
     * @return Collection<int, array{
     *     age_range: string,
     *     batch_count: int,
     *     total_quantity: int,
     *     total_value: int,
     *     expiring_soon: int,
     * }>
     */
    public function getBatchAgingAnalysis(): Collection
    {
        $now = CarbonImmutable::now();

        $batchesQuery = InventoryBatch::query()
            ->whereNotNull('manufactured_at');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($batchesQuery, 'location');
        }

        $batches = $batchesQuery->get();

        $ranges = [
            '0-30 days' => [0, 30],
            '31-60 days' => [31, 60],
            '61-90 days' => [61, 90],
            '91-180 days' => [91, 180],
            '181-365 days' => [181, 365],
            'Over 1 year' => [366, PHP_INT_MAX],
        ];

        return collect($ranges)->map(function ($range, $label) use ($batches, $now) {
            $filtered = $batches->filter(function ($batch) use ($range, $now) {
                $age = CarbonImmutable::parse($batch->manufactured_at)->diffInDays($now);

                return $age >= $range[0] && $age <= $range[1];
            });

            $expiringSoon = $filtered->filter(
                fn ($batch) => $batch->expires_at !== null &&
                    CarbonImmutable::parse($batch->expires_at)->diffInDays($now) <= 30
            )->count();

            return [
                'age_range' => $label,
                'batch_count' => $filtered->count(),
                'total_quantity' => $filtered->sum('quantity'),
                'total_value' => $filtered->sum(fn ($b) => $b->quantity * ($b->unit_cost_minor ?? 0)),
                'expiring_soon' => $expiringSoon,
            ];
        })->values();
    }

    /**
     * Get reorder status report.
     *
     * @return array{
     *     items_below_reorder_point: int,
     *     pending_suggestions: int,
     *     approved_suggestions: int,
     *     total_suggested_value: int,
     *     urgent_reorders: int,
     * }
     */
    public function getReorderStatus(): array
    {
        $belowReorderPointQuery = InventoryLevel::query()
            ->needsReorder();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($belowReorderPointQuery, 'location');
        }

        $belowReorderPoint = (int) $belowReorderPointQuery
            ->selectRaw('COUNT(DISTINCT CONCAT(inventoryable_type, ":", inventoryable_id)) as aggregate')
            ->value('aggregate');

        $pendingSuggestionsQuery = InventoryReorderSuggestion::query()
            ->pending();

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $pendingSuggestionsQuery->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        $pendingSuggestions = $pendingSuggestionsQuery->count();

        $approvedSuggestionsQuery = InventoryReorderSuggestion::query()
            ->where('status', 'approved');

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $approvedSuggestionsQuery->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        $approvedSuggestions = $approvedSuggestionsQuery->count();

        $suggestedValue = 0; // Requires cost integration

        $urgentReordersQuery = InventoryReorderSuggestion::query()
            ->pending()
            ->critical();

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $urgentReordersQuery->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        $urgentReorders = $urgentReordersQuery->count();

        return [
            'items_below_reorder_point' => $belowReorderPoint,
            'pending_suggestions' => $pendingSuggestions,
            'approved_suggestions' => $approvedSuggestions,
            'total_suggested_value' => $suggestedValue,
            'urgent_reorders' => $urgentReorders,
        ];
    }

    /**
     * Get stock distribution analysis.
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     location_count: int,
     *     total_quantity: int,
     *     max_location_quantity: int,
     *     min_location_quantity: int,
     *     concentration_ratio: float,
     * }>
     */
    public function getStockDistribution(int $limit = 20): Collection
    {
        $query = InventoryLevel::query()
            ->select([
                'inventoryable_type',
                'inventoryable_id',
                DB::raw('COUNT(DISTINCT location_id) as location_count'),
                DB::raw('SUM(quantity_on_hand) as total_quantity'),
                DB::raw('MAX(quantity_on_hand) as max_quantity'),
                DB::raw('MIN(quantity_on_hand) as min_quantity'),
            ])
            ->groupBy('inventoryable_type', 'inventoryable_id')
            ->having('location_count', '>', 1)
            ->orderByDesc('total_quantity')
            ->limit($limit);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        return $query->get()
            ->map(fn ($row) => [
                'inventoryable_type' => $row->inventoryable_type,
                'inventoryable_id' => $row->inventoryable_id,
                'location_count' => (int) $row->location_count,
                'total_quantity' => (int) $row->total_quantity,
                'max_location_quantity' => (int) $row->max_quantity,
                'min_location_quantity' => (int) $row->min_quantity,
                'concentration_ratio' => $row->total_quantity > 0
                    ? round(($row->max_quantity / $row->total_quantity) * 100, 2)
                    : 0.0,
            ]);
    }

    /**
     * Get dead stock report (items with no movement).
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     quantity: int,
     *     value: int,
     *     location_id: string,
     *     days_stagnant: int,
     * }>
     */
    public function getDeadStock(int $daysThreshold = 90, int $limit = 50): Collection
    {
        $cutoffDate = CarbonImmutable::now()->subDays($daysThreshold);
        $tableName = config('inventory.table_names.levels', 'inventory_levels');

        $query = InventoryLevel::query()
            ->select([
                "{$tableName}.inventoryable_type",
                "{$tableName}.inventoryable_id",
                "{$tableName}.quantity_on_hand as quantity",
                "{$tableName}.location_id",
                "{$tableName}.updated_at",
            ])
            ->where("{$tableName}.quantity_on_hand", '>', 0)
            ->where("{$tableName}.updated_at", '<', $cutoffDate)
            ->orderBy("{$tableName}.updated_at")
            ->limit($limit);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        return $query->get()
            ->map(fn ($row) => [
                'inventoryable_type' => $row->inventoryable_type,
                'inventoryable_id' => $row->inventoryable_id,
                'quantity' => (int) $row->quantity,
                'value' => 0, // Requires cost layer integration
                'location_id' => $row->location_id,
                'days_stagnant' => CarbonImmutable::parse($row->updated_at)->diffInDays(now()),
            ]);
    }

    /**
     * Get stock accuracy metrics from cycle counts.
     *
     * @return array{
     *     total_counts: int,
     *     accurate_counts: int,
     *     accuracy_percentage: float,
     *     total_variance_units: int,
     *     total_variance_value: int,
     *     avg_variance_percentage: float,
     * }
     */
    public function getCycleCountMetrics(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): array {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $countsQuery = InventoryMovement::query()
            ->where('type', MovementType::Adjustment->value)
            ->where('reason', 'cycle_count')
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($countsQuery);
        }

        $counts = $countsQuery->get();

        $totalCounts = $counts->count();
        $accurateCounts = $counts->filter(
            fn ($c) => $c->quantity === 0
        )->count();

        $totalVariance = $counts->sum(fn ($c) => abs($c->quantity));

        return [
            'total_counts' => $totalCounts,
            'accurate_counts' => $accurateCounts,
            'accuracy_percentage' => $totalCounts > 0
                ? round(($accurateCounts / $totalCounts) * 100, 2)
                : 100.0,
            'total_variance_units' => (int) $totalVariance,
            'total_variance_value' => 0, // Requires cost layer integration
            'avg_variance_percentage' => 0.0,
        ];
    }
}
