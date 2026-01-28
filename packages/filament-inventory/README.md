# Laravel Filament Inventory

Filament admin panel resources for inventory and warehouse management.

## Features

- **Location Management** - Create and manage warehouses, stores, and fulfillment centers
- **Inventory Levels** - View and adjust inventory per product per location
- **Movement History** - Complete audit trail of all inventory movements
- **Allocation Tracking** - Monitor active cart allocations
- **Dashboard Widgets** - Stats overview, low inventory alerts, movement charts

## Installation

```bash
composer require aiarmada/filament-inventory
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-inventory-config
```

## Setup

Register the plugin in your Filament panel provider:

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

## Resources

### Inventory Locations

Full CRUD for managing warehouses and locations:
- Name, code (unique identifier), address lines, city, state, postcode, country
- Priority (for allocation order)
- Active/inactive status

### Inventory Levels

View and manage inventory per product per location:
- Quantity on hand, reserved, available
- Reorder point configuration
- Per-product allocation strategy override
- Quick adjustment actions

### Inventory Movements

Read-only history of all inventory movements:
- Movement type (Receipt, Shipment, Transfer, Adjustment)
- Source/destination locations
- Quantity, reason, reference
- User and timestamp

### Inventory Allocations

Monitor active cart allocations:
- Product, location, cart ID
- Allocated quantity
- Expiration time
- Bulk release action

## Widgets

### Inventory Stats Widget

Overview statistics:
- Total locations
- Total SKUs tracked
- Total units on hand
- Total units reserved
- Active allocations

### Low Inventory Alerts Widget

Table of items below reorder point:
- Product name
- Location
- Current available quantity
- Reorder point threshold

## Configuration

```php
// config/filament-inventory.php
return [
    'navigation_group' => 'Inventory',
    
    'resources' => [
        'navigation_sort' => [
            'locations' => 10,
            'levels' => 20,
            'movements' => 30,
            'allocations' => 40,
        ],
    ],
    
    'polling_interval' => '45s',
];
```

## License

MIT License. See [LICENSE](LICENSE) for details.
