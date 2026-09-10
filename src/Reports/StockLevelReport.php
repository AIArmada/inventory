<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Reports;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates stock level and availability reports.
 */
final class StockLevelReport
{
    /**
     * Get the summary used by inventory dashboards.
     *
     * @return array{total_locations: int, active_locations: int, total_skus: int, total_on_hand: int, total_reserved: int, active_allocations: int}
     */
    public function getOverview(): array
    {
        $locationTotals = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->selectRaw('COUNT(*) as total_locations')
            ->selectRaw('SUM(CASE WHEN is_active = ? THEN 1 ELSE 0 END) as active_locations', [true])
            ->toBase()
            ->first();

        $levelTotals = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->selectRaw('COALESCE(SUM(quantity_on_hand), 0) as total_on_hand')
            ->selectRaw('COALESCE(SUM(quantity_reserved), 0) as total_reserved')
            ->toBase()
            ->first();

        $distinctSkus = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->select('inventoryable_type', 'inventoryable_id')
            ->distinct();

        return [
            'total_locations' => (int) ($locationTotals->total_locations ?? 0),
            'active_locations' => (int) ($locationTotals->active_locations ?? 0),
            'total_skus' => (int) DB::query()
                ->fromSub($distinctSkus->toBase(), 'distinct_skus')
                ->count(),
            'total_on_hand' => (int) ($levelTotals->total_on_hand ?? 0),
            'total_reserved' => (int) ($levelTotals->total_reserved ?? 0),
            'active_allocations' => InventoryOwnerScope::applyToLocationQuery(
                InventoryAllocation::query()->active()
            )->count(),
        ];
    }

    /**
     * Count active levels below an available-quantity threshold.
     */
    public function getLowInventoryCount(?int $threshold = null): int
    {
        $threshold ??= config('inventory.default_reorder_point', 10);

        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= ?', [$threshold])
            ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
            ->count();
    }

    /**
     * Count active levels with no available quantity.
     */
    public function getOutOfStockCount(): int
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= 0')
            ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
            ->count();
    }

    /**
     * Count active levels below their configured reorder point.
     */
    public function getLowStockCount(): int
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereRaw('quantity_on_hand - quantity_reserved <= reorder_point')
            ->where('reorder_point', '>', 0)
            ->count();
    }

    /**
     * Get active levels that need replenishment.
     *
     * @return Builder<InventoryLevel>
     */
    public function getLowStockQuery(): Builder
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->with('location')
            ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
            ->whereRaw('quantity_on_hand - quantity_reserved <= reorder_point')
            ->where('reorder_point', '>', 0)
            ->addSelect(DB::raw('(reorder_point - (quantity_on_hand - quantity_reserved)) AS deficit'))
            ->orderByRaw('reorder_point - (quantity_on_hand - quantity_reserved) DESC');
    }

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
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->select([
                    'location_id',
                    DB::raw('COUNT(DISTINCT CONCAT(inventoryable_type, inventoryable_id)) as sku_count'),
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                ])
                ->with('location:id,name')
                ->groupBy('location_id')
        );

        /** @var Collection<int, array{location_id: string, location_name: string, sku_count: int, total_quantity: int, total_value: int, low_stock_count: int, out_of_stock_count: int}> $stockByLocation */
        $stockByLocation = $query->get()
            ->map(fn ($row): array => [
                'location_id' => (string) $row->location_id,
                'location_name' => (string) ($row->location?->name ?? 'Unknown'),
                'sku_count' => (int) $row->sku_count,
                'total_quantity' => (int) $row->total_quantity,
                'total_value' => (int) 0, // Requires cost layer integration
                'low_stock_count' => (int) 0,
                'out_of_stock_count' => (int) 0,
            ]);

        return $stockByLocation;
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
        $stocksQuery = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->select([
                    'inventoryable_type',
                    'inventoryable_id',
                    DB::raw('SUM(quantity_on_hand) as total_quantity'),
                ])
                ->groupBy('inventoryable_type', 'inventoryable_id')
                ->orderByDesc('total_quantity')
        );

        $stocks = $stocksQuery->get();

        $totalQuantity = $stocks->sum('total_quantity');
        if ($totalQuantity === 0) {
            return collect();
        }

        $cumulativeValue = 0;

        /** @var Collection<int, array{inventoryable_type: string, inventoryable_id: string, total_value: int, cumulative_percentage: float, classification: string}> $analysis */
        $analysis = $stocks->map(function ($stock) use ($totalQuantity, &$cumulativeValue): array {
            $cumulativeValue += $stock->total_quantity;
            $cumulativePercentage = ($cumulativeValue / $totalQuantity) * 100;

            $classification = match (true) {
                $cumulativePercentage <= 80 => 'A',
                $cumulativePercentage <= 95 => 'B',
                default => 'C',
            };

            return [
                'inventoryable_type' => (string) $stock->inventoryable_type,
                'inventoryable_id' => (string) $stock->inventoryable_id,
                'total_value' => (int) $stock->total_quantity,
                'cumulative_percentage' => round($cumulativePercentage, 2),
                'classification' => (string) $classification,
            ];
        });

        return $analysis;
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

        $batchesQuery = InventoryOwnerScope::applyToLocationQuery(
            InventoryBatch::query()->whereNotNull('manufactured_at')
        );

        $batches = $batchesQuery->get();

        $ranges = [
            '0-30 days' => [0, 30],
            '31-60 days' => [31, 60],
            '61-90 days' => [61, 90],
            '91-180 days' => [91, 180],
            '181-365 days' => [181, 365],
            'Over 1 year' => [366, PHP_INT_MAX],
        ];

        return collect($ranges)->map(function ($range, $label) use ($batches, $now): array {
            $filtered = $batches->filter(function ($batch) use ($range, $now) {
                $age = CarbonImmutable::parse($batch->manufactured_at)->diffInDays($now);

                return $age >= $range[0] && $age <= $range[1];
            });

            $expiringSoon = $filtered->filter(
                fn ($batch) => $batch->expires_at !== null &&
                    CarbonImmutable::parse($batch->expires_at)->diffInDays($now) <= 30
            )->count();

            return [
                'age_range' => (string) $label,
                'batch_count' => (int) $filtered->count(),
                'total_quantity' => (int) $filtered->sum('quantity'),
                'total_value' => (int) $filtered->sum(fn ($b) => $b->quantity * ($b->unit_cost_minor ?? 0)),
                'expiring_soon' => (int) $expiringSoon,
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
        $belowReorderPointQuery = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()->needsReorder()
        );

        $belowReorderPoint = (int) $belowReorderPointQuery
            ->selectRaw('COUNT(DISTINCT CONCAT(inventoryable_type, ":", inventoryable_id)) as aggregate')
            ->value('aggregate');

        $pendingSuggestionsQuery = InventoryOwnerScope::applyToLocationQuery(
            InventoryReorderSuggestion::query()->pending()
        );

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            if (! $includeNullLocation) {
                $pendingSuggestionsQuery->whereNotNull('location_id');
            }
        }

        $pendingSuggestions = $pendingSuggestionsQuery->count();

        $approvedSuggestionsQuery = InventoryOwnerScope::applyToLocationQuery(
            InventoryReorderSuggestion::query()->where('status', 'approved')
        );

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            if (! $includeNullLocation) {
                $approvedSuggestionsQuery->whereNotNull('location_id');
            }
        }

        $approvedSuggestions = $approvedSuggestionsQuery->count();

        $suggestedValue = 0; // Requires cost integration

        $urgentReordersQuery = InventoryOwnerScope::applyToLocationQuery(
            InventoryReorderSuggestion::query()->pending()->critical()
        );

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            if (! $includeNullLocation) {
                $urgentReordersQuery->whereNotNull('location_id');
            }
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
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
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
                ->limit($limit)
        );

        return $query->get()
            ->map(fn ($row): array => [
                'inventoryable_type' => (string) $row->inventoryable_type,
                'inventoryable_id' => (string) $row->inventoryable_id,
                'location_count' => (int) $row->location_count,
                'total_quantity' => (int) $row->total_quantity,
                'max_location_quantity' => (int) $row->max_quantity,
                'min_location_quantity' => (int) $row->min_quantity,
                'concentration_ratio' => (int) $row->total_quantity > 0
                    ? round(((int) $row->max_quantity / (int) $row->total_quantity) * 100, 2)
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
        $tableName = config('inventory.database.tables.levels', 'inventory_levels');

        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
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
                ->limit($limit)
        );

        /** @var Collection<int, array{inventoryable_type: string, inventoryable_id: string, quantity: int, value: int, location_id: string, days_stagnant: int}> $deadStock */
        $deadStock = $query->get()
            ->map(fn ($row): array => [
                'inventoryable_type' => (string) $row->inventoryable_type,
                'inventoryable_id' => (string) $row->inventoryable_id,
                'quantity' => (int) $row->quantity,
                'value' => (int) 0, // Requires cost layer integration
                'location_id' => (string) $row->location_id,
                'days_stagnant' => (int) CarbonImmutable::parse($row->updated_at)->diffInDays(CarbonImmutable::now()),
            ]);

        return $deadStock;
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
