---
title: Installation
---

# Installation

## Requirements

- PHP 8.2+
- Laravel 11+
- CHIP Account with API credentials

## Installing the Package

```bash
composer require aiarmada/cashier-chip
```

## Configuration

### Publish Configuration

```bash
php artisan vendor:publish --tag=cashier-chip-config
```

This creates `config/cashier-chip.php`:

```php
return [
    // URL path prefix for webhooks
    'path' => env('CASHIER_CHIP_PATH', 'chip'),
    
    // Default currency (CHIP supports MYR)
    'currency' => env('CASHIER_CURRENCY', 'MYR'),
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'ms_MY'),
    
    // Webhook configuration
    'webhooks' => [
        'secret' => env('CHIP_WEBHOOK_SECRET'),
        'verify_signature' => true,
    ],
    
    // Default redirect URLs
    'success_url' => env('CASHIER_CHIP_SUCCESS_URL'),
    'cancel_url' => env('CASHIER_CHIP_CANCEL_URL'),
    
    // Table names
    'tables' => [
        'customers' => 'chip_customers',
        'subscriptions' => 'chip_subscriptions',
        'subscription_items' => 'chip_subscription_items',
    ],
];
```

### Environment Variables

Add to your `.env` file:

```env
# CHIP API Credentials
CHIP_BRAND_ID=your-brand-id
CHIP_SECRET_KEY=your-secret-key
CHIP_WEBHOOK_SECRET=your-webhook-secret

# Optional: Default URLs
CASHIER_CHIP_SUCCESS_URL=https://example.com/checkout/success
CASHIER_CHIP_CANCEL_URL=https://example.com/checkout/cancel
```

### Run Migrations

```bash
php artisan vendor:publish --tag=cashier-chip-migrations
php artisan migrate
```

This creates the following tables:

- `chip_customers` - Links users to CHIP client IDs
- `chip_subscriptions` - Subscription records
- `chip_subscription_items` - Subscription line items

## Billable Model

Add the `Billable` trait to your User model:

```php
<?php

namespace App\Models;

use AIArmada\CashierChip\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable;
}
```

The `Billable` trait provides:

- Customer management (CHIP client creation)
- Payment method handling (recurring tokens)
- Checkout sessions
- Subscription management
- One-off charges

## Custom Customer Model

If you're using a different model for billing:

```php
// In AppServiceProvider::boot()
use AIArmada\CashierChip\CashierChip;

CashierChip::useCustomerModel(Team::class);
```

## Webhook Route

The package automatically registers a webhook route at:

```
POST /chip/webhook
```

Configure your CHIP dashboard to send webhooks to this URL.

### CSRF Protection

Exclude the webhook route from CSRF verification in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'chip/*',
    ]);
})
```

## Next Steps

- [Customer Management](customers.md) - Create and manage CHIP customers
- [One-off Charges](charges.md) - Process single payments
- [Checkout Sessions](checkout.md) - Redirect to hosted checkout
