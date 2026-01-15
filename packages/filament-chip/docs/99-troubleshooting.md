---
title: Troubleshooting
---

# Troubleshooting

## Common Issues

### Resources Not Appearing in Navigation

**Symptom:** Plugin registered but resources don't show in sidebar.

**Causes:**
1. Plugin not registered correctly
2. Resource explicitly disabled in config
3. User lacks permission

**Solutions:**

```php
// 1. Verify plugin registration
$panel->plugin(FilamentChipPlugin::make())

// 2. Check config - ensure resource isn't null
'resources' => [
    'purchase' => PurchaseResource::class,  // Not null
],

// 3. Check resource permissions (if using policies)
// Ensure canViewAny() returns true
```

### Empty Tables (No Data)

**Symptom:** Tables load but show "No records found".

**Causes:**
1. Owner scoping filtering out all records
2. No data synced from CHIP
3. Wrong database table prefix

**Solutions:**

```php
// 1. Check owner context
// In a tinker session:
use AIArmada\Chip\Models\Purchase;
Purchase::withoutOwnerScope()->count();  // Should show total

// 2. Verify data exists
DB::table('chip_purchases')->count();

// 3. Check table prefix in config
// config/chip.php
'database' => [
    'table_prefix' => 'chip_',
],
```

### Widgets Not Showing Stats

**Symptom:** Widgets display but show 0 or no data.

**Causes:**
1. Owner scoping active
2. No purchases with `paid` status
3. Date range filtering

**Solutions:**

```php
// Check for paid purchases
Purchase::where('status', 'paid')
    ->forCurrentOwner()
    ->count();

// Verify widget date range
// Some widgets filter by current period (30 days, etc.)
```

### Billing Portal 404

**Symptom:** `/billing` returns 404.

**Causes:**
1. BillingPanelProvider not registered
2. Wrong path configuration
3. Cashier-chip not installed

**Solutions:**

```php
// 1. Register provider
// bootstrap/providers.php
AIArmada\FilamentChip\BillingPanelProvider::class,

// 2. Clear route cache
php artisan route:clear

// 3. Check cashier-chip installed
composer show aiarmada/cashier-chip
```

### Permission Denied Errors

**Symptom:** "This action is unauthorized" on resources.

**Cause:** Filament Shield or custom policies blocking access.

**Solutions:**

```php
// 1. Create a policy that allows access
class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;  // Or your logic
    }
}

// 2. Register policy
// AuthServiceProvider
protected $policies = [
    Purchase::class => PurchasePolicy::class,
];

// 3. Or use Filament Shield permissions
// Grant chip::view-any-purchase
```

### Charts Not Rendering

**Symptom:** Chart widgets show empty or broken.

**Causes:**
1. Missing Chart.js
2. No data in date range
3. JavaScript error

**Solutions:**

```bash
# 1. Republish Filament assets
php artisan filament:assets

# 2. Clear view cache
php artisan view:clear

# 3. Check browser console for JS errors
```

### Multi-Tenancy Conflicts

**Symptom:** Seeing data from other tenants.

**Cause:** Owner scope not properly applied or bypassed.

**Solution:**

```php
// Ensure BaseChipResource applies owner scope
// Check that your tenant context is set before Filament renders

// In middleware:
public function handle($request, $next)
{
    OwnerContext::set(auth()->user()->currentTeam);
    return $next($request);
}
```

## Debugging

### Enable Query Logging

```php
// In AppServiceProvider boot()
if (config('app.debug')) {
    DB::listen(function ($query) {
        Log::debug('Query', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
        ]);
    });
}
```

### Check Plugin Registration

```php
// In a controller or tinker
$panel = filament()->getPanel('admin');
$plugins = $panel->getPlugins();
dd($plugins);  // Should show FilamentChipPlugin
```

### Verify Resources Are Loaded

```php
// Check registered resources
$resources = filament()->getPanel('admin')->getResources();
dd($resources);
```

## Performance

### Slow Table Loading

**Cause:** Large dataset without pagination/filtering.

**Solutions:**

```php
// 1. Ensure pagination is enabled (default)
// Tables are paginated by default

// 2. Add database indexes
// Ensure chip_purchases has indexes on:
// - status, created_at, owner_id, owner_type

// 3. Reduce polling frequency
// config/filament-chip.php
'tables' => [
    'poll_interval' => '60s',  // Or null to disable
],
```

### Widget Loading Slow

**Cause:** Complex aggregation queries on large datasets.

**Solutions:**

```php
// 1. Use cached metrics
// The package caches aggregate data via chip:aggregate-metrics

// 2. Schedule aggregation
Schedule::command('chip:aggregate-metrics')->hourly();

// 3. Reduce widget count on dashboard
```

## Getting Help

1. Check [CHIP Package Troubleshooting](../../chip/docs/99-trouble.md)
2. Run `php artisan chip:health` to verify API connectivity
3. Enable debug logging to capture detailed errors
4. Review browser console for frontend issues
