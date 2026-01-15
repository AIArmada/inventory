---
title: Overview
---

# Filament Tax Plugin

The Filament Tax plugin provides a complete admin interface for managing tax configuration in Laravel applications using [Filament](https://filamentphp.com).

## Features

- **Tax Zone Management** — Create and manage geographic tax zones with country, state, and postcode targeting
- **Tax Class Resources** — Define product categorization for different tax treatments
- **Tax Rate Configuration** — Set up tax rates with support for compound taxes and priority ordering
- **Tax Exemption Workflow** — Approve, reject, and track customer tax exemptions
- **Settings Page** — Configure global tax behavior without code changes
- **Dashboard Widgets** — At-a-glance tax statistics and expiring exemption alerts
- **Zone Coverage Overview** — Visual representation of all zones and their rates
- **Authorization Integration** — Seamless integration with `filament-authz` when available
- **Activity Logging** — Track all changes via Spatie Activity Log integration

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5.0+
- `aiarmada/tax` package

## Quick Start

```bash
composer require aiarmada/filament-tax
```

Register the plugin in your panel:

```php
use AIArmada\FilamentTax\FilamentTaxPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentTaxPlugin::make(),
        ]);
}
```

## Resources

The plugin provides four Filament resources:

| Resource | Description |
|----------|-------------|
| `TaxZoneResource` | Geographic zones with country/state/postcode targeting |
| `TaxClassResource` | Product categorization (standard, reduced, zero-rated) |
| `TaxRateResource` | Tax percentages linked to zones and classes |
| `TaxExemptionResource` | Customer exemptions with approval workflow |

## Widgets

| Widget | Description |
|--------|-------------|
| `TaxStatsWidget` | Counts of zones, rates, classes, and exemptions |
| `ExpiringExemptionsWidget` | Table of exemptions expiring within 30 days |
| `ZoneCoverageWidget` | Visual overview of all zones and their rate configurations |

## Architecture

```
filament-tax/
├── src/
│   ├── Actions/                    # Custom Filament actions
│   │   └── DownloadTaxExemptionCertificateAction.php
│   ├── Pages/
│   │   └── ManageTaxSettings.php   # Settings page
│   ├── Plugin/
│   │   └── FilamentTaxPlugin.php   # Main plugin class
│   ├── Resources/
│   │   ├── TaxZoneResource/
│   │   │   ├── Pages/             # List, Create, Edit, View
│   │   │   ├── RelationManagers/ # RatesRelationManager
│   │   │   ├── Schemas/          # Form schema
│   │   │   └── Tables/           # Table schema
│   │   ├── TaxClassResource/
│   │   ├── TaxRateResource/
│   │   └── TaxExemptionResource/
│   ├── Support/
│   │   └── FilamentTaxAuthz.php   # Authorization helper
│   ├── Widgets/
│   │   ├── TaxStatsWidget.php
│   │   ├── ExpiringExemptionsWidget.php
│   │   └── ZoneCoverageWidget.php
│   └── FilamentTaxServiceProvider.php
├── config/
│   └── filament-tax.php
└── resources/
    └── views/
        ├── pages/
        │   └── manage-tax-settings.blade.php
        └── widgets/
            └── zone-coverage.blade.php
```

## Feature Toggles

Control which features are available via the plugin:

```php
FilamentTaxPlugin::make()
    ->zones(true)       // Enable zone management
    ->classes(true)     // Enable class management
    ->rates(true)       // Enable rate management
    ->exemptions(true)  // Enable exemption management
    ->widgets(true)     // Enable dashboard widgets
    ->settings(true);   // Enable settings page
```

## Filament Version

This plugin is built for **Filament 5.0** which uses Livewire 4. The API is compatible with Filament v4, so v4 documentation examples work with minor adjustments.

## Related Packages

- [`aiarmada/tax`](../tax/01-overview.md) — Core tax calculation engine (required)
- [`aiarmada/commerce-support`](../commerce-support/01-overview.md) — Shared utilities for multi-tenancy
- [`aiarmada/filament-authz`](../filament-authz/01-overview.md) — Authorization layer (optional)
