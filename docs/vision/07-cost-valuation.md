# Cost & Valuation

> **Document:** 07 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

Implement comprehensive **cost tracking and inventory valuation** with multiple costing methods (FIFO, weighted average, standard), landed cost allocation, COGS calculation, and real-time inventory value reporting.

---

## Cost Tracking Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                   COST TRACKING FLOW                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   RECEIPT                    SHIPMENT                        │
│   ┌──────────┐              ┌──────────┐                    │
│   │ Unit Cost│              │   COGS   │                    │
│   │ Landed   │              │ Computed │                    │
│   │ Freight  │              │ Per Item │                    │
│   └────┬─────┘              └────┬─────┘                    │
│        │                         │                          │
│        ▼                         ▼                          │
│   ┌────────────────────────────────────────────┐           │
│   │           COSTING ENGINE                    │           │
│   │  ┌──────┐  ┌─────────┐  ┌────────┐        │           │
│   │  │ FIFO │  │ Avg Cost│  │Standard│        │           │
│   │  └──────┘  └─────────┘  └────────┘        │           │
│   └────────────────────────────────────────────┘           │
│                          │                                  │
│                          ▼                                  │
│   ┌────────────────────────────────────────────┐           │
│   │          INVENTORY VALUATION                │           │
│   │  • By Location   • By Category             │           │
│   │  • By Batch      • By Date Range           │           │
│   └────────────────────────────────────────────┘           │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Costing Method Enum

```php
enum CostingMethod: string
{
    case Fifo = 'fifo';           // First In, First Out
    case WeightedAverage = 'avg';  // Weighted Average Cost
    case Standard = 'standard';    // Standard/Fixed Cost
    case Lifo = 'lifo';           // Last In, First Out (rarely used)
    case Specific = 'specific';    // Specific identification (serialized)

    public function label(): string
    {
        return match ($this) {
            self::Fifo => 'First In, First Out (FIFO)',
            self::WeightedAverage => 'Weighted Average Cost',
            self::Standard => 'Standard Cost',
            self::Lifo => 'Last In, First Out (LIFO)',
            self::Specific => 'Specific Identification',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Fifo => 'Uses cost of oldest inventory first',
            self::WeightedAverage => 'Averages cost across all units',
            self::Standard => 'Uses predetermined standard cost',
            self::Lifo => 'Uses cost of newest inventory first',
            self::Specific => 'Tracks actual cost per serial number',
        };
    }
}
```

---

## Database Schema

### Inventory Cost Layers

```php
Schema::create('inventory_cost_layers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    // Item reference
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    $table->foreignUuid('location_id');
    
    // Batch/receipt reference
    $table->foreignUuid('batch_id')->nullable();
    $table->foreignUuid('movement_id')->nullable();
    
    // Quantities
    $table->integer('quantity_received');
    $table->integer('quantity_remaining');
    
    // Costs (in minor units)
    $table->integer('unit_cost_minor');
    $table->integer('landed_cost_minor')->default(0); // Freight, duties, etc.
    $table->integer('total_unit_cost_minor')->storedAs('unit_cost_minor + landed_cost_minor');
    
    // Layer metadata
    $table->timestamp('received_at');
    $table->string('reference')->nullable(); // PO number, supplier invoice
    $table->json('cost_breakdown')->nullable(); // Detailed cost components
    
    $table->timestamps();
    
    $table->index(['inventoryable_type', 'inventoryable_id', 'received_at']);
    $table->index(['location_id', 'quantity_remaining']);
});
```

### Standard Costs

```php
Schema::create('inventory_standard_costs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    // Item reference
    $table->string('inventoryable_type');
    $table->uuid('inventoryable_id');
    
    // Standard cost
    $table->integer('standard_cost_minor');
    
    // Effective period
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    
    // Variance tracking
    $table->integer('purchase_variance_minor')->default(0);
    $table->integer('usage_variance_minor')->default(0);
    
    $table->timestamps();
    
    $table->unique(['inventoryable_type', 'inventoryable_id', 'effective_from']);
});
```

### Inventory Valuation Snapshots

```php
Schema::create('inventory_valuation_snapshots', function (Blueprint $table) {
    $table->uuid('id')->primary();
    
    $table->date('snapshot_date');
    $table->foreignUuid('location_id')->nullable(); // Null for all locations
    
    // Aggregates
    $table->integer('total_quantity');
    $table->bigInteger('total_value_minor');
    $table->bigInteger('total_cost_minor');
    $table->bigInteger('total_landed_cost_minor');
    
    // By costing method
    $table->bigInteger('fifo_value_minor');
    $table->bigInteger('avg_value_minor');
    $table->bigInteger('standard_value_minor');
    
    // SKU count
    $table->integer('sku_count');
    $table->integer('sku_with_stock_count');
    
    $table->json('breakdown_by_category')->nullable();
    $table->json('breakdown_by_location')->nullable();
    
    $table->timestamps();
    
    $table->unique(['snapshot_date', 'location_id']);
});
```

---

## Cost Layer Model

```php
class InventoryCostLayer extends Model
{
    use HasUuids;

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'batch_id',
        'movement_id',
        'quantity_received',
        'quantity_remaining',
        'unit_cost_minor',
        'landed_cost_minor',
        'received_at',
        'reference',
        'cost_breakdown',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'cost_breakdown' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function inventoryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    /**
     * Get total unit cost including landed cost
     */
    public function getTotalUnitCostAttribute(): int
    {
        return $this->unit_cost_minor + $this->landed_cost_minor;
    }

    /**
     * Get total layer value
     */
    public function getLayerValueAttribute(): int
    {
        return $this->quantity_remaining * $this->total_unit_cost;
    }

    /**
     * Consume units from this layer
     */
    public function consume(int $quantity): int
    {
        $consumed = min($quantity, $this->quantity_remaining);
        $this->decrement('quantity_remaining', $consumed);
        
        return $consumed;
    }
}
```

---

## Costing Service

```php
class CostingService
{
    /**
     * Record cost layer on receipt
     */
    public function recordReceipt(
        InventoryableInterface $item,
        InventoryLocation $location,
        int $quantity,
        int $unitCostMinor,
        array $landedCosts = [],
        ?string $reference = null,
        ?InventoryBatch $batch = null
    ): InventoryCostLayer {
        $landedCostMinor = $this->calculateLandedCost($landedCosts, $quantity);
        
        return InventoryCostLayer::create([
            'inventoryable_type' => $item::class,
            'inventoryable_id' => $item->getKey(),
            'location_id' => $location->id,
            'batch_id' => $batch?->id,
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'unit_cost_minor' => $unitCostMinor,
            'landed_cost_minor' => $landedCostMinor,
            'received_at' => now(),
            'reference' => $reference,
            'cost_breakdown' => $landedCosts,
        ]);
    }

    /**
     * Calculate COGS on shipment
     */
    public function calculateCogs(
        InventoryableInterface $item,
        int $quantity,
        CostingMethod $method,
        ?InventoryLocation $location = null
    ): CostResult {
        return match ($method) {
            CostingMethod::Fifo => $this->calculateFifoCogs($item, $quantity, $location),
            CostingMethod::WeightedAverage => $this->calculateAvgCogs($item, $quantity, $location),
            CostingMethod::Standard => $this->calculateStandardCogs($item, $quantity),
            CostingMethod::Lifo => $this->calculateLifoCogs($item, $quantity, $location),
            CostingMethod::Specific => throw new InvalidArgumentException('Use serial-specific method'),
        };
    }

    /**
     * FIFO costing - consume oldest layers first
     */
    private function calculateFifoCogs(
        InventoryableInterface $item,
        int $quantity,
        ?InventoryLocation $location
    ): CostResult {
        $remaining = $quantity;
        $totalCost = 0;
        $layersUsed = [];
        
        $layers = InventoryCostLayer::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at', 'asc') // FIFO: oldest first
            ->get();
        
        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            
            $consumed = $layer->consume($remaining);
            $cost = $consumed * $layer->total_unit_cost;
            
            $totalCost += $cost;
            $remaining -= $consumed;
            
            $layersUsed[] = [
                'layer_id' => $layer->id,
                'quantity' => $consumed,
                'unit_cost' => $layer->total_unit_cost,
                'total_cost' => $cost,
            ];
        }
        
        return new CostResult(
            method: CostingMethod::Fifo,
            quantityCosted: $quantity - $remaining,
            totalCostMinor: $totalCost,
            averageCostMinor: $quantity > 0 ? (int) ($totalCost / $quantity) : 0,
            layersUsed: $layersUsed
        );
    }

    /**
     * Weighted average costing
     */
    private function calculateAvgCogs(
        InventoryableInterface $item,
        int $quantity,
        ?InventoryLocation $location
    ): CostResult {
        $layers = InventoryCostLayer::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->where('quantity_remaining', '>', 0)
            ->get();
        
        $totalUnits = $layers->sum('quantity_remaining');
        $totalValue = $layers->sum(fn ($l) => $l->quantity_remaining * $l->total_unit_cost);
        
        $avgCost = $totalUnits > 0 ? (int) ($totalValue / $totalUnits) : 0;
        
        // Proportionally consume from all layers
        $layersUsed = [];
        $remaining = $quantity;
        
        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            
            $consumed = $layer->consume($remaining);
            $remaining -= $consumed;
            
            $layersUsed[] = [
                'layer_id' => $layer->id,
                'quantity' => $consumed,
            ];
        }
        
        return new CostResult(
            method: CostingMethod::WeightedAverage,
            quantityCosted: $quantity,
            totalCostMinor: $quantity * $avgCost,
            averageCostMinor: $avgCost,
            layersUsed: $layersUsed
        );
    }

    /**
     * Standard costing
     */
    private function calculateStandardCogs(
        InventoryableInterface $item,
        int $quantity
    ): CostResult {
        $standardCost = InventoryStandardCost::query()
            ->where('inventoryable_type', $item::class)
            ->where('inventoryable_id', $item->getKey())
            ->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
            ->orderByDesc('effective_from')
            ->first();
        
        $cost = $standardCost?->standard_cost_minor ?? 0;
        
        return new CostResult(
            method: CostingMethod::Standard,
            quantityCosted: $quantity,
            totalCostMinor: $quantity * $cost,
            averageCostMinor: $cost,
            layersUsed: []
        );
    }

    private function calculateLandedCost(array $costs, int $quantity): int
    {
        $total = 0;
        
        foreach ($costs as $cost) {
            if ($cost['type'] === 'per_unit') {
                $total += $cost['amount_minor'] * $quantity;
            } else {
                $total += (int) ($cost['amount_minor'] / $quantity);
            }
        }
        
        return $total;
    }
}
```

---

## Valuation Service

```php
class InventoryValuationService
{
    public function __construct(
        private CostingService $costingService
    ) {}

    /**
     * Get current inventory value
     */
    public function getCurrentValue(
        ?InventoryLocation $location = null,
        CostingMethod $method = CostingMethod::WeightedAverage
    ): ValuationResult {
        $query = InventoryCostLayer::query()
            ->where('quantity_remaining', '>', 0)
            ->when($location, fn ($q) => $q->where('location_id', $location->id));
        
        $layers = $query->get();
        
        $totalQuantity = $layers->sum('quantity_remaining');
        $totalValue = match ($method) {
            CostingMethod::Fifo, CostingMethod::WeightedAverage => 
                $layers->sum(fn ($l) => $l->quantity_remaining * $l->total_unit_cost),
            CostingMethod::Standard => 
                $this->calculateStandardValue($layers),
            default => 0,
        };
        
        return new ValuationResult(
            method: $method,
            totalQuantity: $totalQuantity,
            totalValueMinor: $totalValue,
            averageCostMinor: $totalQuantity > 0 ? (int) ($totalValue / $totalQuantity) : 0,
            asOfDate: now(),
            location: $location
        );
    }

    /**
     * Get valuation by category
     */
    public function getValueByCategory(CostingMethod $method): Collection
    {
        return InventoryCostLayer::query()
            ->where('quantity_remaining', '>', 0)
            ->with('inventoryable')
            ->get()
            ->groupBy(fn ($l) => $l->inventoryable->category_id ?? 'uncategorized')
            ->map(function ($layers, $categoryId) use ($method) {
                $totalQty = $layers->sum('quantity_remaining');
                $totalValue = $layers->sum(fn ($l) => $l->quantity_remaining * $l->total_unit_cost);
                
                return [
                    'category_id' => $categoryId,
                    'quantity' => $totalQty,
                    'value_minor' => $totalValue,
                    'average_cost_minor' => $totalQty > 0 ? (int) ($totalValue / $totalQty) : 0,
                ];
            });
    }

    /**
     * Create valuation snapshot
     */
    public function createSnapshot(?InventoryLocation $location = null): InventoryValuationSnapshot
    {
        $fifo = $this->getCurrentValue($location, CostingMethod::Fifo);
        $avg = $this->getCurrentValue($location, CostingMethod::WeightedAverage);
        $standard = $this->getCurrentValue($location, CostingMethod::Standard);
        
        return InventoryValuationSnapshot::create([
            'snapshot_date' => now()->toDateString(),
            'location_id' => $location?->id,
            'total_quantity' => $fifo->totalQuantity,
            'total_value_minor' => $fifo->totalValueMinor,
            'fifo_value_minor' => $fifo->totalValueMinor,
            'avg_value_minor' => $avg->totalValueMinor,
            'standard_value_minor' => $standard->totalValueMinor,
            'sku_count' => $this->getSkuCount(),
            'sku_with_stock_count' => $this->getSkuWithStockCount(),
            'breakdown_by_category' => $this->getValueByCategory(CostingMethod::WeightedAverage),
            'breakdown_by_location' => $this->getValueByLocation(),
        ]);
    }

    /**
     * Get valuation trend over time
     */
    public function getValuationTrend(
        Carbon $from,
        Carbon $to,
        ?InventoryLocation $location = null
    ): Collection {
        return InventoryValuationSnapshot::query()
            ->whereBetween('snapshot_date', [$from, $to])
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->orderBy('snapshot_date')
            ->get();
    }
}
```

---

## Cost Result DTO

```php
class CostResult
{
    public function __construct(
        public readonly CostingMethod $method,
        public readonly int $quantityCosted,
        public readonly int $totalCostMinor,
        public readonly int $averageCostMinor,
        public readonly array $layersUsed = [],
    ) {}

    public function totalCost(): Money
    {
        return Money::MYR($this->totalCostMinor);
    }

    public function averageCost(): Money
    {
        return Money::MYR($this->averageCostMinor);
    }
}
```

---

## Landed Cost Allocation

```php
class LandedCostService
{
    /**
     * Allocate landed costs across receipt items
     */
    public function allocateLandedCosts(
        Collection $receiptItems,
        array $landedCosts,
        string $allocationMethod = 'value' // value, quantity, weight, volume
    ): Collection {
        $totalBasis = match ($allocationMethod) {
            'value' => $receiptItems->sum(fn ($i) => $i['quantity'] * $i['unit_cost_minor']),
            'quantity' => $receiptItems->sum('quantity'),
            'weight' => $receiptItems->sum(fn ($i) => $i['quantity'] * ($i['weight_kg'] ?? 1)),
            'volume' => $receiptItems->sum(fn ($i) => $i['quantity'] * ($i['volume_m3'] ?? 0.001)),
            default => throw new InvalidArgumentException('Invalid allocation method'),
        };
        
        $totalLandedCost = collect($landedCosts)->sum('amount_minor');
        
        return $receiptItems->map(function ($item) use ($allocationMethod, $totalBasis, $totalLandedCost) {
            $itemBasis = match ($allocationMethod) {
                'value' => $item['quantity'] * $item['unit_cost_minor'],
                'quantity' => $item['quantity'],
                'weight' => $item['quantity'] * ($item['weight_kg'] ?? 1),
                'volume' => $item['quantity'] * ($item['volume_m3'] ?? 0.001),
            };
            
            $allocatedCost = (int) (($itemBasis / $totalBasis) * $totalLandedCost);
            $costPerUnit = (int) ($allocatedCost / $item['quantity']);
            
            return array_merge($item, [
                'allocated_landed_cost_minor' => $allocatedCost,
                'landed_cost_per_unit_minor' => $costPerUnit,
            ]);
        });
    }
}
```

---

## Usage Examples

### Recording Receipt with Costs

```php
$costingService = app(CostingService::class);

// Record receipt with landed costs
$layer = $costingService->recordReceipt(
    item: $product,
    location: $warehouse,
    quantity: 100,
    unitCostMinor: 5000, // RM50 each
    landedCosts: [
        ['type' => 'fixed', 'name' => 'Shipping', 'amount_minor' => 10000], // RM100 shipping
        ['type' => 'fixed', 'name' => 'Customs', 'amount_minor' => 5000],  // RM50 customs
        ['type' => 'per_unit', 'name' => 'Handling', 'amount_minor' => 50], // RM0.50/unit
    ],
    reference: 'PO-2024-001'
);

echo $layer->total_unit_cost; // 5150 (50.00 + 1.00 shipping + 0.50 customs + 0.50 handling)
```

### Calculating COGS on Shipment

```php
$result = $costingService->calculateCogs(
    item: $product,
    quantity: 10,
    method: CostingMethod::Fifo,
    location: $warehouse
);

echo $result->totalCost();      // RM515.00
echo $result->averageCost();    // RM51.50 per unit
```

### Getting Inventory Valuation

```php
$valuationService = app(InventoryValuationService::class);

$valuation = $valuationService->getCurrentValue(
    location: $warehouse,
    method: CostingMethod::WeightedAverage
);

echo "Total Value: RM" . number_format($valuation->totalValueMinor / 100, 2);
echo "Average Cost: RM" . number_format($valuation->averageCostMinor / 100, 2);
```

---

## Navigation

**Previous:** [06-serial-numbers.md](06-serial-numbers.md)  
**Next:** [08-replenishment-forecasting.md](08-replenishment-forecasting.md)
