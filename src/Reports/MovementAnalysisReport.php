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
 * Generates inventory movement analysis reports.
 */
final class MovementAnalysisReport
{
    /**
     * Get movement summary by type for a period.
     *
     * @return Collection<int, array{
     *     movement_type: string,
     *     count: int,
     *     total_quantity: int,
     *     total_value: int,
     * }>
     */
    public function getMovementSummaryByType(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
        ?string $locationId = null,
    ): Collection {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        if ($locationId !== null) {
            $this->getScopedLocationOrFail($locationId);
        }

        $query = InventoryMovement::query()
            ->select([
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(quantity) as total_quantity'),
            ])
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->when($locationId, fn ($q) => $q->where(function ($query) use ($locationId): void {
                $query->where('from_location_id', $locationId)
                    ->orWhere('to_location_id', $locationId);
            }))
            ->groupBy('type')

            ->orderByDesc('count');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        /** @var Collection<int, array{movement_type: string, count: int, total_quantity: int, total_value: int}> $summary */
        $summary = $query->get()
            ->map(fn ($row): array => [
                'movement_type' => (string) $row->type,
                'count' => (int) $row->count,
                'total_quantity' => (int) $row->total_quantity,
                'total_value' => (int) 0, // Value requires cost layer integration
            ]);

        return $summary;
    }

    /**
     * Get daily movement trends.
     *
     * @return Collection<int, array{
     *     date: string,
     *     receipts: int,
     *     shipments: int,
     *     adjustments: int,
     *     transfers: int,
     * }>
     */
    public function getDailyMovementTrends(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): Collection {
        $startDate ??= CarbonImmutable::now()->subDays(30);
        $endDate ??= CarbonImmutable::now();

        $movementsQuery = InventoryMovement::query()
            ->select([
                DB::raw('DATE(occurred_at) as date'),
                'type',
                DB::raw('SUM(quantity) as total'),
            ])
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(occurred_at)'), 'type');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($movementsQuery);
        }

        $movements = $movementsQuery->get();

        $dates = collect();
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $dayMovements = $movements->where('date', $dateStr);

            $dates->push([
                'date' => $dateStr,
                'receipts' => (int) $dayMovements->where('type', MovementType::Receipt->value)->sum('total'),
                'shipments' => (int) $dayMovements->where('type', MovementType::Shipment->value)->sum('total'),
                'adjustments' => (int) $dayMovements->where('type', MovementType::Adjustment->value)->sum('total'),
                'transfers' => (int) $dayMovements->where('type', MovementType::Transfer->value)->sum('total'),
            ]);

            $current = $current->addDay();
        }

        return $dates;
    }

    /**
     * Get top movers (most active SKUs).
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     movement_count: int,
     *     total_quantity_moved: int,
     *     avg_quantity_per_movement: float,
     * }>
     */
    public function getTopMovers(
        int $limit = 10,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): Collection {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $query = InventoryMovement::query()
            ->select([
                'inventoryable_type',
                'inventoryable_id',
                DB::raw('COUNT(*) as movement_count'),
                DB::raw('SUM(quantity) as total_quantity_moved'),
                DB::raw('AVG(quantity) as avg_quantity'),
            ])
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->groupBy('inventoryable_type', 'inventoryable_id')
            ->orderByDesc('movement_count')
            ->limit($limit);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        return $query->get()
            ->map(fn ($row): array => [
                'inventoryable_type' => (string) $row->inventoryable_type,
                'inventoryable_id' => (string) $row->inventoryable_id,
                'movement_count' => (int) $row->movement_count,
                'total_quantity_moved' => (int) $row->total_quantity_moved,
                'avg_quantity_per_movement' => round((float) $row->avg_quantity, 2),
            ]);
    }

    /**
     * Get slow movers (least active SKUs with stock).
     *
     * @return Collection<int, array{
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     current_quantity: int,
     *     last_movement_at: string|null,
     *     days_since_movement: int,
     * }>
     */
    public function getSlowMovers(
        int $limit = 10,
        int $minDaysSinceMovement = 30,
    ): Collection {
        $cutoffDate = CarbonImmutable::now()->subDays($minDaysSinceMovement);

        $lastMovementsQuery = InventoryMovement::query()
            ->select([
                'inventoryable_type',
                'inventoryable_id',
                DB::raw('MAX(occurred_at) as last_occurred_at'),
            ])
            ->groupBy('inventoryable_type', 'inventoryable_id')
            ->having('last_occurred_at', '<', $cutoffDate);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($lastMovementsQuery);
        }

        $lastMovements = $lastMovementsQuery->get()
            ->keyBy(fn ($row) => $row->inventoryable_type . ':' . $row->inventoryable_id);

        $inventoryablesWithAnyMovementQuery = InventoryMovement::query()
            ->select(['inventoryable_type', 'inventoryable_id'])
            ->distinct();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($inventoryablesWithAnyMovementQuery);
        }

        $inventoryablesWithAnyMovement = $inventoryablesWithAnyMovementQuery
            ->get()
            ->keyBy(fn ($row) => $row->inventoryable_type . ':' . $row->inventoryable_id);

        $levelsQuery = InventoryLevel::query()
            ->select([
                'inventoryable_type',
                'inventoryable_id',
                DB::raw('SUM(quantity_on_hand) as current_quantity'),
            ])
            ->where('quantity_on_hand', '>', 0)
            ->groupBy('inventoryable_type', 'inventoryable_id');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($levelsQuery, 'location');
        }

        /** @var list<array{inventoryable_type: string, inventoryable_id: string, current_quantity: int, last_movement_at: string|null, days_since_movement: int}> $slowMovers */
        $slowMovers = [];

        foreach ($levelsQuery->get() as $level) {
            $key = $level->inventoryable_type . ':' . $level->inventoryable_id;

            if (! $lastMovements->has($key) && $inventoryablesWithAnyMovement->has($key)) {
                continue;
            }

            $lastMovement = $lastMovements->get($key);

            $lastOccurredAt = $lastMovement?->last_occurred_at;
            $lastMovementAt = $lastOccurredAt !== null
                ? CarbonImmutable::parse($lastOccurredAt)->toDateTimeString()
                : null;
            $daysSince = $lastOccurredAt !== null
                ? CarbonImmutable::parse($lastOccurredAt)->diffInDays(now())
                : 999;

            $slowMovers[] = [
                'inventoryable_type' => (string) $level->inventoryable_type,
                'inventoryable_id' => (string) $level->inventoryable_id,
                'current_quantity' => (int) $level->current_quantity,
                'last_movement_at' => $lastMovementAt,
                'days_since_movement' => (int) $daysSince,
            ];
        }

        usort($slowMovers, static function (array $left, array $right): int {
            return $right['days_since_movement'] <=> $left['days_since_movement'];
        });

        $slowMovers = array_slice($slowMovers, 0, $limit);

        // @phpstan-ignore return.type
        return collect($slowMovers);
    }

    /**
     * Get movement velocity (rate of change).
     *
     * @return array{
     *     receipts_per_day: float,
     *     shipments_per_day: float,
     *     net_change_per_day: float,
     *     days_of_stock_at_current_rate: float,
     * }
     */
    public function getMovementVelocity(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): array {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();
        $days = max(1, $startDate->diffInDays($endDate));

        $receiptsQuery = InventoryMovement::query()
            ->where('type', MovementType::Receipt->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($receiptsQuery);
        }

        $receipts = (int) $receiptsQuery->sum('quantity');

        $shipmentsQuery = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($shipmentsQuery);
        }

        $shipments = (int) $shipmentsQuery->sum('quantity');

        $currentStockQuery = InventoryLevel::query();

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($currentStockQuery, 'location');
        }

        $currentStock = (int) $currentStockQuery->sum('quantity_on_hand');

        $shipmentsPerDay = $shipments / $days;
        $netChangePerDay = ($receipts - $shipments) / $days;

        return [
            'receipts_per_day' => round($receipts / $days, 2),
            'shipments_per_day' => round($shipmentsPerDay, 2),
            'net_change_per_day' => round($netChangePerDay, 2),
            'days_of_stock_at_current_rate' => $shipmentsPerDay > 0
                ? round($currentStock / $shipmentsPerDay, 1)
                : 0.0,
        ];
    }

    /**
     * Get adjustment analysis (reasons and frequency).
     *
     * @return Collection<int, array{
     *     reason: string,
     *     count: int,
     *     total_positive: int,
     *     total_negative: int,
     *     net_adjustment: int,
     * }>
     */
    public function getAdjustmentAnalysis(
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): Collection {
        $startDate ??= CarbonImmutable::now()->subMonth();
        $endDate ??= CarbonImmutable::now();

        $query = InventoryMovement::query()
            ->where('type', MovementType::Adjustment->value)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToMovementQuery($query);
        }

        /** @var Collection<int, array{reason: string, count: int, total_positive: int, total_negative: int, net_adjustment: int}> $analysis */
        $analysis = $query->get()
            ->groupBy(fn ($m) => (string) ($m->reason ?? 'unknown'))
            ->map(function ($movements, $reason): array {
                $totalPositive = (int) $movements
                    ->filter(fn ($movement) => $movement->quantity > 0)
                    ->sum('quantity');
                $totalNegative = abs((int) $movements
                    ->filter(fn ($movement) => $movement->quantity < 0)
                    ->sum('quantity'));

                return [
                    'reason' => (string) $reason,
                    'count' => (int) $movements->count(),
                    'total_positive' => $totalPositive,
                    'total_negative' => $totalNegative,
                    'net_adjustment' => (int) $movements->sum('quantity'),
                ];
            })
            ->sortByDesc('count')
            ->values();

        return $analysis;
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
