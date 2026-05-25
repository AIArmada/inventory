# Replenishment & Forecasting

> **Document:** 08 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

Implement **intelligent inventory replenishment** with demand forecasting, lead time tracking, safety stock calculations, and automated reorder suggestions for optimal stock levels.

---

## Replenishment Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                 REPLENISHMENT ENGINE                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   ┌───────────────┐    ┌───────────────┐                    │
│   │ Historical    │    │ Current Stock │                    │
│   │ Sales Data    │    │ Levels        │                    │
│   └───────┬───────┘    └───────┬───────┘                    │
│           │                     │                            │
│           ▼                     ▼                            │
│   ┌───────────────────────────────────────┐                 │
│   │        DEMAND FORECASTING             │                 │
│   │  • Moving Average                     │                 │
│   │  • Exponential Smoothing              │                 │
│   │  • Seasonal Decomposition             │                 │
│   └───────────────────┬───────────────────┘                 │
│                       │                                      │
│                       ▼                                      │
│   ┌───────────────────────────────────────┐                 │
│   │        SAFETY STOCK CALCULATION       │                 │
│   │  • Service Level Target               │                 │
│   │  • Demand Variability                 │                 │
│   │  • Lead Time Variability              │                 │
│   └───────────────────┬───────────────────┘                 │
│                       │                                      │
│                       ▼                                      │
│   ┌───────────────────────────────────────┐                 │
│   │        REORDER POINT CALCULATION      │                 │
│   │  ROP = (Daily Demand × Lead Time)     │                 │
│   │        + Safety Stock                 │                 │
│   └───────────────────┬───────────────────┘                 │
│                       │                                      │
│                       ▼                                      │
│   ┌───────────────────────────────────────┐                 │
│   │        REORDER SUGGESTIONS            │                 │
│   │  • EOQ Calculation                    │                 │
│   │  • Supplier Lead Times                │                 │
│   │  • Min/Max Constraints                │                 │
│   └───────────────────────────────────────┘                 │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Schema

### Demand History

```php
Schema::create('inventory_demand_history', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->foreignUuid('location_id')->nullable();
    
    $table->date('period_date');
    $table->string('period_type')->default('daily'); // daily, weekly, monthly
    
    // Quantities
    $table->integer('quantity_demanded'); // Total demand (including unfulfilled)
    $table->integer('quantity_fulfilled');
    $table->integer('quantity_unfulfilled')->default(0); // Stockouts
    
    // Financial
    $table->integer('revenue_minor')->default(0);
    
    $table->timestamps();
    
    $table->unique(['inventoryable_type', 'inventoryable_id', 'location_id', 'period_date', 'period_type'], 'demand_unique');
    $table->index(['period_date', 'period_type']);
});
```

### Supplier Lead Times

```php
Schema::create('inventory_supplier_leadtimes', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->uuid('supplier_id'); // Link to supplier/vendor
    
    // Lead time in days
    $table->integer('lead_time_days');
    $table->integer('lead_time_variance_days')->default(0); // Standard deviation
    
    // Minimum order
    $table->integer('min_order_quantity')->default(1);
    $table->integer('order_multiple')->default(1); // Must order in multiples of
    
    // Cost
    $table->integer('unit_cost_minor');
    $table->integer('shipping_cost_minor')->default(0);
    
    // Performance
    $table->decimal('on_time_delivery_rate', 5, 2)->default(100);
    $table->integer('total_orders')->default(0);
    
    $table->boolean('is_primary')->default(false);
    $table->boolean('is_active')->default(true);
    
    $table->timestamps();
    
    $table->index(['inventoryable_type', 'inventoryable_id', 'is_primary']);
});
```

### Reorder Suggestions

```php
Schema::create('inventory_reorder_suggestions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->foreignUuid('location_id');
    $table->foreignUuid('supplier_id')->nullable();
    
    // Current state
    $table->integer('current_on_hand');
    $table->integer('current_available');
    $table->integer('reorder_point');
    $table->integer('safety_stock');
    
    // Suggestion
    $table->integer('suggested_quantity');
    $table->integer('economic_order_quantity')->nullable();
    $table->date('order_by_date'); // When to place order
    $table->date('expected_arrival_date');
    
    // Urgency
    $table->string('urgency'); // critical, high, medium, low
    $table->integer('days_until_stockout')->nullable();
    
    // Cost estimate
    $table->integer('estimated_cost_minor');
    
    // Status
    $table->string('status')->default('pending'); // pending, approved, ordered, cancelled
    $table->foreignUuid('purchase_order_id')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->uuid('approved_by')->nullable();
    
    $table->timestamps();
    
    $table->index(['status', 'urgency']);
});
```

---

## Demand Forecasting Service

```php
class DemandForecastingService
{
    /**
     * Get average daily demand
     */
    public function getAverageDailyDemand(
        InventoryableInterface $item,
        ?InventoryLocation $location = null,
        int $lookbackDays = 30
    ): float {
        $history = InventoryDemandHistory::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->where('period_type', 'daily')
            ->where('period_date', '>=', now()->subDays($lookbackDays))
            ->get();
        
        if ($history->isEmpty()) {
            return 0;
        }
        
        return $history->avg('quantity_demanded');
    }

    /**
     * Get demand using moving average
     */
    public function movingAverageForecast(
        InventoryableInterface $item,
        int $periods = 7,
        ?InventoryLocation $location = null
    ): ForecastResult {
        $history = $this->getDemandHistory($item, $location, $periods);
        
        if ($history->count() < $periods) {
            return new ForecastResult(0, 0, 'insufficient_data');
        }
        
        $forecast = $history->take($periods)->avg('quantity_demanded');
        $variance = $this->calculateVariance($history, $forecast);
        
        return new ForecastResult(
            forecast: round($forecast, 2),
            standardDeviation: sqrt($variance),
            method: 'moving_average'
        );
    }

    /**
     * Get demand using exponential smoothing
     */
    public function exponentialSmoothingForecast(
        InventoryableInterface $item,
        float $alpha = 0.3, // Smoothing factor
        ?InventoryLocation $location = null
    ): ForecastResult {
        $history = $this->getDemandHistory($item, $location, 30);
        
        if ($history->isEmpty()) {
            return new ForecastResult(0, 0, 'insufficient_data');
        }
        
        // Initialize with first observation
        $forecast = $history->last()->quantity_demanded;
        
        // Apply exponential smoothing
        foreach ($history->reverse() as $record) {
            $forecast = $alpha * $record->quantity_demanded + (1 - $alpha) * $forecast;
        }
        
        return new ForecastResult(
            forecast: round($forecast, 2),
            standardDeviation: $this->calculateStandardDeviation($history),
            method: 'exponential_smoothing'
        );
    }

    /**
     * Get demand with seasonal decomposition
     */
    public function seasonalForecast(
        InventoryableInterface $item,
        ?InventoryLocation $location = null
    ): SeasonalForecastResult {
        $yearlyData = $this->getDemandHistory($item, $location, 365);
        
        if ($yearlyData->count() < 90) {
            return new SeasonalForecastResult(
                baselineForecast: $this->getAverageDailyDemand($item, $location),
                seasonalIndices: [],
                method: 'insufficient_seasonal_data'
            );
        }
        
        // Calculate monthly seasonal indices
        $monthlyAverages = $yearlyData
            ->groupBy(fn ($r) => $r->period_date->format('m'))
            ->map(fn ($group) => $group->avg('quantity_demanded'));
        
        $overallAverage = $monthlyAverages->avg();
        
        $seasonalIndices = $monthlyAverages
            ->map(fn ($avg) => $overallAverage > 0 ? $avg / $overallAverage : 1);
        
        return new SeasonalForecastResult(
            baselineForecast: $overallAverage,
            seasonalIndices: $seasonalIndices->toArray(),
            method: 'seasonal_decomposition'
        );
    }

    /**
     * Record demand event
     */
    public function recordDemand(
        InventoryableInterface $item,
        int $quantity,
        bool $fulfilled,
        ?InventoryLocation $location = null,
        int $revenueMiner = 0
    ): void {
        $record = InventoryDemandHistory::firstOrNew([
            'inventoryable_type' => $item::class,
            'inventoryable_id' => $item->getKey(),
            'location_id' => $location?->id,
            'period_date' => now()->toDateString(),
            'period_type' => 'daily',
        ]);
        
        $record->quantity_demanded += $quantity;
        
        if ($fulfilled) {
            $record->quantity_fulfilled += $quantity;
            $record->revenue_minor += $revenueMiner;
        } else {
            $record->quantity_unfulfilled += $quantity;
        }
        
        $record->save();
    }

    private function getDemandHistory(
        InventoryableInterface $item,
        ?InventoryLocation $location,
        int $days
    ): Collection {
        return InventoryDemandHistory::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->where('period_type', 'daily')
            ->where('period_date', '>=', now()->subDays($days))
            ->orderByDesc('period_date')
            ->get();
    }
}
```

---

## Safety Stock Service

```php
class SafetyStockService
{
    /**
     * Calculate safety stock for target service level
     */
    public function calculate(
        InventoryableInterface $item,
        float $serviceLevel = 0.95, // 95% service level
        ?InventoryLocation $location = null
    ): SafetyStockResult {
        $forecast = app(DemandForecastingService::class)
            ->movingAverageForecast($item, 30, $location);
        
        $leadTime = $this->getLeadTime($item);
        
        // Z-score for service level
        $zScore = $this->getZScore($serviceLevel);
        
        // Safety stock = Z × σ_demand × √(lead_time)
        $safetyStock = (int) ceil(
            $zScore * $forecast->standardDeviation * sqrt($leadTime->days)
        );
        
        return new SafetyStockResult(
            safetyStock: $safetyStock,
            serviceLevel: $serviceLevel,
            demandStdDev: $forecast->standardDeviation,
            leadTimeDays: $leadTime->days,
            zScore: $zScore
        );
    }

    /**
     * Calculate safety stock with lead time variability
     */
    public function calculateWithLeadTimeVariance(
        InventoryableInterface $item,
        float $serviceLevel = 0.95,
        ?InventoryLocation $location = null
    ): SafetyStockResult {
        $forecast = app(DemandForecastingService::class)
            ->movingAverageForecast($item, 30, $location);
        
        $leadTime = $this->getLeadTime($item);
        $avgDemand = $forecast->forecast;
        
        $zScore = $this->getZScore($serviceLevel);
        
        // Combined safety stock formula accounting for both variabilities
        // SS = Z × √(LT × σ_d² + D² × σ_lt²)
        $safetyStock = (int) ceil(
            $zScore * sqrt(
                $leadTime->days * pow($forecast->standardDeviation, 2) +
                pow($avgDemand, 2) * pow($leadTime->varianceDays, 2)
            )
        );
        
        return new SafetyStockResult(
            safetyStock: $safetyStock,
            serviceLevel: $serviceLevel,
            demandStdDev: $forecast->standardDeviation,
            leadTimeDays: $leadTime->days,
            leadTimeVariance: $leadTime->varianceDays,
            zScore: $zScore
        );
    }

    private function getZScore(float $serviceLevel): float
    {
        // Common z-scores for service levels
        return match (true) {
            $serviceLevel >= 0.99 => 2.33,
            $serviceLevel >= 0.98 => 2.05,
            $serviceLevel >= 0.95 => 1.65,
            $serviceLevel >= 0.90 => 1.28,
            $serviceLevel >= 0.85 => 1.04,
            default => 0.84,
        };
    }
}
```

---

## Reorder Point Service

```php
class ReorderPointService
{
    public function __construct(
        private DemandForecastingService $forecastService,
        private SafetyStockService $safetyStockService,
    ) {}

    /**
     * Calculate reorder point
     */
    public function calculate(
        InventoryableInterface $item,
        ?InventoryLocation $location = null,
        float $serviceLevel = 0.95
    ): ReorderPointResult {
        $avgDemand = $this->forecastService->getAverageDailyDemand($item, $location);
        $leadTime = $this->getLeadTime($item);
        $safetyStock = $this->safetyStockService->calculate($item, $serviceLevel, $location);
        
        // ROP = (Daily Demand × Lead Time) + Safety Stock
        $reorderPoint = (int) ceil(
            ($avgDemand * $leadTime->days) + $safetyStock->safetyStock
        );
        
        return new ReorderPointResult(
            reorderPoint: $reorderPoint,
            averageDailyDemand: $avgDemand,
            leadTimeDays: $leadTime->days,
            safetyStock: $safetyStock->safetyStock,
            serviceLevel: $serviceLevel
        );
    }

    /**
     * Calculate Economic Order Quantity
     */
    public function calculateEOQ(
        InventoryableInterface $item,
        int $annualDemand,
        int $orderCostMinor, // Fixed cost per order
        int $holdingCostMinor // Annual holding cost per unit
    ): int {
        if ($holdingCostMinor <= 0) {
            return $annualDemand;
        }
        
        // EOQ = √((2 × D × S) / H)
        $eoq = sqrt(
            (2 * $annualDemand * $orderCostMinor) / $holdingCostMinor
        );
        
        return (int) ceil($eoq);
    }
}
```

---

## Replenishment Service

```php
class ReplenishmentService
{
    public function __construct(
        private DemandForecastingService $forecastService,
        private ReorderPointService $ropService,
    ) {}

    /**
     * Generate reorder suggestions for location
     */
    public function generateSuggestions(
        ?InventoryLocation $location = null
    ): Collection {
        $levels = InventoryLevel::query()
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->with(['inventoryable', 'location'])
            ->get();
        
        $suggestions = collect();
        
        foreach ($levels as $level) {
            $rop = $this->ropService->calculate(
                $level->inventoryable,
                $level->location
            );
            
            if ($level->available <= $rop->reorderPoint) {
                $suggestions->push(
                    $this->createSuggestion($level, $rop)
                );
            }
        }
        
        return $suggestions->sortByDesc(fn ($s) => $this->getUrgencyScore($s));
    }

    /**
     * Get items requiring urgent reorder
     */
    public function getUrgentItems(int $daysUntilStockout = 7): Collection
    {
        return InventoryLevel::query()
            ->where('quantity_on_hand', '>', 0)
            ->get()
            ->filter(function ($level) use ($daysUntilStockout) {
                $velocity = $this->forecastService->getAverageDailyDemand(
                    $level->inventoryable,
                    $level->location
                );
                
                if ($velocity <= 0) {
                    return false;
                }
                
                $daysRemaining = $level->available / $velocity;
                return $daysRemaining <= $daysUntilStockout;
            })
            ->sortBy(fn ($l) => $l->available / max(1, $this->forecastService->getAverageDailyDemand($l->inventoryable, $l->location)));
    }

    /**
     * Create reorder suggestion
     */
    private function createSuggestion(
        InventoryLevel $level,
        ReorderPointResult $rop
    ): InventoryReorderSuggestion {
        $supplier = $this->getPrimarySupplier($level->inventoryable);
        $leadTime = $supplier?->lead_time_days ?? 7;
        
        // Calculate suggested quantity
        $targetStock = $rop->reorderPoint + ($rop->averageDailyDemand * 14); // 2 weeks buffer
        $suggestedQty = max(
            $supplier?->min_order_quantity ?? 1,
            $targetStock - $level->available
        );
        
        // Round up to order multiple
        if ($supplier?->order_multiple) {
            $suggestedQty = (int) ceil($suggestedQty / $supplier->order_multiple) * $supplier->order_multiple;
        }
        
        // Calculate days until stockout
        $velocity = $this->forecastService->getAverageDailyDemand(
            $level->inventoryable,
            $level->location
        );
        $daysUntilStockout = $velocity > 0 ? (int) ($level->available / $velocity) : null;
        
        return InventoryReorderSuggestion::create([
            'inventoryable_type' => $level->inventoryable_type,
            'inventoryable_id' => $level->inventoryable_id,
            'location_id' => $level->location_id,
            'supplier_id' => $supplier?->supplier_id,
            'current_on_hand' => $level->quantity_on_hand,
            'current_available' => $level->available,
            'reorder_point' => $rop->reorderPoint,
            'safety_stock' => $rop->safetyStock,
            'suggested_quantity' => $suggestedQty,
            'order_by_date' => now(),
            'expected_arrival_date' => now()->addDays($leadTime),
            'urgency' => $this->calculateUrgency($daysUntilStockout, $leadTime),
            'days_until_stockout' => $daysUntilStockout,
            'estimated_cost_minor' => $suggestedQty * ($supplier?->unit_cost_minor ?? 0),
        ]);
    }

    private function calculateUrgency(?int $daysUntilStockout, int $leadTime): string
    {
        if ($daysUntilStockout === null) {
            return 'low';
        }
        
        if ($daysUntilStockout <= 0) {
            return 'critical';
        }
        
        if ($daysUntilStockout < $leadTime) {
            return 'high';
        }
        
        if ($daysUntilStockout < $leadTime * 1.5) {
            return 'medium';
        }
        
        return 'low';
    }
}
```

---

## Scheduled Jobs

### Daily Replenishment Check

```php
class CheckReplenishmentNeeds extends Command
{
    protected $signature = 'inventory:check-replenishment';
    
    public function handle(ReplenishmentService $replenishment): void
    {
        $suggestions = $replenishment->generateSuggestions();
        
        $critical = $suggestions->where('urgency', 'critical');
        $high = $suggestions->where('urgency', 'high');
        
        if ($critical->isNotEmpty() || $high->isNotEmpty()) {
            event(new ReplenishmentAlertsGenerated($critical, $high));
        }
        
        $this->info("Generated {$suggestions->count()} reorder suggestions");
        $this->info("Critical: {$critical->count()}, High: {$high->count()}");
    }
}
```

### Demand History Aggregation

```php
class AggregateDemandHistory extends Command
{
    protected $signature = 'inventory:aggregate-demand';
    
    public function handle(): void
    {
        // Aggregate daily to weekly
        $this->aggregateToWeekly();
        
        // Aggregate weekly to monthly
        $this->aggregateToMonthly();
        
        // Cleanup old daily records (keep 90 days)
        InventoryDemandHistory::query()
            ->where('period_type', 'daily')
            ->where('period_date', '<', now()->subDays(90))
            ->delete();
    }
}
```

---

## Usage Examples

### Getting Reorder Suggestions

```php
$replenishment = app(ReplenishmentService::class);

// Get all suggestions for a warehouse
$suggestions = $replenishment->generateSuggestions($warehouse);

// Filter critical items
$critical = $suggestions->where('urgency', 'critical');

foreach ($critical as $suggestion) {
    echo "{$suggestion->inventoryable->name}: Order {$suggestion->suggested_quantity} units by {$suggestion->order_by_date->format('Y-m-d')}";
}
```

### Calculating Optimal Stock Levels

```php
$ropService = app(ReorderPointService::class);

$result = $ropService->calculate(
    item: $product,
    location: $warehouse,
    serviceLevel: 0.98 // 98% service level
);

echo "Reorder Point: {$result->reorderPoint}";
echo "Safety Stock: {$result->safetyStock}";
echo "Daily Demand: {$result->averageDailyDemand}";
```

---

## Navigation

**Previous:** [07-cost-valuation.md](07-cost-valuation.md)  
**Next:** [09-database-evolution.md](09-database-evolution.md)
