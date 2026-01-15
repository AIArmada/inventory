---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for Filament Cashier.

## Gateway Detection Issues

### "No gateways detected" Message

**Symptoms:** Gateway Setup page appears instead of dashboard.

**Cause:** No gateway packages are installed.

**Solution:**
```bash
# Install Stripe
composer require laravel/cashier

# Or install CHIP
composer require aiarmada/cashier-chip

# Or both
composer require laravel/cashier aiarmada/cashier-chip
```

### Gateway Installed but Not Detected

**Symptoms:** Gateway is installed but not showing in UI.

**Cause:** Package not properly autoloaded.

**Solution:**
```bash
composer dump-autoload
php artisan cache:clear
php artisan config:clear
```

Verify the class exists:
```php
// For Stripe
class_exists(\Laravel\Cashier\Cashier::class); // Should return true

// For CHIP
class_exists(\AIArmada\CashierChip\Cashier::class); // Should return true
```

## Dashboard Issues

### Dashboard Shows Zero Values

**Symptoms:** MRR, subscribers, and other metrics show 0.

**Possible Causes:**

1. **No subscriptions exist**
   - Create test subscriptions in your gateway dashboard
   
2. **Wrong user model configured**
   ```php
   // config/cashier.php
   'models' => [
       'billable' => App\Models\User::class,
   ],
   ```

3. **Multitenancy scoping is too restrictive**
   - Check if `CashierOwnerScope` is filtering out all records
   - Verify owner context is properly set

4. **Database connection issues**
   - Verify migrations have been run
   - Check database credentials

### Widget Errors

**Symptoms:** Widgets show errors or fail to load.

**Solution:**
1. Check browser console for JavaScript errors
2. Verify API credentials are configured:
   ```bash
   php artisan tinker
   >>> config('services.stripe.secret')
   >>> config('chip.api_key')
   ```

3. Test gateway connectivity from Gateway Management page

## Subscription Resource Issues

### Empty Subscription List

**Symptoms:** Subscriptions page shows "No subscriptions yet."

**Possible Causes:**

1. **Subscriptions exist but aren't active**
   - Check the "All" tab instead of "Active"
   
2. **User scoping**
   - In admin panels, all subscriptions should be visible
   - In customer portal, only user's subscriptions are shown

3. **Database tables missing**
   ```bash
   php artisan migrate:status
   ```

### Subscription Actions Fail

**Symptoms:** Cancel/Resume actions show errors.

**Possible Causes:**

1. **Gateway API errors**
   - Check Laravel logs: `storage/logs/laravel.log`
   - Verify API credentials

2. **Policy denies action**
   - Check `SubscriptionPolicy` authorization
   - Ensure user owns the subscription

3. **Subscription not in correct state**
   - Can't cancel an already-canceled subscription
   - Can't resume unless on grace period

## Invoice Issues

### No Invoices Showing

**Symptoms:** Invoice list is empty.

**For Stripe:**
- Invoices are fetched from Stripe API, not database
- Ensure `STRIPE_SECRET` is configured
- User must have `stripe_id` set

**For CHIP:**
- Check if `chip_purchases` table has data
- Verify CHIP credentials

### Invoice PDF Download Fails

**Symptoms:** PDF download returns error or blank.

**For Stripe:**
- Stripe generates PDFs, URL should work
- Check if invoice is finalized

**For CHIP:**
- CHIP may not provide PDF URLs
- Check `pdf_url` in purchase record

## Customer Portal Issues

### Portal Not Accessible

**Symptoms:** `/billing` returns 404.

**Solution:**
1. Enable portal in config:
   ```php
   'billing_portal' => [
       'enabled' => true,
   ],
   ```

2. Register the panel provider:
   ```php
   // config/app.php or bootstrap/providers.php
   AIArmada\FilamentCashier\CustomerPortal\BillingPanelProvider::class,
   ```

3. Clear route cache:
   ```bash
   php artisan route:clear
   ```

### Authentication Issues

**Symptoms:** Login redirects incorrectly.

**Solution:**
Check auth guard configuration:
```php
'billing_portal' => [
    'auth_guard' => 'web', // Must match your auth guard
],
```

### Customer Sees Wrong Subscriptions

**Symptoms:** Customer sees other users' data.

**This is a serious security issue!**

**Solution:**
1. Verify `CashierOwnerScope` is applied
2. Check that `user_id` column exists in subscription tables
3. Ensure queries filter by authenticated user:
   ```php
   ->where('user_id', auth()->id())
   ```

## Performance Issues

### Slow Dashboard Loading

**Symptoms:** Dashboard takes several seconds to load.

**Causes & Solutions:**

1. **Too many subscriptions**
   - Widgets use chunking (200 at a time)
   - Consider increasing polling interval:
     ```php
     'tables' => [
         'polling_interval' => '120s',
     ],
     ```

2. **N+1 queries**
   - Widgets use `with('items')` for eager loading
   - Check for additional N+1 issues in custom code

3. **API rate limiting**
   - Stripe has API limits
   - Reduce polling frequency

### Subscription List Slow

**Symptoms:** Subscription list page loads slowly.

**Solutions:**
1. The list uses collection-based pagination, which loads all data
2. Consider filtering by gateway tab to reduce dataset
3. Use "Active" or "Issues" tabs for smaller datasets

## Translation Issues

### Missing Translations

**Symptoms:** Translation keys show instead of text (e.g., `filament-cashier::subscriptions.title`).

**Solution:**
```bash
php artisan vendor:publish --tag=filament-cashier-translations
```

### Wrong Language

**Symptoms:** UI shows wrong language.

**Solution:**
1. Check `config/app.php`:
   ```php
   'locale' => 'en', // or 'ms'
   ```

2. Ensure translation files exist for your locale

## Gateway-Specific Issues

### Stripe Webhook Errors

**Symptoms:** Webhook events fail to process.

**Solution:**
1. Verify webhook secret:
   ```env
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```

2. Check webhook URL in Stripe Dashboard
3. See [cashier webhook docs](../../../cashier/docs/05-webhooks.md)

### CHIP Subscription Renewals Not Working

**Symptoms:** CHIP subscriptions don't auto-renew.

**Solution:**
CHIP doesn't have native subscriptions. Schedule the renewal command:

```php
// app/Console/Kernel.php
$schedule->command('cashier-chip:renew-subscriptions')
    ->hourly()
    ->withoutOverlapping();
```

## Debugging

### Enable Logging

Add to your logging config to capture cashier-related logs:

```php
// config/logging.php
'channels' => [
    'cashier' => [
        'driver' => 'daily',
        'path' => storage_path('logs/cashier.log'),
        'level' => 'debug',
    ],
],
```

### Check Gateway Health

Use the Gateway Management page or check programmatically:

```php
use AIArmada\FilamentCashier\Support\GatewayDetector;

$detector = app(GatewayDetector::class);
$detector->availableGateways();  // Collection of available gateways
$detector->isAvailable('stripe'); // true/false
$detector->isAvailable('chip');   // true/false
```

### Verify Model Configuration

```php
use App\Models\User;

$user = User::first();

// Check Stripe
$user->stripe_id;              // Should have value if using Stripe
$user->stripeId();             // Method should work

// Check CHIP  
$user->chip_id;                // Should have value if using CHIP
```

## Getting Help

If issues persist:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Enable debug mode: `APP_DEBUG=true`
3. Search existing issues in the repository
4. Create a new issue with:
   - PHP version
   - Laravel version
   - Filament version
   - Gateway packages installed
   - Error messages
   - Steps to reproduce
