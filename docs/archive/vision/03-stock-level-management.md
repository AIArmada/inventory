# Stock Level Management

> **Document:** 03 of 11  
> **Package:** `aiarmada/inventory`  
> **Status:** Vision

---

## Overview

Enhance stock level management with **multi-tier thresholds**, **safety stock calculations**, **decimal quantity support**, and **intelligent alerting** for proactive inventory control.

---

## Enhanced Stock Quantities

### Current State vs Vision

```
CURRENT:                           VISION:
├── quantity_on_hand               ├── quantity_on_hand
├── quantity_reserved              ├── quantity_reserved
└── available (computed)           ├── quantity_committed
                                   ├── quantity_in_transit
                                   ├── quantity_on_order
                                   ├── quantity_backordered
                                   ├── available (computed)
                                   └── projected_available (computed)
```

### Quantity Definitions

| Field | Description | Calculation |
|-------|-------------|-------------|
| `quantity_on_hand` | Physical stock in location | Direct count |
| `quantity_reserved` | Held for pending orders | Sum of allocations |
| `quantity_committed` | Allocated and payment received | Confirmed allocations |
| `quantity_in_transit` | Being transferred between locations | Transfer movements |
| `quantity_on_order` | Expected from purchase orders | Open PO lines |
| `quantity_backordered` | Unfulfilled demand | Failed allocations |
| `available` | Ready to allocate | on_hand - reserved - committed |
| `projected_available` | Future availability | available + on_order - backordered |

---

## Enhanced InventoryLevel Model

### Schema Updates

```php
Schema::table('inventory_levels', function (Blueprint $table) {
    // Additional quantities
    $table->integer('quantity_committed')->default(0);
    $table->integer('quantity_in_transit')->default(0);
    $table->integer('quantity_on_order')->default(0);
    $table->integer('quantity_backordered')->default(0);
    
    // Thresholds
    $table->integer('safety_stock')->default(0);
    $table->integer('min_quantity')->default(0);
    $table->integer('max_quantity')->nullable();
    
    // Replenishment
    $table->integer('reorder_quantity')->nullable();
    $table->integer('lead_time_days')->default(0);
    
    // Cost tracking
    $table->integer('unit_cost_minor')->default(0);
    $table->integer('total_value_minor')->default(0);
    
    // Decimal support
    $table->boolean('use_decimal_quantities')->default(false);
    $table->decimal('decimal_on_hand', 15, 4)->nullable();
    $table->decimal('decimal_reserved', 15, 4)->nullable();
    
    // Last activity
    $table->timestamp('last_received_at')->nullable();
    $table->timestamp('last_shipped_at')->nullable();
    $table->timestamp('last_counted_at')->nullable();
});
```

### Model Implementation

```php
class InventoryLevel extends Model
{
    use HasUuids;

    protected $fillable = [
        'inventoryable_type',
        'inventoryable_id',
        'location_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_committed',
        'quantity_in_transit',
        'quantity_on_order',
        'quantity_backordered',
        'reorder_point',
        'safety_stock',
        'min_quantity',
        'max_quantity',
        'reorder_quantity',
        'lead_time_days',
        'unit_cost_minor',
        'use_decimal_quantities',
        'allocation_strategy',
        'metadata',
    ];

    /**
     * Get available quantity for allocation
     */
    public function getAvailableAttribute(): int
    {
        return max(0, $this->quantity_on_hand - $this->quantity_reserved - $this->quantity_committed);
    }

    /**
     * Get projected available including incoming
     */
    public function getProjectedAvailableAttribute(): int
    {
        return $this->available + $this->quantity_on_order - $this->quantity_backordered;
    }

    /**
     * Calculate effective reorder point including safety stock
     */
    public function getEffectiveReorderPointAttribute(): int
    {
        return $this->reorder_point + $this->safety_stock;
    }

    /**
     * Check if below reorder point
     */
    public function needsReorder(): bool
    {
        return $this->available <= $this->effective_reorder_point;
    }

    /**
     * Check if critically low (below safety stock)
     */
    public function isCriticallyLow(): bool
    {
        return $this->available <= $this->safety_stock;
    }

    /**
     * Check if overstocked
     */
    public function isOverstocked(): bool
    {
        return $this->max_quantity && $this->quantity_on_hand > $this->max_quantity;
    }

    /**
     * Get suggested reorder quantity
     */
    public function getSuggestedReorderQuantity(): int
    {
        if ($this->reorder_quantity) {
            return $this->reorder_quantity;
        }
        
        // Calculate based on max or velocity
        $target = $this->max_quantity ?? ($this->effective_reorder_point * 3);
        
        return max(0, $target - $this->projected_available);
    }

    /**
     * Get days until stockout at current velocity
     */
    public function getDaysUntilStockout(): ?int
    {
        $velocity = $this->getAverageDailyVelocity();
        
        if ($velocity <= 0) {
            return null;
        }
        
        return (int) floor($this->available / $velocity);
    }

    /**
     * Get inventory value at this location
     */
    public function getValueAttribute(): int
    {
        return $this->quantity_on_hand * $this->unit_cost_minor;
    }
}
```

---

## Stock Status Classification

### StockStatus Enum

```php
enum StockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case CriticallyLow = 'critically_low';
    case OutOfStock = 'out_of_stock';
    case Overstocked = 'overstocked';
    case OnBackorder = 'on_backorder';
    
    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::CriticallyLow => 'danger',
            self::OutOfStock => 'danger',
            self::Overstocked => 'info',
            self::OnBackorder => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::InStock => 'heroicon-o-check-circle',
            self::LowStock => 'heroicon-o-exclamation-triangle',
            self::CriticallyLow => 'heroicon-o-exclamation-circle',
            self::OutOfStock => 'heroicon-o-x-circle',
            self::Overstocked => 'heroicon-o-arrow-trending-up',
            self::OnBackorder => 'heroicon-o-clock',
        };
    }
}
```

### StockStatusService

```php
class StockStatusService
{
    public function getStatus(InventoryLevel $level): StockStatus
    {
        if ($level->quantity_backordered > 0) {
            return StockStatus::OnBackorder;
        }
        
        if ($level->available <= 0) {
            return StockStatus::OutOfStock;
        }
        
        if ($level->isCriticallyLow()) {
            return StockStatus::CriticallyLow;
        }
        
        if ($level->isOverstocked()) {
            return StockStatus::Overstocked;
        }
        
        if ($level->needsReorder()) {
            return StockStatus::LowStock;
        }
        
        return StockStatus::InStock;
    }

    /**
     * @return array<StockStatus, int>
     */
    public function getStatusCounts(?InventoryLocation $location = null): array
    {
        $query = InventoryLevel::query();
        
        if ($location) {
            $query->where('location_id', $location->id);
        }
        
        $levels = $query->get();
        $counts = array_fill_keys(array_map(fn ($s) => $s->value, StockStatus::cases()), 0);
        
        foreach ($levels as $level) {
            $status = $this->getStatus($level);
            $counts[$status->value]++;
        }
        
        return $counts;
    }
}
```

---

## Alert System

### StockAlertConfig

```php
class StockAlertConfig
{
    public function __construct(
        public bool $enableLowStockAlerts = true,
        public bool $enableCriticalAlerts = true,
        public bool $enableOutOfStockAlerts = true,
        public bool $enableOverstockAlerts = false,
        public int $alertCooldownHours = 24,
        public array $notificationChannels = ['database', 'mail'],
    ) {}
}
```

### StockAlertService

```php
class StockAlertService
{
    public function __construct(
        private StockStatusService $statusService,
        private StockAlertConfig $config,
    ) {}

    public function checkAndAlert(InventoryLevel $level): void
    {
        $status = $this->statusService->getStatus($level);
        
        if (! $this->shouldAlert($level, $status)) {
            return;
        }
        
        match ($status) {
            StockStatus::OutOfStock => $this->triggerOutOfStockAlert($level),
            StockStatus::CriticallyLow => $this->triggerCriticalAlert($level),
            StockStatus::LowStock => $this->triggerLowStockAlert($level),
            StockStatus::Overstocked => $this->triggerOverstockAlert($level),
            default => null,
        };
        
        $this->recordAlertSent($level, $status);
    }

    private function shouldAlert(InventoryLevel $level, StockStatus $status): bool
    {
        $enabled = match ($status) {
            StockStatus::OutOfStock => $this->config->enableOutOfStockAlerts,
            StockStatus::CriticallyLow => $this->config->enableCriticalAlerts,
            StockStatus::LowStock => $this->config->enableLowStockAlerts,
            StockStatus::Overstocked => $this->config->enableOverstockAlerts,
            default => false,
        };
        
        if (! $enabled) {
            return false;
        }
        
        // Check cooldown
        $lastAlert = $this->getLastAlert($level, $status);
        
        if ($lastAlert && $lastAlert->created_at->addHours($this->config->alertCooldownHours)->isFuture()) {
            return false;
        }
        
        return true;
    }

    private function triggerLowStockAlert(InventoryLevel $level): void
    {
        event(new LowInventoryDetected(
            inventoryable: $level->inventoryable,
            location: $level->location,
            currentQuantity: $level->available,
            reorderPoint: $level->effective_reorder_point,
            suggestedReorder: $level->getSuggestedReorderQuantity()
        ));
    }
}
```

---

## Decimal Quantity Support

For items sold by weight, volume, or length:

### DecimalQuantityService

```php
class DecimalQuantityService
{
    public function receive(
        InventoryLevel $level,
        float $quantity,
        int $precision = 4
    ): void {
        if (! $level->use_decimal_quantities) {
            throw new InventoryException('Decimal quantities not enabled for this level');
        }
        
        $level->decimal_on_hand = bcadd(
            (string) ($level->decimal_on_hand ?? 0),
            (string) $quantity,
            $precision
        );
        
        // Keep integer field in sync (rounded)
        $level->quantity_on_hand = (int) round($level->decimal_on_hand);
        $level->save();
    }

    public function ship(InventoryLevel $level, float $quantity): void
    {
        if ($level->getDecimalAvailable() < $quantity) {
            throw new InsufficientInventoryException();
        }
        
        $level->decimal_on_hand = bcsub(
            (string) $level->decimal_on_hand,
            (string) $quantity,
            4
        );
        
        $level->quantity_on_hand = (int) round($level->decimal_on_hand);
        $level->save();
    }
}
```

### Usage Example

```php
// Fabric sold by meter
$fabric = Product::find('uuid');

$level = InventoryLevel::firstOrCreate([
    'inventoryable_type' => Product::class,
    'inventoryable_id' => $fabric->id,
    'location_id' => $warehouse->id,
], [
    'use_decimal_quantities' => true,
]);

// Receive 100.5 meters
$decimalService->receive($level, 100.5);

// Ship 2.75 meters
$decimalService->ship($level, 2.75);

// Available: 97.75 meters
```

---

## Threshold Configuration

### Per-SKU Thresholds

```php
// Set thresholds per product/location
$level->update([
    'reorder_point' => 100,      // When to trigger reorder
    'safety_stock' => 25,        // Buffer stock
    'min_quantity' => 10,        // Absolute minimum
    'max_quantity' => 500,       // Maximum capacity
    'reorder_quantity' => 200,   // How much to order
    'lead_time_days' => 14,      // Supplier lead time
]);
```

### Threshold Inheritance

```php
class ThresholdResolver
{
    public function resolve(InventoryLevel $level): array
    {
        // Priority: Level > Product > Category > Global
        return [
            'reorder_point' => $level->reorder_point
                ?? $level->inventoryable->default_reorder_point
                ?? config('inventory.defaults.reorder_point', 10),
            'safety_stock' => $level->safety_stock
                ?? $level->inventoryable->default_safety_stock
                ?? config('inventory.defaults.safety_stock', 0),
            // ...
        ];
    }
}
```

---

## Navigation

**Previous:** [02-location-architecture.md](02-location-architecture.md)  
**Next:** [04-allocation-strategies.md](04-allocation-strategies.md)
