---
title: Configuration
---

# Configuration

## Configuration File

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-tax-config
```

This creates `config/filament-tax.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Tax',
        'sort' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'polling' => false,
        'date_format' => 'M j, Y',
        'datetime_format' => 'M j, Y g:i A',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'zones' => true,
        'classes' => true,
        'rates' => true,
        'exemptions' => true,
        'widgets' => true,
        'settings' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    */
    'resources' => [
        'zones' => [
            'navigation_icon' => 'heroicon-o-globe-alt',
            'navigation_sort' => 1,
        ],
        'classes' => [
            'navigation_icon' => 'heroicon-o-tag',
            'navigation_sort' => 2,
        ],
        'rates' => [
            'navigation_icon' => 'heroicon-o-calculator',
            'navigation_sort' => 3,
        ],
        'exemptions' => [
            'navigation_icon' => 'heroicon-o-shield-check',
            'navigation_sort' => 4,
        ],
    ],
];
```

## Plugin Configuration

The plugin supports fluent configuration in your panel provider:

```php
use AIArmada\FilamentTax\FilamentTaxPlugin;

FilamentTaxPlugin::make()
    // Navigation
    ->navigationGroup('Tax Management')
    ->navigationSort(50)
    
    // Features
    ->zones(true)
    ->classes(true)
    ->rates(true)
    ->exemptions(true)
    ->widgets(true)
    ->settings(true);
```

### Feature Toggles

#### `->zones(bool $enabled)`

Enable/disable the Tax Zones resource.

```php
FilamentTaxPlugin::make()->zones(true);
```

#### `->classes(bool $enabled)`

Enable/disable the Tax Classes resource.

```php
FilamentTaxPlugin::make()->classes(true);
```

#### `->rates(bool $enabled)`

Enable/disable the Tax Rates resource.

```php
FilamentTaxPlugin::make()->rates(true);
```

#### `->exemptions(bool $enabled)`

Enable/disable the Tax Exemptions resource.

```php
FilamentTaxPlugin::make()->exemptions(true);
```

#### `->widgets(bool $enabled)`

Enable/disable dashboard widgets:
- TaxStatsWidget
- ExpiringExemptionsWidget  
- ZoneCoverageWidget

```php
FilamentTaxPlugin::make()->widgets(true);
```

#### `->settings(bool $enabled)`

Enable/disable the Tax Settings page.

```php
FilamentTaxPlugin::make()->settings(true);
```

### Navigation

#### `->navigationGroup(?string $group)`

Set the navigation group for all tax resources.

```php
FilamentTaxPlugin::make()->navigationGroup('Store Settings');
```

Set to `null` to show resources at root level:

```php
FilamentTaxPlugin::make()->navigationGroup(null);
```

#### `->navigationSort(int $sort)`

Control the position of the Tax group in navigation.

```php
FilamentTaxPlugin::make()->navigationSort(100);
```

## Panel-Specific Configuration

Configure the plugin differently per panel:

```php
// Full admin access
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentTaxPlugin::make()
                    ->zones(true)
                    ->classes(true)
                    ->rates(true)
                    ->exemptions(true)
                    ->widgets(true)
                    ->settings(true),
            ]);
    }
}

// Staff panel - no settings access
class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentTaxPlugin::make()
                    ->zones(true)
                    ->classes(true)
                    ->rates(true)
                    ->exemptions(true)
                    ->widgets(true)
                    ->settings(false), // No settings
            ]);
    }
}

// Customer panel - exemptions only
class CustomerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                FilamentTaxPlugin::make()
                    ->zones(false)
                    ->classes(false)
                    ->rates(false)
                    ->exemptions(true) // Only exemptions
                    ->widgets(false)
                    ->settings(false),
            ]);
    }
}
```

## Table Configuration

### Polling

Enable automatic table refresh:

```php
// config/filament-tax.php
'tables' => [
    'polling' => '30s', // Refresh every 30 seconds
],
```

Or disable polling:

```php
'tables' => [
    'polling' => false,
],
```

### Date Formats

Customize date display format:

```php
'tables' => [
    'date_format' => 'Y-m-d',        // 2024-01-15
    'datetime_format' => 'Y-m-d H:i', // 2024-01-15 14:30
],
```

## Resource Configuration

### Icons

Customize navigation icons per resource:

```php
'resources' => [
    'zones' => [
        'navigation_icon' => 'heroicon-o-map',
    ],
    'classes' => [
        'navigation_icon' => 'heroicon-o-squares-2x2',
    ],
    'rates' => [
        'navigation_icon' => 'heroicon-o-percentage',
    ],
    'exemptions' => [
        'navigation_icon' => 'heroicon-o-document-check',
    ],
],
```

### Sort Order

Control resource ordering within the Tax group:

```php
'resources' => [
    'zones' => [
        'navigation_sort' => 1,
    ],
    'classes' => [
        'navigation_sort' => 2,
    ],
    'rates' => [
        'navigation_sort' => 3,
    ],
    'exemptions' => [
        'navigation_sort' => 4,
    ],
],
```

## Authorization Configuration

### With filament-authz

The plugin automatically integrates with `filament-authz` when available:

```php
// FilamentTaxAuthz helper checks authorization
FilamentTaxAuthz::canView('zones');    // tax.zones.view
FilamentTaxAuthz::canCreate('zones');  // tax.zones.create
FilamentTaxAuthz::canUpdate('zones');  // tax.zones.update
FilamentTaxAuthz::canDelete('zones');  // tax.zones.delete
```

### Without filament-authz

Falls back to Laravel policies on the model:

```php
$user->can('viewAny', TaxZone::class);
$user->can('create', TaxZone::class);
$user->can('update', $zone);
$user->can('delete', $zone);
```

## Environment Variables

The plugin doesn't define its own environment variables. Use the base tax package's env vars:

```env
# Base tax package configuration
TAX_ENABLED=true
TAX_DEFAULT_RATE=600
TAX_PRICES_INCLUDE_TAX=false
TAX_OWNER_ENABLED=true
```

## Caching

The plugin doesn't implement its own caching. Consider caching at the application level:

```php
// Cache zone lookups
$zones = Cache::remember('tax-zones', 3600, function () {
    return TaxZone::active()->with('rates')->get();
});
```

## Localization

Override translations by publishing:

```bash
php artisan vendor:publish --tag=filament-tax-translations
```

This creates `lang/vendor/filament-tax/en/` with translation files.
