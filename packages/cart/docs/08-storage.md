---
title: Storage
---

# Storage

The Cart package supports multiple storage backends with a consistent interface.

## Storage Interface

Both storage implementations implement `StorageInterface`:

```php
interface StorageInterface
{
    // Owner scoping
    public function withOwner(?Model $owner): static;
    public function getOwnerType(): ?string;
    public function getOwnerId(): string|int|null;
    
    // Cart operations
    public function has(string $identifier, string $instance): bool;
    public function forget(string $identifier, string $instance): void;
    public function flush(): void;
    
    // Items
    public function getItems(string $identifier, string $instance): array;
    public function putItems(string $identifier, string $instance, array $items): void;
    
    // Conditions
    public function getConditions(string $identifier, string $instance): array;
    public function putConditions(string $identifier, string $instance, array $conditions): void;
    
    // Combined
    public function putBoth(string $identifier, string $instance, array $items, array $conditions): void;
    
    // Metadata
    public function getMetadata(string $identifier, string $instance, string $key): mixed;
    public function getAllMetadata(string $identifier, string $instance): array;
    public function putMetadata(string $identifier, string $instance, string $key, mixed $value): void;
    public function putMetadataBatch(string $identifier, string $instance, array $metadata): void;
    public function clearMetadata(string $identifier, string $instance): void;
    public function clearAll(string $identifier, string $instance): void;
    
    // Versioning
    public function getVersion(string $identifier, string $instance): ?int;
    public function getId(string $identifier, string $instance): ?string;
    public function getCreatedAt(string $identifier, string $instance): ?string;
    public function getUpdatedAt(string $identifier, string $instance): ?string;
    public function getExpiresAt(string $identifier, string $instance): ?string;
    public function isExpired(string $identifier, string $instance): bool;
    
    // Migration
    public function swapIdentifier(string $oldId, string $newId, string $instance): bool;
    public function getInstances(string $identifier): array;
    public function forgetIdentifier(string $identifier): void;
}
```

## Database Storage

The default production storage using database persistence.

### Features

- **Optimistic Locking (CAS)** - Compare-and-swap prevents lost updates
- **TTL Support** - Automatic cart expiration
- **Owner Scoping** - Multi-tenant data isolation
- **JSON Columns** - Efficient storage with optional JSONB indexes
- **Transaction Safety** - All operations are atomic

### Configuration

```php
// config/cart.php
'database' => [
    'table' => 'carts',
    'ttl' => 60 * 60 * 24 * 30, // 30 days
    'lock_for_update' => false, // Enable for high contention
    'json_column_type' => 'jsonb', // PostgreSQL optimization
],
```

### Optimistic Locking

Every cart has a `version` column. Updates use CAS:

```php
// Pseudo-code of CAS update
$current = SELECT version FROM carts WHERE id = ?;
UPDATE carts SET ..., version = version + 1 
    WHERE id = ? AND version = $current;

// If 0 rows updated, throw CartConflictException
```

### Handling Conflicts

```php
use AIArmada\Cart\Exceptions\CartConflictException;

try {
    Cart::add('SKU-001', 'Product', 999, 1);
} catch (CartConflictException $e) {
    // Cart was modified by another request
    $e->expectedVersion; // What we expected
    $e->actualVersion;   // What we found
    
    // Retry or inform user
}
```

### PostgreSQL JSONB Indexes

The migration automatically creates GIN indexes for JSONB columns:

```sql
CREATE INDEX carts_items_gin_index ON carts USING GIN (items);
CREATE INDEX carts_conditions_gin_index ON carts USING GIN (conditions);
CREATE INDEX carts_metadata_gin_index ON carts USING GIN (metadata);
```

## Session Storage

For development, testing, or stateless applications.

### Usage

```php
// In service provider
$this->app->bind('cart.storage', function ($app) {
    return new \AIArmada\Cart\Storage\SessionStorage(
        session: $app['session']
    );
});
```

### Features

- **No Database Required** - Uses Laravel session
- **Same Interface** - Drop-in replacement for DatabaseStorage
- **Version Tracking** - Simulated versioning via session keys
- **Owner Scoping** - Supported via key prefixing

### Limitations

- Data lost when session expires
- No cross-request persistence for API clients
- Not suitable for load-balanced environments without sticky sessions

## Owner Scoping

Both storage backends support multi-tenant isolation:

```php
// Get storage with owner scope
$storage = Cart::storage()->withOwner($tenant);

// All operations now scoped to tenant
$storage->getItems('user-123', 'default'); // Only tenant's carts
```

### How It Works

**Database Storage:**
```sql
SELECT * FROM carts 
WHERE identifier = ? 
  AND instance = ?
  AND owner_type = 'App\Models\Tenant'
  AND owner_id = '123'
```

**Session Storage:**
```php
// Key format
"cart.owner.App\Models\Tenant.123.{identifier}.{instance}.items"
```

## Data Validation

Storage validates data before persistence:

### Size Limits

```php
'limits' => [
    'max_items' => 1000,
    'max_data_size_bytes' => 1048576, // 1MB
],
```

### Serialization Validation

Non-serializable values are rejected:

```php
// These will throw InvalidArgumentException:
Cart::setMetadata('callback', fn() => 'test'); // Closures
Cart::setMetadata('resource', fopen('file', 'r')); // Resources
Cart::setMetadata('object', new NonSerializableClass()); // Non-JSON objects
```

## Cart Migration

Transfer carts between identifiers (e.g., guest to user):

```php
use AIArmada\Cart\Services\CartMigrationService;

$migrationService = app(CartMigrationService::class);

// Swap cart ownership
$migrationService->swapGuestCartToUser(
    userId: 123,
    instance: 'default',
    guestSessionId: 'session-abc'
);

// Full migration with merge
$result = $migrationService->migrateGuestCartForUser(
    user: $user,
    instance: 'default',
    sessionId: $guestSessionId
);
```

### Auto-Migration on Login

When enabled, carts migrate automatically:

```php
// config/cart.php
'migration' => [
    'auto_migrate_on_login' => true,
    'merge_strategy' => 'add_quantities',
],
```

The package listens to `Attempting` and `Login` events:

1. `HandleUserLoginAttempt` - Captures session ID before auth regenerates it
2. `HandleUserLogin` - Migrates cart using captured session ID

## Custom Storage

Implement `StorageInterface` for custom backends:

```php
class RedisStorage implements StorageInterface
{
    public function getItems(string $identifier, string $instance): array
    {
        $key = "cart:{$identifier}:{$instance}:items";
        return json_decode(Redis::get($key), true) ?? [];
    }
    
    // ... implement all interface methods
}

// Register
$this->app->bind('cart.storage', RedisStorage::class);
```
