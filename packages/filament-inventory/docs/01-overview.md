---
title: Overview
---

# Filament Inventory

A comprehensive Filament admin panel plugin for inventory and warehouse management. This package provides full CRUD resources, dashboard widgets, and operational actions for managing inventory across multiple locations.

## Features

### Resources

- **Locations** — Manage warehouses, stores, and fulfillment centers with priority-based allocation
- **Stock Levels** — View and manage inventory per product per location with reorder alerts
- **Movements** — Complete audit trail of all inventory transactions (receipts, shipments, transfers, adjustments)
- **Allocations** — Monitor active cart allocations with expiration tracking
- **Batches** — Track lot/batch numbers with expiry date management (optional)
- **Serial Numbers** — Individual unit tracking with warranty and condition tracking (optional)

### Dashboard Widgets

| Widget | Purpose |
|--------|---------|
| **Stats Overview** | Active locations, total SKUs, on-hand, reserved, available |
| **KPI Widget** | Turnover ratio, days on hand, fill rate, accuracy |
| **Low Inventory Alerts** | Items below reorder point |
| **Expiring Batches** | Batches approaching expiry |
| **Reorder Suggestions** | AI-generated reorder recommendations |
| **Backorders** | Open backorder tracking |
| **Valuation** | Total inventory value by costing method |
| **Movement Trends** | Daily receipts/shipments/transfers chart |
| **ABC Analysis** | Pareto classification of inventory value |

### Operational Actions

- **Receive Stock** — Record incoming inventory with PO and supplier info
- **Ship Stock** — Record outgoing inventory with order and tracking info
- **Transfer Stock** — Move inventory between locations
- **Adjust Stock** — Make inventory adjustments with reason codes
- **Cycle Count** — Verify physical counts and auto-adjust variances
- **Release Allocation** — Manually release cart allocations

## Architecture

```
filament-inventory/
├── Actions/           # Reusable Filament actions
├── Resources/         # Filament resources with Pages/Schemas/Tables
├── Services/          # Stats aggregation and caching
├── Support/           # Multitenancy helpers
└── Widgets/           # Dashboard widgets
```

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- `aiarmada/inventory` (core inventory package)

## Quick Start

```php
use AIArmada\FilamentInventory\FilamentInventoryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentInventoryPlugin::make(),
        ]);
}
```

## Multitenancy

The package fully supports owner-scoped multitenancy through the `InventoryOwnerScope` helper. When owner mode is enabled in the core inventory package, all resources automatically filter data to the current tenant context.
