---
title: Resources
---

# Resources

The package provides six Filament resources for managing inventory data.

## Inventory Locations

Manage warehouses, stores, and fulfillment centers.

### Features

- Full CRUD operations
- Priority-based allocation ordering
- Active/inactive status
- Address and metadata storage
- Navigation badge showing active location count

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Location display name |
| `code` | string | Unique identifier (e.g., "WH-001") |
| `address` | text | Physical address |
| `priority` | integer | Higher priority = used first for allocation |
| `is_active` | boolean | Inactive locations excluded from allocation |
| `metadata` | json | Custom key-value pairs |

### Usage

```php
// Access the resource
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;

// Get the Eloquent query (owner-scoped)
$query = InventoryLocationResource::getEloquentQuery();
```

## Stock Levels

View and manage inventory quantities per product per location.

### Features

- View on-hand, reserved, and available quantities
- Set reorder points for low-stock alerts
- Override allocation strategy per SKU
- Quick filters by stock status (In Stock, Low Stock, Out of Stock)
- Navigation badge showing low-stock count

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `location_id` | uuid | Foreign key to location |
| `inventoryable_type` | string | Polymorphic product type |
| `inventoryable_id` | uuid | Polymorphic product ID |
| `quantity_on_hand` | integer | Physical stock count |
| `quantity_reserved` | integer | Allocated to pending orders |
| `reorder_point` | integer | Alert threshold |
| `allocation_strategy` | enum | Override for this SKU |

### Calculated Fields

- **Available** = `quantity_on_hand - quantity_reserved`

## Inventory Movements

Read-only audit trail of all inventory transactions.

### Features

- Filter by movement type, location, date range
- View source/destination locations for transfers
- Reference tracking (order, PO, etc.)
- User attribution

### Movement Types

| Type | Description |
|------|-------------|
| `receipt` | Incoming stock (purchase, return) |
| `shipment` | Outgoing stock (sales, transfers out) |
| `transfer` | Between-location movement |
| `adjustment` | Manual correction |
| `allocation` | Reserved for order |
| `release` | Released from reservation |

## Inventory Allocations

Monitor active cart allocations.

### Features

- View allocated quantity and cart ID
- Expiration time tracking
- Bulk release action
- "Cleanup Expired" header action
- Navigation badge showing expired allocation count

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `location_id` | uuid | Where stock is allocated |
| `cart_id` | uuid | Associated cart |
| `quantity` | integer | Allocated units |
| `expires_at` | datetime | When allocation expires |

## Inventory Batches

Track lot/batch numbers with expiry management.

### Features

- Batch and lot number tracking
- Expiry date management
- Quantity tracking (initial, current, reserved)
- Status management (Active, Quarantined, Expired, Depleted)
- Navigation badge showing batches expiring soon

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `batch_number` | string | Primary batch identifier |
| `lot_number` | string | Secondary lot identifier |
| `location_id` | uuid | Storage location |
| `status` | enum | Batch status |
| `initial_quantity` | integer | Original quantity |
| `current_quantity` | integer | Remaining quantity |
| `reserved_quantity` | integer | Reserved units |
| `manufactured_at` | date | Production date |
| `expires_at` | date | Expiry date |
| `received_at` | date | Receipt date |

### Enable/Disable

```php
// config/filament-inventory.php
'features' => [
    'batch_resource' => true,
],
```

## Serial Numbers

Individual unit tracking with warranty management.

### Features

- Unique serial number tracking
- Status management (Available, Allocated, Sold, Returned, etc.)
- Condition tracking (New, Refurbished, Damaged, etc.)
- Warranty expiration tracking
- Order/customer association

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `serial_number` | string | Unique identifier |
| `location_id` | uuid | Current location |
| `batch_id` | uuid | Associated batch (optional) |
| `status` | enum | Serial status |
| `condition` | enum | Physical condition |
| `unit_cost_minor` | integer | Unit cost in minor currency |
| `warranty_expires_at` | date | Warranty end date |
| `order_id` | uuid | Associated order |
| `customer_id` | uuid | Associated customer |

### Enable/Disable

```php
// config/filament-inventory.php
'features' => [
    'serial_resource' => true,
],
```

## Owner Scoping

All resources automatically apply owner scoping when multitenancy is enabled:

```php
// config/inventory.php
'owner' => [
    'enabled' => true,
    'include_global' => false,
],
```

The `InventoryOwnerScope` helper ensures:
1. Queries are filtered to the current owner context
2. Location relationship selects are scoped
3. Action handlers validate location ownership
