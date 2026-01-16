---
title: Overview
---

# Inventory Package

A comprehensive, enterprise-grade inventory management system for Laravel applications. Supports multi-location warehouses, batch/lot tracking, serial number management, multiple costing methods, demand forecasting, and seamless cart integration.

## Key Features

### Multi-Location Inventory
- **Hierarchical locations** with parent-child relationships (warehouse → zone → bin)
- **Temperature zones** for cold chain compliance
- **Hazmat certification** tracking per location
- **Capacity management** with current/max utilization tracking
- **Coordinates** (lat/lng) for logistics optimization

### Stock Management
- **Real-time stock levels** per SKU per location
- **Reserved quantity** tracking for cart allocations
- **Reorder points** and **safety stock** thresholds
- **Alert statuses**: None, LowStock, SafetyBreached, OutOfStock, OverStock
- **Lead time** tracking for replenishment calculations

### Batch/Lot Tracking
- **FEFO** (First Expired, First Out) allocation strategy
- **Expiry date** management with proactive alerts
- **Quarantine** and **recall** workflows
- **Batch split** and **merge** operations
- **Unit cost** tracking per batch

### Serial Number Management
- **Full lifecycle tracking**: Available → Reserved → Sold → Returned → Disposed
- **Condition tracking**: New, Refurbished, Used, Damaged, ForParts
- **Warranty expiry** management
- **Customer/order** association
- **Complete audit history** via serial history records

### Inventory Costing
- **FIFO** (First In, First Out) cost layers
- **Weighted Average** cost with automatic recalculation
- **Standard Cost** with effective date ranges and variance analysis
- **Valuation snapshots** for period-end reporting

### Demand Forecasting & Replenishment
- **Demand history** recording (daily/weekly/monthly)
- **Exponential smoothing** forecast
- **Weighted moving average** calculation
- **Trend analysis** (linear regression)
- **EOQ** (Economic Order Quantity) calculation
- **Auto-generated reorder suggestions** with urgency levels

### Cart Integration
- **Inventory validation** on item add
- **Automatic allocation** of stock to carts
- **TTL-based allocation expiry** cleanup
- **Backorder** support with configurable limits
- **Payment integration** to commit allocations on checkout

### Multi-Tenancy
- **Owner-scoped** inventory (per team/organization)
- **Global inventory** support for shared catalogs
- **Configurable** via `inventory.owner.enabled`

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Facades                                  │
│    Inventory::receive()    InventoryAllocation::allocate()      │
└─────────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────────┐
│                        Actions                                  │
│   ReceiveInventory  ShipInventory  TransferInventory  Adjust... │
└─────────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────────┐
│                        Services                                 │
│  InventoryService      InventoryAllocationService               │
│  BatchService          SerialService                            │
│  ValuationService      FifoCostService                          │
│  DemandForecastService ReplenishmentService                     │
│  StockThresholdService AlertDispatchService                     │
│  LocationTreeService   SerialLookupService                      │
│  ExpiryMonitorService  BackorderService                         │
│  BatchAllocationService WeightedAverageCostService              │
│  StandardCostService                                            │
└─────────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────────┐
│                        Models                                   │
│  InventoryLocation     InventoryLevel      InventoryMovement    │
│  InventoryAllocation   InventoryBatch      InventorySerial      │
│  InventorySerialHistory InventoryCostLayer                      │
│  InventoryStandardCost InventoryValuationSnapshot               │
│  InventoryBackorder    InventoryDemandHistory                   │
│  InventorySupplierLeadtime InventoryReorderSuggestion           │
└─────────────────────────────────────────────────────────────────┘
```

## Quick Example

```php
use AIArmada\Inventory\Facades\Inventory;
use AIArmada\Inventory\Facades\InventoryAllocation;

// Receive inventory
Inventory::receive($product, 100, $location->id, [
    'reference' => 'PO-2024-001',
    'unit_cost_minor' => 1500, // $15.00
]);

// Check availability
$available = Inventory::getAvailability($product);
// ['total' => 100, 'reserved' => 0, 'available' => 100]

// Allocate for a cart
InventoryAllocation::allocate($product, 5, $cartId, ttlMinutes: 30);

// Ship after payment
Inventory::ship($product, 5, $location->id, [
    'reference' => 'ORD-2024-001',
]);
```

## Requirements

- PHP 8.4+
- Laravel 12.x
- `aiarmada/commerce-support` (required)
- `aiarmada/cart` (optional, for cart integration)

## License

MIT License. See [LICENSE](../LICENSE) for details.
