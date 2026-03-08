<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\DemandPeriodType;
use AIArmada\Inventory\Models\InventoryDemandHistory;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DemandForecastService
{
    /**
     * Record demand for a model.
     */
    public function recordDemand(
        Model $model,
        int $quantity,
        int $fulfilledQuantity,
        ?string $locationId = null,
        DemandPeriodType $periodType = DemandPeriodType::Daily,
        ?Carbon $periodDate = null
    ): InventoryDemandHistory {
        $periodDate = $periodDate ?? today();
        $lostQuantity = max(0, $quantity - $fulfilledQuantity);

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
        }

        return DB::transaction(function () use (
            $model,
            $quantity,
            $fulfilledQuantity,
            $lostQuantity,
            $locationId,
            $periodType,
            $periodDate
        ): InventoryDemandHistory {
            $existing = InventoryDemandHistory::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->where('location_id', $locationId)
                ->where('period_date', $periodDate)
                ->where('period_type', $periodType->value)
                ->first();

            if ($existing) {
                $existing->update([
                    'quantity_demanded' => $existing->quantity_demanded + $quantity,
                    'quantity_fulfilled' => $existing->quantity_fulfilled + $fulfilledQuantity,
                    'quantity_lost' => $existing->quantity_lost + $lostQuantity,
                    'order_count' => $existing->order_count + 1,
                ]);

                return $existing;
            }

            return InventoryDemandHistory::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'location_id' => $locationId,
                'period_date' => $periodDate,
                'period_type' => $periodType,
                'quantity_demanded' => $quantity,
                'quantity_fulfilled' => $fulfilledQuantity,
                'quantity_lost' => $lostQuantity,
                'order_count' => 1,
            ]);
        });
    }

    /**
     * Calculate average daily demand.
     */
    public function calculateAverageDailyDemand(
        Model $model,
        int $days = 30,
        ?string $locationId = null
    ): float {
        $query = InventoryDemandHistory::query()
            ->forModel($model)
            ->daily()
            ->lastDays($days);

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

        $totalDemand = $query->sum('quantity_demanded');
        $periodCount = $query->count();

        if ($periodCount === 0) {
            return 0.0;
        }

        return $totalDemand / $days;
    }

    /**
     * Calculate weighted moving average.
     *
     * @param  array<int, float>|null  $weights
     */
    public function calculateWeightedMovingAverage(
        Model $model,
        int $periods = 4,
        ?array $weights = null,
        ?string $locationId = null
    ): float {
        if ($weights === null) {
            $weights = $this->generateDefaultWeights($periods);
        }

        $query = InventoryDemandHistory::query()
            ->forModel($model)
            ->daily()
            ->orderBy('period_date', 'desc')
            ->limit($periods);

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

        $history = $query->get()->reverse()->values();

        if ($history->isEmpty()) {
            return 0.0;
        }

        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($history as $index => $record) {
            $weight = $weights[$index] ?? 1.0;
            $weightedSum += $record->quantity_demanded * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;
    }

    /**
     * Calculate exponential smoothing forecast.
     */
    public function calculateExponentialSmoothing(
        Model $model,
        float $alpha = 0.3,
        int $periods = 30,
        ?string $locationId = null
    ): float {
        $query = InventoryDemandHistory::query()
            ->forModel($model)
            ->daily()
            ->orderBy('period_date', 'asc')
            ->limit($periods);

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

        $history = $query->get();

        if ($history->isEmpty()) {
            return 0.0;
        }

        $forecast = (float) $history->first()->quantity_demanded;

        foreach ($history->skip(1) as $record) {
            $forecast = $alpha * $record->quantity_demanded + (1 - $alpha) * $forecast;
        }

        return $forecast;
    }

    /**
     * Calculate demand variability (standard deviation).
     */
    public function calculateDemandVariability(
        Model $model,
        int $days = 30,
        ?string $locationId = null
    ): float {
        $query = InventoryDemandHistory::query()
            ->forModel($model)
            ->daily()
            ->lastDays($days);

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

        $demands = $query->pluck('quantity_demanded')->toArray();

        if (count($demands) < 2) {
            return 0.0;
        }

        $mean = array_sum($demands) / count($demands);
        $squaredDiffs = array_map(fn ($d) => ($d - $mean) ** 2, $demands);

        return sqrt(array_sum($squaredDiffs) / (count($demands) - 1));
    }

    /**
     * Forecast demand for next N days.
     *
     * @return array{forecast: float, confidence_low: float, confidence_high: float}
     */
    public function forecastDemand(
        Model $model,
        int $daysAhead = 7,
        ?string $locationId = null
    ): array {
        $avgDemand = $this->calculateExponentialSmoothing($model, 0.3, 30, $locationId);
        $stdDev = $this->calculateDemandVariability($model, 30, $locationId);

        $forecast = $avgDemand * $daysAhead;
        $confidenceMargin = 1.96 * $stdDev * sqrt($daysAhead);

        return [
            'forecast' => $forecast,
            'confidence_low' => max(0, $forecast - $confidenceMargin),
            'confidence_high' => $forecast + $confidenceMargin,
        ];
    }

    /**
     * Get demand trend (positive = increasing, negative = decreasing).
     */
    public function calculateTrend(
        Model $model,
        int $days = 30,
        ?string $locationId = null
    ): float {
        $query = InventoryDemandHistory::query()
            ->forModel($model)
            ->daily()
            ->lastDays($days)
            ->orderBy('period_date');

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

        $history = $query->get();

        if ($history->count() < 2) {
            return 0.0;
        }

        $n = $history->count();
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($history as $i => $record) {
            $x = $i + 1;
            $y = $record->quantity_demanded;

            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominator = $n * $sumX2 - $sumX * $sumX;

        if ($denominator === 0) {
            return 0.0;
        }

        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }

    /**
     * Get demand history summary.
     *
     * @return array{total_demand: int, total_fulfilled: int, total_lost: int, fulfillment_rate: float, periods: int}
     */
    public function getDemandSummary(
        Model $model,
        int $days = 30,
        ?string $locationId = null
    ): array {
        $query = InventoryDemandHistory::query()
            ->forModel($model)
            ->daily()
            ->lastDays($days);

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

        $history = $query->get();

        $totalDemand = $history->sum('quantity_demanded');
        $totalFulfilled = $history->sum('quantity_fulfilled');
        $totalLost = $history->sum('quantity_lost');

        return [
            'total_demand' => $totalDemand,
            'total_fulfilled' => $totalFulfilled,
            'total_lost' => $totalLost,
            'fulfillment_rate' => $totalDemand > 0 ? ($totalFulfilled / $totalDemand) * 100 : 100.0,
            'periods' => $history->count(),
        ];
    }

    /**
     * @return array<int, float>
     */
    private function generateDefaultWeights(int $periods): array
    {
        $weights = [];
        $total = ($periods * ($periods + 1)) / 2;

        for ($i = 1; $i <= $periods; $i++) {
            $weights[] = $i / $total;
        }

        return $weights;
    }
}
