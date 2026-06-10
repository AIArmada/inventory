---
title: Overview
---

# Inventory Package

## Purpose

The `aiarmada/inventory` package owns stock state, warehouse structure, allocation, costing, and replenishment workflows for the Commerce ecosystem.

## What this package owns

- Inventory locations, levels, movements, allocations, batches, serials, cost layers, valuation snapshots, backorders, demand history, supplier lead times, and reorder suggestions
- Stock receipt, shipment, transfer, adjustment, cycle count, and allocation workflows
- Inventory validation and reservation hooks used by cart and checkout integrations
- Valuation, threshold monitoring, expiry handling, forecasting, and replenishment services

## What this package does not own

- Product or variant catalog records (`aiarmada/products`)
- Cart UI or admin panel surfaces (`aiarmada/cart`, `aiarmada/filament-inventory`)
- Checkout, payment, or order orchestration (`aiarmada/checkout`, `aiarmada/orders`, `aiarmada/cashier`, `aiarmada/chip`)
- Shipping carrier workflows and fulfilment integrations (`aiarmada/shipping`, `aiarmada/jnt`)

## Related packages

- [`aiarmada/filament-inventory`](../../filament-inventory/docs/01-overview.md) — Filament admin resources, widgets, and actions for inventory operations
- [`aiarmada/cart`](../../cart/docs/01-overview.md) — basket and allocation entry points
- [`aiarmada/orders`](../../orders/docs/01-overview.md) — order lifecycle hooks that commit or release inventory
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping, shared helpers, and common contracts

## Main models services or surfaces

- **Models** — `InventoryLocation`, `InventoryLevel`, `InventoryMovement`, `InventoryAllocation`, `InventoryBatch`, `InventorySerial`, `InventoryCostLayer`, `InventoryValuationSnapshot`, `InventoryBackorder`, `InventoryDemandHistory`, `InventorySupplierLeadtime`, `InventoryReorderSuggestion`
- **Actions (16)** — `ReceiveInventory`, `ShipInventory`, `TransferInventory`, `AdjustInventory`, `AllocateStock`, `CommitStock`, `ReleaseStock`, `CreateBatch`, `RecordSerial`, `CreateBackorder`, `ResolveBackorder`, `ProcessExpiredBatches`, `CheckLowInventory`, `ApproveReorderSuggestion`, `RejectReorderSuggestion`, `CreateValuationSnapshot`
- **Contracts (6)** — `InventoryableInterface`, `CheckoutInventoryServiceInterface`, `CostingMethodInterface`, `ProvidesInventoryCommitContext`, `ExportInterface`, `ReportInterface`
- **Facades** — `Inventory`, `InventoryAllocation`
- **Services** — reorganized into subdirectories: `Batch/`, `Costing/`, `Serial/`, `Stock/`
- **Support registries** — `AllocationStrategyRegistry`, `CostingMethodRegistry`, `ExportRegistry`, `ReportRegistry`

## Owner scoping and security notes

- Owner enforcement is controlled by `inventory.owner.enabled`, `inventory.owner.include_global`, and `inventory.owner.auto_assign_on_create`
- Non-request surfaces such as jobs and commands should set owner context explicitly through `commerce-support`
- UI filtering is not authorization; integrations that submit location, batch, allocation, or serial identifiers must still validate them server-side within the current owner scope

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
│                      Facades                                    │
│    Inventory::receive()    InventoryAllocation::allocate()      │
└─────────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────────┐
│                   Contracts (6)                                 │
│  InventoryableInterface       CheckoutInventoryServiceInterface │
│  CostingMethodInterface       ProvidesInventoryCommitContext    │
│  ExportInterface              ReportInterface                   │
└─────────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────────┐
│                 Actions (16, via AsAction)                       │
│  ReceiveInventory  ShipInventory  TransferInventory             │
│  AdjustInventory   AllocateStock   CommitStock                  │
│  ReleaseStock      CreateBatch    RecordSerial                  │
│  CreateBackorder   ResolveBackorder  ProcessExpiredBatches      │
│  CheckLowInventory  ApproveReorderSuggestion                    │
│  RejectReorderSuggestion  CreateValuationSnapshot               │
└─────────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────────┐
│                   Services (subdirectories)                      │
│  Stock/                     Batch/                              │
│    InventoryAllocationService   BatchService                    │
│    BackorderService             BatchAllocationService          │
│    ReplenishmentService         ExpiryMonitorService            │
│    DemandForecastService                                        │
│    StockThresholdService    Costing/                            │
│    AlertDispatchService        FifoCostService                  │
│    LocationTreeService         WeightedAverageCostService       │
│                                StandardCostService              │
│  Serial/                      ValuationService                  │
│    SerialService                                                 │
│    SerialLookupService    InventoryService (core ops)            │
└─────────────────────────────────────────────────────────────────┘
                                │
┌─────────────────────────────────────────────────────────────────┐
│               Support Registries                                 │
│  AllocationStrategyRegistry  CostingMethodRegistry              │
│  ExportRegistry              ReportRegistry                     │
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

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
- [Filament Inventory overview](../../filament-inventory/docs/01-overview.md)

## Requirements

- PHP 8.4+
- `aiarmada/commerce-support` (required)
- `aiarmada/cart` (optional, for cart integration)

## License

MIT License. See [LICENSE](../../../LICENSE) for details.
