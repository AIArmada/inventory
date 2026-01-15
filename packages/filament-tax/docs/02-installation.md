---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5.0+
- `aiarmada/tax` package (installed automatically as dependency)

## Install via Composer

```bash
composer require aiarmada/filament-tax
```

This will also install the core `aiarmada/tax` package if not already present.

## Publish Assets

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filament-tax-config
```

Optionally, publish views for customization:

```bash
php artisan vendor:publish --tag=filament-tax-views
```

## Register the Plugin

Add the plugin to your Filament panel provider:

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentTax\FilamentTaxPlugin;
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
            ->plugins([
                FilamentTaxPlugin::make(),
            ]);
    }
}
```

## Base Tax Package Setup

Ensure the base tax package is configured. Publish and run migrations:

```bash
php artisan vendor:publish --tag=tax-migrations
php artisan vendor:publish --tag=tax-settings
php artisan migrate
```

Publish the core tax config if not already done:

```bash
php artisan vendor:publish --tag=tax-config
```

## Verify Installation

After installation, you should see:

1. **Navigation** — Tax menu group in the sidebar with:
   - Tax Zones
   - Tax Classes
   - Tax Rates
   - Tax Exemptions

2. **Settings** — Tax Settings page (if enabled)

3. **Widgets** — Dashboard widgets (if enabled):
   - Tax Stats overview
   - Expiring Exemptions table
   - Zone Coverage visualization

## Initial Data

Create a default zone and rate to get started:

```php
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxClass;

// Create a default zone
$zone = TaxZone::create([
    'name' => 'Malaysia',
    'code' => 'MY',
    'countries' => ['MY'],
    'is_active' => true,
    'is_default' => true,
    'priority' => 10,
]);

// Create default tax class
$class = TaxClass::create([
    'name' => 'Standard',
    'slug' => 'standard',
    'description' => 'Standard taxable goods',
    'is_default' => true,
    'is_active' => true,
]);

// Create SST rate
TaxRate::create([
    'zone_id' => $zone->id,
    'name' => 'SST 6%',
    'tax_class' => 'standard',
    'rate' => 600, // 6.00% in basis points
    'is_compound' => false,
    'is_shipping' => true,
    'is_active' => true,
    'priority' => 10,
]);
```

Or use factories in development:

```php
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxClass;

TaxZone::factory()->forMalaysia()->default()->create();
TaxClass::factory()->standard()->create();
TaxRate::factory()->forZone(TaxZone::first())->sst()->create();
```

## Multiple Panels

Register the plugin on each panel that needs tax management:

```php
// AdminPanelProvider
FilamentTaxPlugin::make()
    ->zones(true)
    ->classes(true)
    ->rates(true)
    ->exemptions(true)
    ->widgets(true)
    ->settings(true);

// StaffPanelProvider - read-only access
FilamentTaxPlugin::make()
    ->zones(true)
    ->classes(true)
    ->rates(true)
    ->exemptions(true)
    ->widgets(true)
    ->settings(false); // No settings access
```

## Authorization Setup

### With filament-authz (Recommended)

If you have `aiarmada/filament-authz` installed, authorization is automatic:

```php
// Permissions checked automatically:
// tax.zones.view, tax.zones.create, tax.zones.update, tax.zones.delete
// tax.classes.view, tax.classes.create, ...
// tax.rates.view, tax.rates.create, ...
// tax.exemptions.view, tax.exemptions.create, ...
// tax.settings.view, tax.settings.update
```

### Without filament-authz

The plugin uses Laravel's standard policies. Create policies for each model:

```bash
php artisan make:policy TaxZonePolicy --model=TaxZone
php artisan make:policy TaxClassPolicy --model=TaxClass
php artisan make:policy TaxRatePolicy --model=TaxRate
php artisan make:policy TaxExemptionPolicy --model=TaxExemption
```

Example policy:

```php
<?php

namespace App\Policies;

use AIArmada\Tax\Models\TaxZone;
use App\Models\User;

class TaxZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tax.zones.view');
    }

    public function view(User $user, TaxZone $zone): bool
    {
        return $user->hasPermission('tax.zones.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tax.zones.create');
    }

    public function update(User $user, TaxZone $zone): bool
    {
        return $user->hasPermission('tax.zones.update');
    }

    public function delete(User $user, TaxZone $zone): bool
    {
        return $user->hasPermission('tax.zones.delete');
    }
}
```

## Troubleshooting Installation

### Resources Not Appearing

1. Verify plugin is registered in the panel
2. Check that features are enabled (zones, classes, rates, exemptions)
3. Confirm user has view permissions

### Widgets Not Showing

1. Ensure `widgets(true)` is set on the plugin
2. Widgets appear on the dashboard — navigate to `/admin`
3. Check widget authorization

### Settings Page Missing

1. Enable via `settings(true)` on plugin
2. Ensure TaxSettings class is migrated:
   ```bash
   php artisan vendor:publish --tag=tax-settings
   php artisan migrate
   ```

### Migration Errors

Run migrations for the base tax package:

```bash
php artisan migrate --path=vendor/aiarmada/tax/database/migrations
```
