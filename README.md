# Laravel Inventory

A comprehensive multi-location inventory and warehouse management package for Laravel with allocation strategies, cart integration, and full movement tracking.

## Features

- **Multi-Location Support** - Manage inventory across warehouses, stores, and fulfillment centers
- **Allocation Strategies** - Priority-based, FIFO, least-stock, or single-location allocation
- **Split Allocation** - Automatically split orders across multiple locations
- **Cart Integration** - Seamless integration with `aiarmada/cart` package
- **Movement Tracking** - Full audit trail of all inventory movements
- **Reservation System** - Prevent overselling during checkout
- **Per-Product Strategy** - Override global allocation strategy per product
- **Event-Driven** - Dispatch events for low inventory, allocation, and more
- **UUID Support** - First-class UUID support for all models

## Installation

```bash
composer require aiarmada/inventory
```

The package auto-discovers and registers itself. Run migrations:

```bash
php artisan migrate
```

Optionally publish the configuration:

```bash
php artisan vendor:publish --tag=inventory-config
```

## Quick Start

### 1. Add Trait to Your Model

```php
use AIArmada\Inventory\Contracts\InventoryableInterface;
use AIArmada\Inventory\Traits\HasInventory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements InventoryableInterface
{
    use HasUuids, HasInventory;
}
```

### 2. Manage Inventory

```php
use AIArmada\Inventory\Facades\Inventory;

$product = Product::find($id);
$location = InventoryLocation::where('code', 'WAREHOUSE-A')->first();

// Receive inventory
$product->receive($location->id, 100, 'Initial stock');

// Ship inventory
$product->ship($location->id, 5, 'sale', 'ORDER-123');

// Transfer between locations
$product->transfer($fromLocationId, $toLocationId, 20);

// Check availability
$total = $product->getTotalAvailable();          // All locations
$atLocation = $product->getInventoryAtLocation($locationId);

// Check if sufficient inventory exists
$product->hasInventory(10);  // true if >= 10 available across all locations
```

### 3. Use Facades

```php
use AIArmada\Inventory\Facades\Inventory;
use AIArmada\Inventory\Facades\InventoryAllocation;

// Inventory operations
Inventory::receive($product, $locationId, 100, 'Supplier delivery');
Inventory::ship($product, $locationId, 5, 'sale', 'ORDER-123');
Inventory::transfer($product, $fromId, $toId, 20);
Inventory::getAvailability($product);  // [locationId => available, ...]

// Allocations
InventoryAllocation::allocate($product, 5, 'cart-123', 30);  // Returns Collection<InventoryAllocation>
InventoryAllocation::release($product, 'cart-123');
InventoryAllocation::commit('cart-123', 'ORDER-456');
```

## Allocation Strategies

The package supports multiple allocation strategies:

| Strategy | Description |
|----------|-------------|
| `priority` | Allocate from highest-priority location first (default) |
| `fifo` | Allocate from location with oldest stock |
| `least_stock` | Allocate to balance inventory across locations |
| `single_location` | Must fulfill from one location or fail |

### Global Strategy

Set in config or environment:

```php
// config/inventory.php
'allocation_strategy' => 'priority',

// Or via .env
INVENTORY_ALLOCATION_STRATEGY=priority
```

### Per-Product Strategy

Override on individual products:

```php
// In your Product model
public function getAllocationStrategy(): ?AllocationStrategy
{
    return $this->allocation_strategy 
        ? AllocationStrategy::from($this->allocation_strategy)
        : null;  // null = use global config
}
```

Or set directly on inventory levels:

```php
$level = $product->inventoryLevels()->where('location_id', $locationId)->first();
$level->update(['allocation_strategy' => 'single_location']);
```

## Split Allocation

When enabled (default), allocations can span multiple locations:

```php
// Product needs 100 units, Warehouse A has 60, Warehouse B has 50
$allocations = InventoryAllocation::allocate($product, 100, 'cart-123');

// Returns 2 allocations:
// - 60 from Warehouse A
// - 40 from Warehouse B
```

Disable split allocation to require single-location fulfillment:

```php
// config/inventory.php
'allow_split_allocation' => false,
```

## Cart Integration

When installed with `aiarmada/cart`, the package automatically:

1. Extends `CartManager` with inventory methods
2. Releases allocations when carts are cleared
3. Commits inventory on payment success

```php
use AIArmada\Cart\Facades\Cart;

// Allocate inventory for checkout
$allocations = Cart::allocateAllInventory(30);  // 30 min TTL

// Validate availability
$validation = Cart::validateInventory();
if (!$validation['available']) {
    foreach ($validation['issues'] as $issue) {
        // $issue['itemId'], $issue['requested'], $issue['available']
    }
}

// Commit after payment
Cart::commitInventory('ORDER-123');

// Release on abandon
Cart::releaseAllInventory();
```

## Events

| Event | Description |
|-------|-------------|
| `InventoryReceived` | Stock received at location |
| `InventoryShipped` | Stock shipped from location |
| `InventoryTransferred` | Stock moved between locations |
| `InventoryAdjusted` | Stock level manually adjusted |
| `InventoryAllocated` | Stock allocated to cart |
| `InventoryReleased` | Allocation released |
| `LowInventoryDetected` | Stock below reorder point |
| `OutOfInventory` | Available stock reached zero |

## Commands

```bash
# Clean up expired allocations
php artisan inventory:cleanup-allocations
```

Schedule in your console kernel:

```php
$schedule->command('inventory:cleanup-allocations')->everyFiveMinutes();
```

## Configuration

Key options in `config/inventory.php`:

```php
return [
    'allocation_strategy' => 'priority',
    'allocation_ttl_minutes' => 30,
    'allow_split_allocation' => true,
    'default_reorder_point' => 10,
    
    'cart' => [
        'enabled' => true,
    ],
    
    'payment' => [
        'auto_commit' => true,
    ],
];
```

## Documentation

- [Configuration Guide](docs/configuration.md)
- [Allocation Strategies](docs/allocation-strategies.md)
- [Cart Integration](docs/cart-integration.md)
- [Events & Listeners](docs/events.md)
- [API Reference](docs/api-reference.md)

## Testing

```bash
./vendor/bin/pest tests/src/Inventory
```

## License

MIT License. See [LICENSE](LICENSE) for details.
