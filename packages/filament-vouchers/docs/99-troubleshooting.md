---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for Filament Vouchers.

## Installation Issues

### Plugin not appearing in panel

Ensure the plugin is registered in your panel provider:

```php
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentVouchersPlugin::make(),
        ]);
}
```

Clear caches after registration:

```bash
php artisan config:clear
php artisan view:clear
php artisan filament:clear-cached-components
```

### Migration errors

Ensure the core vouchers package migrations have run:

```bash
php artisan migrate:status | grep voucher
```

If migrations are pending:

```bash
php artisan migrate
```

## Multi-tenant Issues

### Vouchers not filtering by owner

Check that owner scoping is enabled:

```php
// config/vouchers.php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Include global vouchers?
],
```

Verify the `OwnerResolverInterface` is bound:

```php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

app()->bind(OwnerResolverInterface::class, YourOwnerResolver::class);
```

### Cross-tenant data visible

Resources use `OwnerScopedQueries::forModel()` for scoping. Ensure:

1. Owner columns (`owner_type`, `owner_id`) are populated
2. The resolver returns the correct owner
3. Global scope is not being bypassed

## Cart Integration Issues

### Cart widgets not appearing

Check that `aiarmada/filament-cart` is installed:

```bash
composer show aiarmada/filament-cart
```

The widgets require a cart record to be set:

```php
$widget->record = $cart;
```

### Voucher application failing silently

Enable debug logging to see errors:

```php
Log::channel('daily')->debug('Voucher action', [
    'code' => $code,
    'cart' => $cart->toArray(),
]);
```

Check for `VoucherException` being caught:

```php
try {
    $cartInstance->applyVoucher($code);
} catch (VoucherException $e) {
    // Check this path
    Log::warning('Voucher failed', ['error' => $e->getMessage()]);
}
```

## Widget Issues

### Stats showing zero values

Check that the `VoucherStatsAggregator` can query data:

```php
use AIArmada\FilamentVouchers\Services\VoucherStatsAggregator;

$aggregator = app(VoucherStatsAggregator::class);
$stats = $aggregator->getOverview();
dd($stats);
```

### Chart not rendering

Ensure Filament Chart widget dependencies are loaded:

```bash
npm run build
```

Check browser console for JavaScript errors.

## Form Issues

### Value field showing wrong format

The form uses cents/basis points internally:

- Fixed vouchers: value in cents (1000 = $10.00)
- Percentage vouchers: value in basis points (1000 = 10.00%)

The form converts display values automatically. If seeing raw values, check the `formatStateUsing` callback.

### Targeting configuration not saving

Ensure the DSL format is valid:

```php
// Valid DSL format
'cart@cart_subtotal/aggregate'

// Invalid formats will throw ValidationException
```

## Performance Issues

### Slow table loading

Reduce polling interval or disable:

```php
// config/filament-vouchers.php
'polling_interval' => null, // Disable polling
```

Add indexes to frequently queried columns:

```php
Schema::table('vouchers', function (Blueprint $table) {
    $table->index(['owner_type', 'owner_id']);
    $table->index('status');
});
```

### High memory usage

For large voucher counts, consider:

1. Pagination limits
2. Deferred widget loading
3. Query optimization

## Debugging

### Enable query logging

```php
DB::enableQueryLog();

// Perform operations

dd(DB::getQueryLog());
```

### Check model events

```php
Voucher::observe(new class {
    public function retrieved(Voucher $voucher): void
    {
        Log::debug('Voucher retrieved', ['id' => $voucher->id]);
    }
});
```

## Getting Help

If issues persist:

1. Check the [core vouchers documentation](../../vouchers/docs/)
2. Review the package source code
3. Open an issue with:
   - PHP and Laravel versions
   - Filament version
   - Steps to reproduce
   - Error messages/stack traces
