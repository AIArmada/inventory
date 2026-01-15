---
title: Installation
---

# Installation

## Requirements

- PHP ^8.4
- Laravel ^12.0
- Filament ^5.0
- [aiarmada/chip](../../chip) (automatically installed as dependency)

## Install Package

```bash
composer require aiarmada/filament-chip
```

This will also install `aiarmada/chip` if not already present.

## Register Plugin

Add the plugin to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentChip\FilamentChipPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilamentChipPlugin::make())
            // ... other configuration
            ;
    }
}
```

## Publish Configuration

```bash
php artisan vendor:publish --tag="filament-chip-config"
```

This creates `config/filament-chip.php` with customizable options.

## Publish Migrations (if needed)

The core `chip` package handles migrations. Run them if you haven't:

```bash
php artisan migrate
```

## Configure CHIP Credentials

Ensure your `.env` has the CHIP API credentials:

```env
# CHIP Collect (Payments)
CHIP_ENVIRONMENT=sandbox
CHIP_BRAND_ID=your-brand-uuid
CHIP_COLLECT_API_KEY=your-collect-api-key

# CHIP Send (Payouts) - optional
CHIP_SEND_API_KEY=your-send-api-key
CHIP_SEND_API_SECRET=your-send-api-secret

# Webhooks
CHIP_COMPANY_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
```

## Verify Installation

Navigate to your Filament admin panel. You should see:
- "Payments" navigation group with Purchase, Payment, Client resources
- Analytics dashboard page
- CHIP stats widgets (if using dashboard widgets)

## Optional: Billing Portal

For customer self-service billing, install `cashier-chip`:

```bash
composer require aiarmada/cashier-chip
```

Register the billing panel provider:

```php
// bootstrap/providers.php (Laravel 12)
return [
    // ...
    AIArmada\FilamentChip\BillingPanelProvider::class,
];
```

Configure your billable model:

```php
// config/filament-chip.php
'billable' => [
    'model' => App\Models\User::class,
    'billing_portal' => [
        'path' => 'billing',
    ],
],
```

Add the `Billable` trait to your User model:

```php
<?php

namespace App\Models;

use AIArmada\CashierChip\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Billable;
    
    // ...
}
```

## Multi-Panel Setup

Register the plugin on multiple panels:

```php
// AdminPanelProvider
$panel->plugin(
    FilamentChipPlugin::make()
        ->navigationGroup('Payments')
);

// TenantPanelProvider
$panel->plugin(
    FilamentChipPlugin::make()
        ->navigationGroup('Billing')
        ->resources([
            // Subset of resources for tenant panel
            PurchaseResource::class,
        ])
);
```
