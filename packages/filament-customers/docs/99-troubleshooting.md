---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Resources Not Appearing

**Problem**: Customer or Segment resources not visible in navigation

**Solution**: Ensure plugin is registered:

```php
// app/Providers/Filament/AdminPanelProvider.php
use AIArmada\FilamentCustomers\FilamentCustomersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        FilamentCustomersPlugin::make(),
    ]);
}
```

### Empty Customer List

**Problem**: Customer list shows no records despite database having customers

**Solution**: Check owner scoping:

```php
// Enable debugging
use AIArmada\CommerceSupport\Support\OwnerContext;

// In your middleware or panel provider
$owner = OwnerContext::resolve();
dd($owner); // Should be set in multi-tenant mode

// Temporarily disable owner scoping for testing
// config/customers.php
'features' => [
    'owner' => [
        'enabled' => false, // Testing only
    ],
],
```

### Wallet Actions Not Working

**Problem**: Add/Deduct credit actions fail silently

**Causes**:
1. Wallet feature disabled
2. Insufficient permissions
3. Amount validation failure
4. Owner scope mismatch

**Solution**:

```php
// Check wallet config
if (!config('customers.features.wallet.enabled')) {
    // Enable in config/customers.php
}

// Verify policy authorization
Gate::authorize('update', $customer);

// Check amount limits
$minTopup = config('customers.defaults.wallet.min_topup');
$maxBalance = config('customers.defaults.wallet.max_balance');
```

### Segment Rebuild Fails

**Problem**: Segment rebuild action shows error or wrong count

**Solution**:

```php
// Check segment configuration
$segment = Segment::find('segment-id');

if (!$segment->is_automatic) {
    // Cannot rebuild manual segments
}

if (empty($segment->conditions)) {
    // Add conditions to segment
}

// Run rebuild via command for debugging
php artisan customers:rebuild-segments --segment={uuid}
```

### Form Validation Errors

**Problem**: Form submissions fail with validation errors

**Common Issues**:
1. Email already exists (unique constraint)
2. Required fields missing
3. Invalid format (phone, email)

**Solution**:

```php
// Check for existing customer
$existing = Customer::where('email', $email)->first();

// Use unique validation with ignore
Forms\Components\TextInput::make('email')
    ->unique(ignoreRecord: true);

// Verify required fields
Forms\Components\TextInput::make('first_name')
    ->required();
```

### Relation Manager Not Loading

**Problem**: Addresses, wishlists, or notes tabs not showing

**Solution**:

```php
// Ensure relation managers are registered
public static function getRelations(): array
{
    return [
        RelationManagers\AddressesRelationManager::class,
        RelationManagers\WishlistsRelationManager::class,
        RelationManagers\NotesRelationManager::class,
    ];
}

// Check relationship exists on model
$customer->addresses; // Should not error
```

### Policy Denying Access

**Problem**: 403 Forbidden errors when accessing resources

**Solution**:

```php
// Check policy implementation
use AIArmada\Customers\Policies\CustomerPolicy;

// Verify methods return true for authorized users
public function viewAny(User $user): bool
{
    return $user->can('view-customers'); // Or your logic
}

// For testing, allow all (not for production)
public function viewAny(User $user): bool
{
    return true;
}
```

### Widgets Not Displaying

**Problem**: Dashboard widgets not appearing

**Solution**:

```php
// Verify widgets are registered in plugin
FilamentCustomersPlugin::make()

// Check widget discovery
// Widgets should be in app/Filament/Widgets

// Or register manually
$panel->widgets([
    \AIArmada\FilamentCustomers\Widgets\CustomerStatsWidget::class,
    \AIArmada\FilamentCustomers\Widgets\TopCustomersWidget::class,
]);

// Check widget canView method
public static function canView(): bool
{
    return true; // For testing
}
```

### Global Search Not Working

**Problem**: Customers not appearing in global search

**Solution**:

```php
// Verify searchable attributes are defined
public static function getGloballySearchableAttributes(): array
{
    return ['first_name', 'last_name', 'email', 'phone', 'company'];
}

// Check database indexes exist
// Migrations should include:
$table->index('email');
$table->index('phone');

// Rebuild search index if using Scout
php artisan scout:import "AIArmada\Customers\Models\Customer"
```

## Performance Issues

### Slow Customer List Loading

**Problem**: Customer table takes long to load

**Solutions**:

1. **Reduce default page size**:
```php
public static function table(Table $table): Table
{
    return parent::table($table)
        ->defaultPaginationPageOption(25); // Reduce from default
}
```

2. **Optimize eager loading**:
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['segments' => fn($q) => $q->select('id', 'name')]);
}
```

3. **Add database indexes**:
```php
// In migration
$table->index(['status', 'created_at']);
$table->index('lifetime_value');
```

### Widget Query Timeouts

**Problem**: Dashboard widgets cause slow page loads

**Solutions**:

1. **Add caching**:
```php
protected function getStats(): array
{
    return cache()->remember(
        'stats-' . auth()->id(),
        now()->addMinutes(5),
        fn() => $this->calculateStats()
    );
}
```

2. **Use database aggregates**:
```php
// Instead of loading all records
$stats = Customer::query()
    ->selectRaw('COUNT(*) as total')
    ->selectRaw('SUM(lifetime_value) as ltv')
    ->first();
```

3. **Disable polling**:
```php
protected ?string $pollingInterval = null;
```

### Segment Rebuild Slow

**Problem**: Rebuilding segments takes too long

**Solutions**:

1. **Use queue**:
```php
dispatch(function() use ($segment) {
    $segment->rebuildCustomerList();
})->onQueue('segments');
```

2. **Run via scheduled task**:
```bash
php artisan customers:rebuild-segments --dry-run
```

3. **Optimize segment conditions**:
```php
// Use indexed fields in conditions
// Good: lifetime_value, total_orders, status
// Avoid: metadata, custom JSON fields
```

## Debugging Tips

### Enable Query Logging

```php
// In service provider or middleware
DB::listen(function($query) {
    Log::debug('Query', [
        'sql' => $query->sql,
        'bindings' => $query->bindings,
        'time' => $query->time,
    ]);
});
```

### Inspect Owner Context

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Check current owner
$owner = OwnerContext::resolve();
Log::info('Owner Context', [
    'owner_type' => $owner?->getMorphClass(),
    'owner_id' => $owner?->getKey(),
]);
```

### Test Policies Directly

```php
$user = auth()->user();
$customer = Customer::first();
$policy = new CustomerPolicy();

// Test each method
dd([
    'viewAny' => $policy->viewAny($user),
    'view' => $policy->view($user, $customer),
    'create' => $policy->create($user),
    'update' => $policy->update($user, $customer),
    'delete' => $policy->delete($user, $customer),
]);
```

### Check Resource Registration

```php
// Get all registered resources
$resources = Filament::getResources();
dd($resources);

// Check if specific resource is registered
$isRegistered = in_array(
    \AIArmada\FilamentCustomers\Resources\CustomerResource::class,
    $resources
);
```

## Error Messages

### "Owner context required"

**Cause**: Multi-tenancy enabled but owner not set

**Fix**:
```php
// Set owner context before accessing resources
OwnerContext::setOwner($tenant);

// Or in Filament middleware
protected function provideTenantContext(): void
{
    OwnerContext::setOwner(Filament::getTenant());
}
```

### "Address customer must belong to owner"

**Cause**: Trying to create address for customer in different owner context

**Fix**:
```php
// Ensure customer and address share owner
$customer = Customer::query()
    ->forOwner(OwnerContext::resolve(), includeGlobal: false)
    ->findOrFail($customerId);

// Then safe to create address
$customer->addresses()->create([...]);
```

### "Maximum wallet balance exceeded"

**Cause**: Adding credit would exceed configured limit

**Fix**:
```php
// Check current balance and limit
$maxBalance = config('customers.defaults.wallet.max_balance');
$available = $maxBalance - $customer->wallet_balance;

// Only add up to available amount
$amount = min($requestedAmount, $available);
```

## Getting Help

When reporting issues, include:

1. **Environment**:
   - PHP version
   - Laravel version
   - Filament version
   - Package version

2. **Configuration**:
   - `config/customers.php` (sanitized)
   - Owner scoping settings

3. **Error Details**:
   - Full error message
   - Stack trace
   - Steps to reproduce

4. **Debug Information**:
   - Query logs
   - Owner context
   - Policy results

## Next Steps

- [Resources](04-resources.md) - Review resource documentation
- [Widgets](05-widgets.md) - Review widget documentation
- [Customization](06-customization.md) - Customization options
