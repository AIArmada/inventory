---
title: Usage
---

# Usage

## Basic Operations

### Receiving Inventory

```php
use AIArmada\Inventory\Facades\Inventory;

// Basic receive
Inventory::receive($product, 100, $location->id);

// With options
Inventory::receive($product, 100, $location->id, [
    'reference' => 'PO-2024-001',
    'unit_cost_minor' => 1500, // $15.00
    'batch_number' => 'BATCH-001',
    'expires_at' => now()->addMonths(6),
]);
```

### Shipping Inventory

```php
// Basic ship
Inventory::ship($product, 10, $location->id);

// With options
Inventory::ship($product, 10, $location->id, [
    'reference' => 'ORD-2024-001',
    'actor_id' => auth()->id(),
]);
```

### Transferring Between Locations

```php
Inventory::transfer(
    $product,
    quantity: 25,
    fromLocationId: $warehouseA->id,
    toLocationId: $warehouseB->id,
    options: ['reference' => 'TRF-001']
);
```

### Adjusting Inventory

```php
// Positive adjustment (add stock)
Inventory::adjust($product, 5, $location->id, [
    'reason' => 'Found during stock count',
]);

// Negative adjustment (remove stock)
Inventory::adjust($product, -3, $location->id, [
    'reason' => 'Damaged goods',
]);
```

### Checking Availability

```php
// Get detailed availability
$availability = Inventory::getAvailability($product);
// [
//     'total' => 150,
//     'reserved' => 25,
//     'available' => 125,
//     'locations' => [
//         ['location_id' => 'uuid', 'on_hand' => 100, 'reserved' => 20, 'available' => 80],
//         ['location_id' => 'uuid', 'on_hand' => 50, 'reserved' => 5, 'available' => 45],
//     ]
// ]

// Simple check
$hasStock = Inventory::hasAvailableStock($product, 10);

// Using the trait on your model
$product->getAvailableStock(); // 125
$product->isInStock(); // true
$product->isInStock(200); // false
```

## Allocations (Reservations)

### Cart Flow

```php
use AIArmada\Inventory\Facades\InventoryAllocation;

// 1. Allocate when adding to cart
InventoryAllocation::allocate($product, 5, $cartId, ttlMinutes: 30);

// 2. Check what's allocated
$allocations = InventoryAllocation::getForCart($cartId);

// 3. Extend allocation if customer is still shopping
InventoryAllocation::extendForCart($cartId, additionalMinutes: 15);

// 4a. Commit on successful payment
InventoryAllocation::commitForCart($cartId);

// 4b. Or release if cart is abandoned
InventoryAllocation::releaseAllForCart($cartId);
```

### Manual Allocation Control

```php
// Allocate with specific strategy
$allocation = InventoryAllocation::allocate(
    $product, 
    10, 
    $cartId,
    ttlMinutes: 60,
    strategy: AllocationStrategy::FIFO
);

// Release specific allocation
InventoryAllocation::release($allocation);

// Get available quantity (considers allocations)
$available = InventoryAllocation::getTotalAvailable($product);
```

## Batch/Lot Tracking

### Creating Batches

```php
use AIArmada\Inventory\Services\BatchService;

$batchService = app(BatchService::class);

$batch = $batchService->createBatch(
    model: $product,
    locationId: $location->id,
    quantity: 500,
    batchNumber: 'LOT-2024-001',
    expiresAt: now()->addYear(),
    unitCostMinor: 1200
);
```

### FEFO Allocation

```php
use AIArmada\Inventory\Services\BatchAllocationService;

$batchAllocationService = app(BatchAllocationService::class);

// Get FEFO allocation plan
$plan = $batchAllocationService->getFefoAllocationPlan($product, 100);
// [
//     'available' => true,
//     'total_available' => 500,
//     'requested' => 100,
//     'allocations' => [
//         ['batch_id' => 'uuid', 'batch_number' => 'LOT-2024-001', 'quantity' => 100, 'expires_at' => '2025-01-15'],
//     ]
// ]

// Actually allocate using FEFO
$allocations = $batchAllocationService->allocateFefo($product, 100);
$batchAllocationService->reserveBatches($allocations);

// On payment, commit
$batchAllocationService->commitBatches($allocations);
```

### Quarantine and Recall

```php
// Quarantine a batch
$batchService->quarantine($batch, 'Pending QC inspection');

// Release from quarantine
$batchService->releaseFromQuarantine($batch);

// Recall a batch
$batchService->recall($batch, 'Product recall notice #123');
```

## Serial Number Tracking

### Registering Serials

```php
use AIArmada\Inventory\Services\SerialService;

$serialService = app(SerialService::class);

// Register single serial
$serial = $serialService->register(
    model: $product,
    serialNumber: 'SN-001-ABC',
    locationId: $location->id,
    batchId: $batch->id,
    unitCostMinor: 15000
);

// Bulk register
$serials = $serialService->bulkRegister($product, [
    'SN-001', 'SN-002', 'SN-003', 'SN-004', 'SN-005'
], $location->id);
```

### Serial Lifecycle

```php
// Reserve for order
$serialService->reserve($serial, $orderId);

// Mark as sold
$serialService->sell($serial, $orderId, $customerId);

// Customer returns
$serialService->return($serial, 'Customer changed mind');

// Mark as disposed
$serialService->dispose($serial, 'Damaged beyond repair');
```

### Serial Lookup

```php
use AIArmada\Inventory\Services\SerialLookupService;

$lookup = app(SerialLookupService::class);

// Find by serial number
$serial = $lookup->findBySerialNumber('SN-001-ABC');

// Search partial
$results = $lookup->searchBySerialNumber('SN-001', limit: 10);

// Get customer's warranty items
$warrantyItems = $lookup->getCustomerWarrantyItems($customerId);

// Get items with expiring warranty
$expiring = $lookup->getExpiringWarranty(daysAhead: 30);
```

## Inventory Costing

### FIFO Costing

```php
use AIArmada\Inventory\Services\FifoCostService;

$fifo = app(FifoCostService::class);

// Add cost layer on receipt
$fifo->addLayer($product, 100, unitCostMinor: 1500, locationId: $location->id);

// Get valuation
$valuation = $fifo->calculateValuation($product);
// ['quantity' => 100, 'value' => 150000, 'average_cost' => 1500, 'layers' => 1]

// Consume (on sale)
$result = $fifo->consume($product, 20);
// ['consumed' => 20, 'cost' => 30000, 'layers' => [...]]
```

### Weighted Average Costing

```php
use AIArmada\Inventory\Services\WeightedAverageCostService;

$wac = app(WeightedAverageCostService::class);

// Record purchase (automatically recalculates average)
$result = $wac->recordPurchase($product, 100, unitCostMinor: 1600);
// ['new_average_cost' => 1550, 'layer' => InventoryCostLayer]

// Get current average cost
$avgCost = $wac->getCurrentAverageCost($product);
```

### Standard Costing

```php
use AIArmada\Inventory\Services\StandardCostService;

$stdCost = app(StandardCostService::class);

// Set standard cost
$stdCost->setStandardCost(
    $product,
    standardCostMinor: 1500,
    effectiveFrom: now(),
    approvedBy: auth()->id()
);

// Calculate variance
$variance = $stdCost->calculateVariance($product, actualCostMinor: 1600);
// ['variance' => 100, 'variance_percentage' => 6.67, 'favorable' => false]
```

### Valuation Snapshots

```php
use AIArmada\Inventory\Services\ValuationService;

$valuationService = app(ValuationService::class);

// Create month-end snapshot
$snapshot = $valuationService->createSnapshot(CostingMethod::Fifo);

// Get historical snapshot
$historicalSnapshot = $valuationService->getSnapshot($snapshotDate);
```

## Demand Forecasting

```php
use AIArmada\Inventory\Services\DemandForecastService;

$forecast = app(DemandForecastService::class);

// Record demand (call after each sale)
$forecast->recordDemand($product, quantity: 5, fulfilledQuantity: 5);

// Get average daily demand
$avgDemand = $forecast->calculateAverageDailyDemand($product, days: 30);

// Forecast next 7 days
$prediction = $forecast->forecastDemand($product, daysAhead: 7);
// ['forecast' => 35.5, 'confidence_low' => 28.2, 'confidence_high' => 42.8]

// Get trend
$trend = $forecast->calculateTrend($product); // positive = increasing, negative = decreasing
```

## Replenishment

```php
use AIArmada\Inventory\Services\ReplenishmentService;

$replenishment = app(ReplenishmentService::class);

// Generate suggestions for items below reorder point
$suggestions = $replenishment->generateSuggestions();

// Get pending suggestions by urgency
$pending = $replenishment->getPendingSuggestions();

// Get critical (stockout imminent)
$critical = $replenishment->getCriticalSuggestions();

// Approve and process
$replenishment->approve($suggestion, approvedBy: auth()->id());
```

## Events

The package dispatches events you can listen to:

| Event | When Dispatched |
|-------|-----------------|
| `InventoryReceived` | Stock received at location |
| `InventoryShipped` | Stock shipped from location |
| `InventoryTransferred` | Stock moved between locations |
| `InventoryAdjusted` | Stock count adjusted |
| `InventoryAllocated` | Stock reserved for cart/order |
| `InventoryReleased` | Reserved stock released |
| `LowInventoryDetected` | Stock falls below reorder point |
| `OutOfInventory` | Stock reaches zero |
| `SafetyStockBreached` | Stock falls below safety stock |
| `StockRestored` | Stock recovers from critical level |
| `MaxStockExceeded` | Stock exceeds max threshold |
| `BatchCreated` | New batch created |
| `BatchExpired` | Batch reached expiry date |
| `BatchRecalled` | Batch marked for recall |

### Listening to Events

```php
// In EventServiceProvider
protected $listen = [
    \AIArmada\Inventory\Events\LowInventoryDetected::class => [
        \App\Listeners\SendLowStockAlert::class,
    ],
];
```

## Console Commands

```bash
# Clean up expired allocations
php artisan inventory:cleanup-allocations
php artisan inventory:cleanup-allocations --dry-run

# Create valuation snapshot
php artisan inventory:create-valuation-snapshot
php artisan inventory:create-valuation-snapshot --method=weighted_average
php artisan inventory:create-valuation-snapshot --location=uuid-here
```

## Using Traits on Your Models

### HasInventory Trait

```php
use AIArmada\Inventory\Traits\HasInventory;

class Product extends Model
{
    use HasInventory;
}

// Now you can:
$product->inventoryLevels;
$product->inventoryMovements;
$product->getAvailableStock();
$product->isInStock();
$product->receive(100, $locationId);
$product->ship(10, $locationId);
```

### HasSerialNumbers Trait

```php
use AIArmada\Inventory\Traits\HasSerialNumbers;

class Product extends Model
{
    use HasInventory, HasSerialNumbers;
}

// Now you can:
$product->serials;
$product->availableSerials();
$product->getSerialByNumber('SN-001');
```
