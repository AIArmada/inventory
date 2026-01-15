---
title: Multitenancy
---

# Multitenancy

The package supports full multitenancy through owner scoping. All models and queries can be automatically scoped to a tenant (owner), enabling SaaS and multi-store deployments.

## Overview

Multitenancy is implemented via the `commerce-support` package primitives:

1. **Owner Columns** — `owner_type` and `owner_id` morphs on all tables
2. **HasOwner Trait** — Provides scoping methods and owner assignment
3. **OwnerContext** — Resolves the current owner from request context
4. **HasFilamentCartOwner** — Package-specific trait with fail-safe behavior

## Enabling Owner Scoping

Configure owner scoping in the config file:

```php
// config/filament-cart.php
'owner' => [
    'enabled' => true,           // Enable owner scoping
    'include_global' => false,   // Include records with null owner
],
```

## How It Works

When owner scoping is enabled:

1. **On Save** — Models automatically get `owner_type` and `owner_id` from `OwnerContext`
2. **On Query** — The `forOwner()` scope filters to current owner
3. **Validation** — Foreign key references are validated within owner scope
4. **Fail-Fast** — Operations without owner context throw exceptions

## OwnerContext

The `OwnerContext` class from `commerce-support` resolves the current owner:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Resolve current owner (from Filament tenant, auth, etc.)
$owner = OwnerContext::resolve();

// Check if owner exists
if ($owner) {
    echo "Owner: " . get_class($owner) . "#" . $owner->getKey();
}
```

### Registering Owner Resolver

In your service provider, register how to resolve the owner:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

public function boot(): void
{
    // From Filament tenant
    OwnerContext::resolveUsing(function () {
        return filament()->getTenant();
    });

    // Or from authenticated user's organization
    OwnerContext::resolveUsing(function () {
        return auth()->user()?->organization;
    });

    // Or from a custom header/subdomain
    OwnerContext::resolveUsing(function () {
        $tenantId = request()->header('X-Tenant-ID');
        return $tenantId ? Tenant::find($tenantId) : null;
    });
}
```

## HasFilamentCartOwner Trait

All filament-cart models use the `HasFilamentCartOwner` trait:

```php
use AIArmada\FilamentCart\Models\Concerns\HasFilamentCartOwner;

class Cart extends Model
{
    use HasFilamentCartOwner;
    // ...
}
```

### Trait Features

```php
// Check if scoping is enabled
Cart::ownerScopingEnabled(); // bool

// Resolve current owner
$owner = Cart::resolveCurrentOwner(); // Model|null

// Query scope
$carts = Cart::query()->forOwner()->get();

// With specific owner
$carts = Cart::query()->forOwner($tenant)->get();

// Include global records (owner = null)
$carts = Cart::query()->forOwner(includeGlobal: true)->get();
```

### Auto-Assignment on Save

When saving a model without owner:

```php
// Owner is auto-assigned from OwnerContext
$cart = new Cart(['identifier' => 'session-123']);
$cart->save(); // owner_type and owner_id set automatically

// If no owner context and scoping is enabled:
// RuntimeException: Cart requires an owner context...
```

### Manual Owner Assignment

```php
use AIArmada\CommerceSupport\Traits\HasOwner;

// Assign owner explicitly
$cart->assignOwner($tenant);
$cart->save();

// Check owner
$cart->owner_type; // "App\Models\Tenant"
$cart->owner_id;   // "uuid-here"

// Get owner relationship
$tenant = $cart->owner; // Returns the Tenant model
```

## Model Validation

Models validate foreign key references within owner scope:

### RecoveryCampaign

```php
// When saving, validates:
// - control_template_id belongs to owner (or is global)
// - variant_template_id belongs to owner (or is global)

$campaign = RecoveryCampaign::create([
    'name' => 'Test',
    'control_template_id' => $template->id, // Validated!
]);

// If template doesn't belong to owner:
// RuntimeException: RecoveryCampaign.control_template_id must reference 
// a template within the current owner scope.
```

### RecoveryAttempt

```php
// When saving, validates:
// - campaign_id belongs to owner
// - template_id belongs to owner (or is global)
// - cart_id belongs to owner
```

### AlertLog

```php
// When saving, validates:
// - alert_rule_id belongs to owner
// - cart_id belongs to owner
```

## Query Builder Scoping

For raw query builder (non-Eloquent) queries, use `OwnerQuery`:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Support\Facades\DB;

$table = 'cart_snapshots';
$query = DB::table($table);

$owner = OwnerContext::resolve();
$includeGlobal = (bool) config('filament-cart.owner.include_global', false);

OwnerQuery::applyToQueryBuilder(
    $query,
    $owner,
    $includeGlobal,
    "{$table}.owner_type",
    "{$table}.owner_id"
);

$results = $query->get();
```

This is used internally by services like `CartMonitor` and `RecentActivityWidget`.

## Filament Panel Tenancy

When using Filament's panel tenancy:

```php
// app/Providers/Filament/AdminPanelProvider.php
use App\Models\Team;

public function panel(Panel $panel): Panel
{
    return $panel
        ->tenant(Team::class)
        // ...
}
```

Register the owner resolver:

```php
// app/Providers/AppServiceProvider.php
use AIArmada\CommerceSupport\Support\OwnerContext;

public function boot(): void
{
    OwnerContext::resolveUsing(fn () => filament()->getTenant());
}
```

All cart resources will automatically scope to the current team.

## Global Records

Some records may be "global" (available to all tenants):

```php
// Create a global template (no owner)
$template = RecoveryTemplate::create([
    'name' => 'Default Template',
    'type' => 'email',
    // owner_type and owner_id will be null
]);

// Query with include_global
$templates = RecoveryTemplate::query()
    ->forOwner(includeGlobal: true)
    ->get();
// Returns tenant templates + global templates
```

### Configuration

```php
// config/filament-cart.php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Include null-owner records by default
],
```

### Per-Query Override

```php
// Include global even if config says false
RecoveryTemplate::query()
    ->forOwner(includeGlobal: true)
    ->get();

// Exclude global even if config says true
RecoveryTemplate::query()
    ->forOwner(includeGlobal: false)
    ->get();
```

## Cross-Tenant Operations

For admin/system operations across tenants:

### Temporary Context Override

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Run as specific tenant
OwnerContext::runAs($tenant, function () {
    // All queries scoped to $tenant
    $carts = Cart::query()->forOwner()->get();
});

// Run without owner (global/system context)
OwnerContext::runWithout(function () {
    // Queries not scoped
    $allCarts = Cart::all();
});
```

### Without Global Scope

```php
use Illuminate\Database\Eloquent\SoftDeletingScope;

// Bypass owner scope for a single query
Cart::query()
    ->withoutGlobalScope('owner')
    ->get();
```

## Jobs and Commands

Queue jobs and console commands don't have request context:

### Jobs

Pass owner explicitly:

```php
class ProcessCartRecovery implements ShouldQueue
{
    public function __construct(
        public string $ownerId,
        public string $ownerType,
    ) {}

    public function handle(): void
    {
        $owner = $this->ownerType::find($this->ownerId);
        
        OwnerContext::runAs($owner, function () {
            // Now owner context is available
            $carts = Cart::query()->forOwner()->abandoned()->get();
        });
    }
}
```

### Console Commands

Iterate over tenants:

```php
class ProcessAllTenantsCommand extends Command
{
    public function handle(): void
    {
        $teams = Team::all();
        
        foreach ($teams as $team) {
            OwnerContext::runAs($team, function () use ($team) {
                $this->info("Processing team: {$team->name}");
                
                // All queries scoped to this team
                app(RecoveryScheduler::class)->scheduleForAllCampaigns();
            });
        }
    }
}
```

## Database Migrations

All filament-cart migrations include owner columns:

```php
Schema::create('cart_snapshots', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->nullableMorphs('owner'); // owner_type, owner_id
    // ...
});
```

### Indexing

For performance, ensure composite indexes:

```php
// Add if needed for your query patterns
$table->index(['owner_type', 'owner_id', 'checkout_abandoned_at']);
$table->index(['owner_type', 'owner_id', 'created_at']);
```

## Testing

### Testing with Owner Context

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

test('carts are scoped to owner', function () {
    $tenant1 = Team::factory()->create();
    $tenant2 = Team::factory()->create();
    
    // Create cart for tenant1
    OwnerContext::runAs($tenant1, function () {
        Cart::factory()->create(['identifier' => 'cart-1']);
    });
    
    // Create cart for tenant2
    OwnerContext::runAs($tenant2, function () {
        Cart::factory()->create(['identifier' => 'cart-2']);
    });
    
    // Query as tenant1
    OwnerContext::runAs($tenant1, function () {
        $carts = Cart::query()->forOwner()->get();
        expect($carts)->toHaveCount(1);
        expect($carts->first()->identifier)->toBe('cart-1');
    });
});
```

### Testing Cross-Tenant Protection

```php
test('cannot access other tenant carts', function () {
    $tenant1 = Team::factory()->create();
    $tenant2 = Team::factory()->create();
    
    $cart = null;
    OwnerContext::runAs($tenant1, function () use (&$cart) {
        $cart = Cart::factory()->create();
    });
    
    OwnerContext::runAs($tenant2, function () use ($cart) {
        // Cart not visible to tenant2
        expect(Cart::find($cart->id))->toBeNull();
        
        // Via forOwner scope
        expect(Cart::query()->forOwner()->find($cart->id))->toBeNull();
    });
});
```

## Troubleshooting

### "Requires an owner context" Exception

```
RuntimeException: Cart requires an owner context when filament-cart.owner.enabled=true.
```

**Cause:** Owner scoping is enabled but no owner context was resolved.

**Solutions:**
1. Register an `OwnerContext::resolveUsing()` callback
2. Ensure the resolver returns a model (not null)
3. For jobs/commands, use `OwnerContext::runAs()`

### Seeing All Tenant Data

**Cause:** `forOwner()` scope not applied to query.

**Solutions:**
1. Always use `->forOwner()` on queries
2. Check that `owner.enabled` is `true` in config
3. For raw queries, use `OwnerQuery::applyToQueryBuilder()`

### Global Records Not Showing

**Cause:** `include_global` is false.

**Solutions:**
1. Set `owner.include_global` to `true` in config
2. Or pass `includeGlobal: true` to `forOwner()`
