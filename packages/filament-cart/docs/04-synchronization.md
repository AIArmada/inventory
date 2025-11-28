# Event Synchronization

The plugin maintains normalized cart records through event listeners that sync with the `aiarmada/cart` package.

## Listeners

### SyncCartOnEvent

Synchronizes cart state to normalized models whenever cart events fire.

**Handles:**
- Cart creation
- Item additions/updates/removals
- Condition changes
- Cart clearing

**Models Updated:**
- `Cart` — Snapshot of cart state
- `CartItem` — Individual line items
- `CartCondition` — Applied conditions

### ApplyGlobalConditions

Automatically applies global conditions when carts are created or modified.

**Triggers:**
- New cart creation
- Items added to cart
- Cart items updated

**Behavior:**
1. Fetches active global conditions
2. Evaluates dynamic rules against current cart state
3. Applies matching conditions
4. Removes conditions that no longer match

### CleanupSnapshotOnCartMerged

Handles cleanup when guest carts merge with authenticated user carts.

**Behavior:**
- Removes orphaned snapshot records
- Transfers relevant metadata

## Configuration

Configure synchronization in `config/filament-cart.php`:

```php
return [
    'synchronization' => [
        // Use queue for sync operations
        'queue_sync' => false,
        
        // Queue connection when enabled
        'queue_connection' => 'default',
        
        // Queue name for sync jobs
        'queue_name' => 'cart-sync',
    ],
];
```

### Queue Mode

Enable queue synchronization for high-traffic applications:

```php
'synchronization' => [
    'queue_sync' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'cart-sync',
],
```

When enabled, sync operations dispatch to the queue instead of running synchronously.

## Manual Synchronization

Force-sync carts when needed:

```php
use AIArmada\FilamentCart\Jobs\SyncCartJob;

// Sync a specific cart
SyncCartJob::dispatch($cartIdentifier);
```

## Event Flow

```
Cart Event (aiarmada/cart)
    │
    ├── SyncCartOnEvent
    │   └── Updates Cart, CartItem, CartCondition models
    │
    └── ApplyGlobalConditions
        └── Evaluates and applies/removes global conditions
```

## Disabling Synchronization

To disable automatic synchronization, remove listeners from your `EventServiceProvider` or use the config:

```php
'enable_global_conditions' => false,
```

This disables global condition auto-application but keeps model synchronization active.

## Polling for Real-Time Updates

The Filament resources support polling for live updates:

```php
// config/filament-cart.php
'polling_interval' => 30, // seconds
```

Set to `0` or `null` to disable polling.
