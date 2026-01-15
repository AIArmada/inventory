---
title: Troubleshooting
---

# Troubleshooting

Common issues and their solutions when using the filament-cart package.

## Installation Issues

### Migration Errors

**Error:** `SQLSTATE[42S01]: Base table or view already exists`

**Solution:** Tables may already exist from a previous installation.

```bash
# Check existing tables
php artisan db:table cart_snapshots

# If reinstalling, drop tables first
php artisan migrate:rollback --step=X

# Or manually drop tables (be careful!)
php artisan tinker
>>> Schema::dropIfExists('cart_snapshots');
```

### Config Not Publishing

**Error:** Config file not appearing in `config/filament-cart.php`

**Solution:**

```bash
# Clear config cache
php artisan config:clear

# Force publish
php artisan vendor:publish --tag=filament-cart-config --force
```

### Plugin Not Registered

**Error:** Resources/pages not showing in Filament

**Solution:** Ensure the plugin is registered in your panel provider:

```php
// app/Providers/Filament/AdminPanelProvider.php
use AIArmada\FilamentCart\FilamentCartPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCartPlugin::make(),
        ]);
}
```

## Synchronization Issues

### Carts Not Syncing

**Symptom:** Cart changes in `aiarmada/cart` not appearing in Filament

**Causes & Solutions:**

1. **Event listeners not registered:**
   ```bash
   php artisan event:list | grep Cart
   # Should show SyncCartOnEvent listeners
   ```

2. **Queue not running (if queue_sync enabled):**
   ```bash
   php artisan queue:work --queue=cart-sync
   ```

3. **Wrong instance name:**
   ```php
   // Check instance matches
   Cart::instance('shopping'); // In your code
   // vs
   Cart::query()->where('instance', 'default')->get(); // In query
   ```

### Stale Snapshot Data

**Symptom:** Snapshot shows old cart state

**Solution:** Force a re-sync:

```php
use AIArmada\Cart\Facades\Cart;
use AIArmada\FilamentCart\Services\CartSyncManager;

$cart = Cart::instance('default');
app(CartSyncManager::class)->sync($cart);
```

### Missing Items or Conditions

**Symptom:** Items or conditions not appearing in snapshot

**Solution:** Check if events are firing:

```php
// In your code, ensure you're using cart methods that fire events
$cart->add($item);        // Fires ItemAdded event
$cart->condition($cond);  // Fires CartConditionAdded event
```

## Owner Scoping Issues

### "Requires an owner context" Exception

**Error:** `RuntimeException: Cart requires an owner context when filament-cart.owner.enabled=true.`

**Solutions:**

1. **Register owner resolver:**
   ```php
   // app/Providers/AppServiceProvider.php
   use AIArmada\CommerceSupport\Support\OwnerContext;
   
   public function boot(): void
   {
       OwnerContext::resolveUsing(fn () => filament()->getTenant());
   }
   ```

2. **For jobs/commands, use runAs:**
   ```php
   OwnerContext::runAs($tenant, function () {
       // Code here has owner context
   });
   ```

3. **Disable owner scoping if not needed:**
   ```php
   // config/filament-cart.php
   'owner' => [
       'enabled' => false,
   ],
   ```

### Cross-Tenant Data Leakage

**Symptom:** Seeing data from other tenants

**Causes & Solutions:**

1. **Missing `forOwner()` scope:**
   ```php
   // Wrong
   $carts = Cart::all();
   
   // Correct
   $carts = Cart::query()->forOwner()->get();
   ```

2. **Raw queries without owner scope:**
   ```php
   // Use OwnerQuery helper
   OwnerQuery::applyToQueryBuilder($query, $owner, false, 'table.owner_type', 'table.owner_id');
   ```

### Invalid Foreign Key Exception

**Error:** `Invalid campaign_id: does not belong to the current owner scope.`

**Cause:** Referencing a record from a different tenant.

**Solution:** Ensure foreign references are within owner scope:

```php
// Query within scope
$template = RecoveryTemplate::query()
    ->forOwner()
    ->where('id', $templateId)
    ->firstOrFail();

$campaign->control_template_id = $template->id;
```

## Recovery Issues

### Campaigns Not Running

**Symptom:** No recovery attempts being scheduled

**Causes & Solutions:**

1. **Scheduler not running:**
   ```bash
   php artisan cart:schedule-recovery
   ```

2. **Campaign not active:**
   ```php
   $campaign->isActive(); // Check this returns true
   // Must be: status=active, within starts_at/ends_at
   ```

3. **No eligible carts:**
   - Check `min_cart_value_cents`, `max_cart_value_cents`
   - Check `trigger_delay_minutes`
   - Carts must have email or phone in metadata

### Emails Not Sending

**Symptom:** Recovery attempts created but emails not sent

**Causes & Solutions:**

1. **Queue not running:**
   ```bash
   php artisan queue:work
   ```

2. **Process command not running:**
   ```bash
   php artisan cart:process-recovery
   ```

3. **Check attempt status:**
   ```php
   RecoveryAttempt::where('status', 'failed')->get();
   // Check failure_reason
   ```

### Tracking Not Working

**Symptom:** Opens/clicks not recorded

**Causes & Solutions:**

1. **Routes not registered:**
   ```bash
   php artisan route:list | grep recovery.track
   ```

2. **Signed URLs expired:**
   - Check `app.url` is correct
   - Extend URL signature lifetime if needed

## Monitoring Issues

### Live Stats Not Updating

**Symptom:** LiveStatsWidget shows stale data

**Causes & Solutions:**

1. **Polling disabled:**
   ```php
   // Widget should have
   protected ?string $pollingInterval = '10s';
   ```

2. **JavaScript errors:**
   - Check browser console for errors
   - Ensure Livewire is loading properly

### Alerts Not Firing

**Symptom:** No alerts being created

**Causes & Solutions:**

1. **Monitor command not running:**
   ```bash
   php artisan cart:monitor
   ```

2. **Rules not active:**
   ```php
   AlertRule::where('is_active', true)->get();
   ```

3. **Rules in cooldown:**
   ```php
   $rule->isInCooldown(); // true = in cooldown
   ```

### Fraud Detection Not Working

**Symptom:** Suspicious carts not flagged

**Solution:** Check fraud detection is enabled:

```php
// config/filament-cart.php
'features' => [
    'fraud_detection' => true,
],

'ai' => [
    'fraud_detection_enabled' => true,
    'fraud_score_threshold' => 0.7,
],
```

## Analytics Issues

### Empty Analytics Data

**Symptom:** Analytics page shows zeros

**Causes & Solutions:**

1. **Aggregation not running:**
   ```bash
   php artisan cart:aggregate-metrics
   ```

2. **Date range too narrow:**
   - Expand date range
   - Check data exists in `cart_daily_metrics`

3. **Wrong segment filter:**
   ```php
   // Ensure querying overall (null segment)
   CartDailyMetrics::whereNull('segment')->get();
   ```

### Chart Not Rendering

**Symptom:** Chart widget shows empty

**Causes & Solutions:**

1. **No data for period:**
   ```php
   $service = app(CartAnalyticsService::class);
   $trends = $service->getValueTrends($from, $to);
   dd($trends); // Check data exists
   ```

2. **Chart.js not loading:**
   - Check browser console for errors
   - Ensure Filament assets are published

## Performance Issues

### Slow Dashboard Loading

**Solutions:**

1. **Add database indexes:**
   ```php
   $table->index(['owner_type', 'owner_id', 'updated_at']);
   $table->index(['checkout_abandoned_at']);
   $table->index(['recovered_at']);
   ```

2. **Reduce polling frequency:**
   ```php
   protected ?string $pollingInterval = '30s'; // Instead of 10s
   ```

3. **Limit query results:**
   ```php
   ->limit(100) // In widget queries
   ```

### High Database Load

**Solutions:**

1. **Enable query caching:**
   ```php
   $metrics = Cache::remember(
       "cart-metrics:{$from}:{$to}",
       300, // 5 minutes
       fn () => $service->getDashboardMetrics($from, $to)
   );
   ```

2. **Use dedicated read replica:**
   ```php
   // In model
   protected $connection = 'mysql-replica';
   ```

## Debug Mode

Enable debug logging for troubleshooting:

```php
// config/filament-cart.php
'debug' => [
    'log_sync_events' => true,
    'log_recovery_attempts' => true,
    'log_alert_evaluations' => true,
],
```

Then check logs:

```bash
tail -f storage/logs/laravel.log | grep filament-cart
```

## Getting Help

If you're still stuck:

1. **Check the logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Run diagnostics:**
   ```bash
   php artisan about
   php artisan config:show filament-cart
   ```

3. **Check versions:**
   ```bash
   composer show aiarmada/filament-cart
   composer show aiarmada/cart
   composer show filament/filament
   ```

4. **Report issues:**
   - Include Laravel version
   - Include Filament version
   - Include package version
   - Include error message and stack trace
   - Include relevant config
