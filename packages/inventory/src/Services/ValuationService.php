<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryCostLayer;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ValuationService
{
    public function __construct(
        private FifoCostService $fifoCostService,
        private WeightedAverageCostService $weightedAverageCostService,
        private StandardCostService $standardCostService
    ) {}

    /**
     * Calculate valuation for a model using specified costing method.
     *
     * @return array{quantity: int, value: int, average_cost: int}
     */
    public function calculateValuation(
        Model $model,
        CostingMethod $method,
        ?string $locationId = null
    ): array {
        return match ($method) {
            CostingMethod::Fifo => $this->fifoCostService->calculateValuation($model, $locationId),
            CostingMethod::WeightedAverage => $this->weightedAverageCostService->calculateValuation($model, $locationId),
            CostingMethod::Standard => $this->calculateStandardValuation($model, $locationId),
            default => $this->fifoCostService->calculateValuation($model, $locationId),
        };
    }

    /**
     * Get total inventory valuation for a location.
     *
     * @return array{total_quantity: int, total_value: int, sku_count: int}
     */
    public function getLocationValuation(string $locationId, CostingMethod $method): array
    {
        $layers = InventoryCostLayer::query()
            ->where('location_id', $locationId)
            ->withRemainingQuantity()
            ->usingMethod($method)
            ->get();

        $totalQuantity = 0;
        $totalValue = 0;
        $skuSet = [];

        foreach ($layers as $layer) {
            $totalQuantity += $layer->remaining_quantity;
            $totalValue += $layer->remainingValue();
            $skuSet[$layer->inventoryable_type . '-' . $layer->inventoryable_id] = true;
        }

        return [
            'total_quantity' => $totalQuantity,
            'total_value' => $totalValue,
            'sku_count' => count($skuSet),
        ];
    }

    /**
     * Get total inventory valuation across all locations.
     *
     * @return array{total_quantity: int, total_value: int, sku_count: int, location_count: int}
     */
    public function getTotalValuation(CostingMethod $method): array
    {
        $layers = InventoryCostLayer::query()
            ->withRemainingQuantity()
            ->usingMethod($method)
            ->get();

        $totalQuantity = 0;
        $totalValue = 0;
        $skuSet = [];
        $locationSet = [];

        foreach ($layers as $layer) {
            $totalQuantity += $layer->remaining_quantity;
            $totalValue += $layer->remainingValue();
            $skuSet[$layer->inventoryable_type . '-' . $layer->inventoryable_id] = true;
            if ($layer->location_id) {
                $locationSet[$layer->location_id] = true;
            }
        }

        return [
            'total_quantity' => $totalQuantity,
            'total_value' => $totalValue,
            'sku_count' => count($skuSet),
            'location_count' => count($locationSet),
        ];
    }

    /**
     * Create a valuation snapshot.
     */
    public function createSnapshot(
        CostingMethod $method,
        ?string $locationId = null,
        ?Carbon $snapshotDate = null
    ): InventoryValuationSnapshot {
        $snapshotDate = $snapshotDate ?? today();

        $valuation = $locationId !== null
            ? $this->getLocationValuation($locationId, $method)
            : $this->getTotalValuation($method);

        $previousSnapshot = $this->getLatestSnapshot($method, $locationId);
        $variance = $previousSnapshot !== null
            ? $valuation['total_value'] - $previousSnapshot->total_value_minor
            : null;

        $breakdown = $this->getBreakdownByCategory($method, $locationId);

        return InventoryValuationSnapshot::create([
            'location_id' => $locationId,
            'costing_method' => $method,
            'snapshot_date' => $snapshotDate,
            'total_quantity' => $valuation['total_quantity'],
            'total_value_minor' => $valuation['total_value'],
            'average_unit_cost_minor' => $valuation['total_quantity'] > 0
                ? (int) round($valuation['total_value'] / $valuation['total_quantity'])
                : 0,
            'currency' => config('inventory.defaults.currency', 'MYR'),
            'sku_count' => $valuation['sku_count'],
            'variance_from_previous_minor' => $variance,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Get latest snapshot.
     */
    public function getLatestSnapshot(CostingMethod $method, ?string $locationId = null): ?InventoryValuationSnapshot
    {
        $query = InventoryValuationSnapshot::query()
            ->usingMethod($method)
            ->latest();

        if ($locationId !== null) {
            $query->forLocation($locationId);
        } else {
            $query->allLocations();
        }

        return $query->first();
    }

    /**
     * Get snapshots for a date range.
     *
     * @return Collection<int, InventoryValuationSnapshot>
     */
    public function getSnapshotsForRange(
        CostingMethod $method,
        Carbon $from,
        Carbon $to,
        ?string $locationId = null
    ): Collection {
        $query = InventoryValuationSnapshot::query()
            ->usingMethod($method)
            ->betweenDates($from, $to)
            ->orderBy('snapshot_date');

        if ($locationId !== null) {
            $query->forLocation($locationId);
        } else {
            $query->allLocations();
        }

        return $query->get();
    }

    /**
     * Compare valuations between two methods.
     *
     * @return array{method1: array{value: int, average: int}, method2: array{value: int, average: int}, difference: int}
     */
    public function compareValuations(
        Model $model,
        CostingMethod $method1,
        CostingMethod $method2,
        ?string $locationId = null
    ): array {
        $valuation1 = $this->calculateValuation($model, $method1, $locationId);
        $valuation2 = $this->calculateValuation($model, $method2, $locationId);

        return [
            'method1' => [
                'value' => $valuation1['value'],
                'average' => $valuation1['average_cost'],
            ],
            'method2' => [
                'value' => $valuation2['value'],
                'average' => $valuation2['average_cost'],
            ],
            'difference' => $valuation1['value'] - $valuation2['value'],
        ];
    }

    /**
     * Generate valuation report for all locations.
     *
     * @return array<string, array{location_name: string, quantity: int, value: int, average_cost: int}>
     */
    public function generateLocationReport(CostingMethod $method): array
    {
        $locations = InventoryLocation::query()
            ->where('is_active', true)
            ->get();

        $report = [];

        foreach ($locations as $location) {
            $valuation = $this->getLocationValuation($location->id, $method);

            $report[$location->id] = [
                'location_name' => $location->name,
                'quantity' => $valuation['total_quantity'],
                'value' => $valuation['total_value'],
                'average_cost' => $valuation['total_quantity'] > 0
                    ? (int) round($valuation['total_value'] / $valuation['total_quantity'])
                    : 0,
            ];
        }

        return $report;
    }

    /**
     * Create daily snapshots for all locations.
     *
     * @return Collection<int, InventoryValuationSnapshot>
     */
    public function createDailySnapshots(CostingMethod $method): Collection
    {
        return DB::transaction(function () use ($method): Collection {
            $snapshots = collect();

            $snapshots->push($this->createSnapshot($method));

            $locations = InventoryLocation::query()
                ->where('is_active', true)
                ->get();

            foreach ($locations as $location) {
                $snapshots->push($this->createSnapshot($method, $location->id));
            }

            return $snapshots;
        });
    }

    /**
     * Calculate valuation using standard cost.
     *
     * @return array{quantity: int, value: int, average_cost: int}
     */
    private function calculateStandardValuation(Model $model, ?string $locationId = null): array
    {
        $query = InventoryLevel::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        $quantity = (int) $query->sum('quantity_on_hand');

        $standardCost = $this->standardCostService->getCurrentCostValue($model) ?? 0;

        return [
            'quantity' => $quantity,
            'value' => $quantity * $standardCost,
            'average_cost' => $standardCost,
        ];
    }

    /**
     * Get breakdown by category/type.
     *
     * @return array<string, array{units: int, value: int}>
     */
    private function getBreakdownByCategory(CostingMethod $method, ?string $locationId = null): array
    {
        $query = InventoryCostLayer::query()
            ->withRemainingQuantity()
            ->usingMethod($method)
            ->selectRaw('inventoryable_type, SUM(remaining_quantity) as units, SUM(remaining_quantity * unit_cost_minor) as value')
            ->groupBy('inventoryable_type');

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        $results = $query->get();

        $breakdown = [];
        foreach ($results as $result) {
            $breakdown[$result->inventoryable_type] = [
                'units' => (int) $result->units,
                'value' => (int) $result->value,
            ];
        }

        return $breakdown;
    }
}
