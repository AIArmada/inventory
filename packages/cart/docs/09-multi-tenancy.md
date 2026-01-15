---
title: Multi-Tenancy
---

# Multi-Tenancy

The Cart package provides built-in support for multi-tenant applications through owner scoping.

## Enabling Owner Scoping

```php
// config/cart.php
'owner' => [
    'enabled' => true,
    'include_global' => false,
],
```

## How It Works

When owner scoping is enabled:

1. **Storage Operations** - All database queries include owner constraints
2. **Cart Access** - Carts are isolated per owner
3. **Conditions** - Condition definitions can be owner-scoped

## Setting the Owner

### Via Facade

```php
use AIArmada\Cart\Facades\Cart;

// Set owner for all subsequent operations
Cart::forOwner($tenant);

// Or get a scoped cart instance
$cart = Cart::forOwner($tenant)->instance('default');
```

### Via Storage

```php
$storage = Cart::storage()->withOwner($tenant);
$items = $storage->getItems('user-123', 'default');
```

## Database Schema

The carts table includes owner columns:

```php
Schema::table('carts', function (Blueprint $table) {
    $table->string('owner_type')->nullable()->index();
    $table->string('owner_id')->nullable()->index();
});
```

## Query Behavior

### With Owner Set

```sql
SELECT * FROM carts 
WHERE identifier = 'user-123'
  AND instance = 'default'
  AND owner_type = 'App\Models\Tenant'
  AND owner_id = '456'
```

### Without Owner (Global)

When no owner is set, queries return only global records:

```sql
SELECT * FROM carts 
WHERE identifier = 'user-123'
  AND instance = 'default'
  AND (owner_type IS NULL OR owner_type = '')
  AND (owner_id IS NULL OR owner_id = '')
```

## Global vs Tenant Records

Records can be:

- **Tenant-Scoped**: `owner_type` and `owner_id` set
- **Global**: `owner_type` and `owner_id` are null/empty

### Including Global Records

```php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Include global records in queries
],
```

With `include_global: true`, tenant queries also return global records:

```sql
SELECT * FROM carts 
WHERE identifier = 'user-123'
  AND instance = 'default'
  AND (
    (owner_type = 'App\Models\Tenant' AND owner_id = '456')
    OR (owner_type IS NULL AND owner_id IS NULL)
  )
```

## Condition Model Scoping

The `Condition` model also supports owner scoping:

```php
use AIArmada\Cart\Models\Condition;

// Get tenant-specific conditions
$conditions = Condition::forOwner($tenant)->active()->get();

// Get global-only conditions
$globalConditions = Condition::forOwner(null)->active()->get();

// Both tenant and global
$allConditions = Condition::forOwner($tenant, includeGlobal: true)->get();
```

## Middleware Integration

Create middleware to set the owner context:

```php
class SetCartOwner
{
    public function handle($request, Closure $next)
    {
        if ($tenant = $request->route('tenant')) {
            Cart::forOwner($tenant);
        }
        
        return $next($request);
    }
}
```

## Service Provider Setup

Configure owner context in your service provider:

```php
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Set owner based on authenticated user's tenant
        Cart::macro('forCurrentTenant', function () {
            $tenant = auth()->user()?->tenant;
            return $tenant ? Cart::forOwner($tenant) : Cart::getFacadeRoot();
        });
    }
}
```

## Testing Multi-Tenancy

```php
use AIArmada\Cart\Facades\Cart;

it('isolates carts between tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    // Add item for tenant 1
    Cart::forOwner($tenant1)->add('SKU-001', 'Product', 999, 1);
    
    // Add item for tenant 2
    Cart::forOwner($tenant2)->add('SKU-002', 'Different', 1999, 1);
    
    // Tenant 1 only sees their cart
    expect(Cart::forOwner($tenant1)->getItems())->toHaveCount(1);
    expect(Cart::forOwner($tenant1)->get('SKU-001'))->not->toBeNull();
    expect(Cart::forOwner($tenant1)->get('SKU-002'))->toBeNull();
    
    // Tenant 2 only sees their cart
    expect(Cart::forOwner($tenant2)->getItems())->toHaveCount(1);
    expect(Cart::forOwner($tenant2)->get('SKU-001'))->toBeNull();
    expect(Cart::forOwner($tenant2)->get('SKU-002'))->not->toBeNull();
});
```

## HasOwner Trait

The `CartModel` and `Condition` models use the `HasOwner` trait from `commerce-support`:

```php
use AIArmada\CommerceSupport\Traits\HasOwner;

class CartModel extends Model
{
    use HasOwner;
    
    protected static string $ownerScopeConfigKey = 'cart.owner';
}
```

This provides:

- `scopeForOwner($query, $owner, $includeGlobal)` - Query scope
- Automatic owner assignment on create (when configured)
- Owner relationship accessors

## Important Notes

1. **Owner Context is Request-Scoped** - Set owner early in request lifecycle
2. **No Implicit Scoping** - Without setting owner, global records only
3. **Migration Safety** - Run migrations for owner columns if upgrading
4. **Index Performance** - Owner columns are indexed for query performance
