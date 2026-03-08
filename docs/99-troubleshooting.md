---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### "Insufficient inventory" when stock appears available

**Symptoms:**
- `InsufficientInventoryException` thrown
- Dashboard shows available stock, but operations fail

**Causes:**
1. **Reserved stock**: The available quantity considers reservations. Check `quantity_reserved` on the inventory level.
2. **Multi-tenancy**: Owner scoping may be hiding inventory from other tenants.
3. **Location mismatch**: Stock exists at a different location than requested.

**Solutions:**
```php
// Check actual availability (including reservations)
$level = InventoryLevel::where('inventoryable_id', $product->id)
    ->where('location_id', $location->id)
    ->first();

echo "On hand: {$level->quantity_on_hand}";
echo "Reserved: {$level->quantity_reserved}";
echo "Available: {$level->available}"; // on_hand - reserved
```

### Cart allocations expiring too quickly

**Symptoms:**
- Customers lose their cart items
- `InventoryAllocated` events followed by automatic releases

**Solution:**
Increase the TTL in config:

```php
// config/inventory.php
'allocation_ttl_minutes' => env('INVENTORY_ALLOCATION_TTL', 60), // Increase from 30
```

Or extend allocations programmatically:
```php
InventoryAllocation::extendForCart($cartId, additionalMinutes: 30);
```

### Batch/FEFO allocation fails despite available stock

**Symptoms:**
- `BatchAllocationService` throws insufficient inventory
- Regular `Inventory::ship()` works fine

**Causes:**
1. **Quarantined batches**: Batches in quarantine are excluded from FEFO allocation
2. **Expired batches**: Already expired batches are excluded

**Solution:**
```php
// Check batch statuses
$batches = InventoryBatch::forModel($product)
    ->where('location_id', $location->id)
    ->get();

foreach ($batches as $batch) {
    dump([
        'batch' => $batch->batch_number,
        'status' => $batch->status,
        'on_hand' => $batch->quantity_on_hand,
        'reserved' => $batch->quantity_reserved,
        'available' => $batch->available,
        'is_quarantined' => $batch->is_quarantined,
        'is_expired' => $batch->isExpired(),
    ]);
}
```

### Multi-tenant inventory leaking between owners

**Symptoms:**
- Tenant A sees Tenant B's inventory
- Cross-tenant allocation possible

**Causes:**
1. **Owner scoping disabled**: `inventory.owner.enabled` is `false`
2. **Missing owner context**: No owner resolved during the request
3. **Direct DB queries**: Using `DB::table()` instead of Eloquent models

**Solutions:**
1. Enable owner scoping:
```php
// config/inventory.php
'owner' => [
    'enabled' => true,
    'include_global' => false,
],
```

2. Ensure owner is resolved:
```php
// In a service provider or middleware
$this->app->bind(OwnerResolverInterface::class, function () {
    return new class implements OwnerResolverInterface {
        public function resolve(): ?Model
        {
            return auth()->user()?->currentTeam;
        }
    };
});
```

3. Always use Eloquent models (never raw `DB::table()` for tenant data):
```php
// ❌ Bad - bypasses owner scoping
DB::table('inventory_levels')->where(...);

// ✅ Good - owner scoping applied
InventoryLevel::where(...);
```

### Valuation snapshots show zero values

**Symptoms:**
- `inventory:create-valuation-snapshot` reports 0 SKUs, 0 quantity, 0 value

**Causes:**
1. **No cost layers**: FIFO/WAC valuation requires cost layers to exist
2. **No standard costs**: Standard costing requires `InventoryStandardCost` records
3. **Wrong costing method**: Using a method that has no data

**Solution:**
```php
// Check if cost layers exist
$layerCount = InventoryCostLayer::count();
echo "Cost layers: {$layerCount}";

// Check if standard costs exist
$stdCostCount = InventoryStandardCost::current()->count();
echo "Standard costs: {$stdCostCount}";

// Create cost layers when receiving
Inventory::receive($product, 100, $location->id, [
    'unit_cost_minor' => 1500,
]);
```

### "Invalid location for current owner" error

**Symptoms:**
- `InvalidArgumentException` when receiving/shipping/allocating

**Cause:**
The location belongs to a different owner than the current context.

**Solution:**
1. Verify the location's owner:
```php
$location = InventoryLocation::find($locationId);
dump($location->owner_type, $location->owner_id);
```

2. Verify current owner context:
```php
dump(OwnerContext::resolve());
```

3. Create location with correct owner or switch context.

### Stock levels don't update after operations

**Symptoms:**
- `receive()` or `ship()` completes without error
- Stock levels unchanged

**Cause:**
Usually a transaction rollback due to an uncaught exception elsewhere in the request.

**Solution:**
Check for exceptions in logs. Wrap critical operations:
```php
try {
    DB::transaction(function () use ($product, $location) {
        Inventory::receive($product, 100, $location->id);
    });
} catch (Throwable $e) {
    Log::error('Inventory receive failed', ['error' => $e->getMessage()]);
    throw $e;
}
```

## Performance Tips

### High-traffic sites

1. **Enable caching** for availability checks (built-in, automatic)
2. **Use `single_location` strategy** if you don't need split fulfillment
3. **Run cleanup command** frequently:
```bash
# Add to scheduler
$schedule->command('inventory:cleanup-allocations')->everyFifteenMinutes();
```

### Large catalogs (>100k SKUs)

1. **Index your foreign keys** (migrations already include these)
2. **Partition valuation snapshots** by date if needed
3. **Use batch operations** for bulk receives:
```php
// Process in chunks
$items->chunk(100)->each(function ($chunk) use ($location) {
    DB::transaction(function () use ($chunk, $location) {
        foreach ($chunk as $item) {
            Inventory::receive($item['product'], $item['quantity'], $location->id);
        }
    });
});
```

## Debugging

### Enable detailed logging

```php
// config/logging.php - add a channel
'inventory' => [
    'driver' => 'daily',
    'path' => storage_path('logs/inventory.log'),
    'level' => 'debug',
],
```

Then log in your listeners:
```php
Log::channel('inventory')->debug('Inventory received', [
    'product_id' => $event->model->id,
    'quantity' => $event->quantity,
    'location_id' => $event->locationId,
]);
```

### Inspect allocations

```php
// Get all allocations for debugging
$allocations = InventoryAllocation::query()
    ->with(['location', 'inventoryable'])
    ->where('cart_id', $cartId)
    ->get();

foreach ($allocations as $alloc) {
    dump([
        'id' => $alloc->id,
        'quantity' => $alloc->quantity,
        'expires_at' => $alloc->expires_at,
        'is_expired' => $alloc->isExpired(),
        'location' => $alloc->location->name,
    ]);
}
```

## Getting Help

1. Check the [AUDIT.md](AUDIT.md) for known issues and fixes
2. Review the test suite in `tests/src/Inventory/` for usage examples
3. Open an issue on GitHub with:
   - Laravel version
   - Package version
   - Minimal reproduction code
   - Error message and stack trace
