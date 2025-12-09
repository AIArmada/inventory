# AIArmada Commerce

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aiarmada/commerce.svg?style=flat-square)](https://packagist.org/packages/aiarmada/commerce)
[![Total Downloads](https://img.shields.io/packagist/dt/aiarmada/commerce.svg?style=flat-square)](https://packagist.org/packages/aiarmada/commerce)

A powerful collection of commerce components for Laravel - build and ship e-commerce features fast.

## Overview

AIArmada Commerce is a modular e-commerce toolkit for Laravel applications. Install the complete suite or pick individual packages based on your needs.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.4+ |
| Laravel | 12.0+ |
| Filament | 5.0+ |

## Installation

Install the complete commerce suite:

```bash
composer require aiarmada/commerce
```

Or install individual packages as needed (see below).

## Included Packages

### Core Packages

| Package | Description |
|---------|-------------|
| [commerce-support](../commerce-support) | Core utilities, contracts, and exceptions |
| [cart](../cart) | Shopping cart with conditions and persistence |
| [stock](../stock) | Inventory and stock management |
| [vouchers](../vouchers) | Discount codes and promotional vouchers |
| [docs](../docs) | Invoice and receipt generation with PDF |

### Payment & Shipping

| Package | Description |
|---------|-------------|
| [chip](../chip) | CHIP payment gateway integration |
| [cashier](../cashier) | Payment orchestration layer |
| [cashier-chip](../cashier-chip) | CHIP adapter for Cashier |
| [jnt](../jnt) | J&T Express shipping integration |

### Filament Admin

| Package | Description |
|---------|-------------|
| [filament-cart](../filament-cart) | Cart management admin panel |
| [filament-stock](../filament-stock) | Stock management admin panel |
| [filament-vouchers](../filament-vouchers) | Voucher management admin panel |
| [filament-docs](../filament-docs) | Document management admin panel |
| [filament-chip](../filament-chip) | CHIP payment admin panel |
| [filament-jnt](../filament-jnt) | J&T Express admin panel |
| [filament-authz](../filament-authz) | Role & permission management |

## Quick Start

### 1. Install the Suite

```bash
composer require aiarmada/commerce
```

### 2. Publish Configurations

```bash
php artisan vendor:publish --tag=cart-config
php artisan vendor:publish --tag=vouchers-config
php artisan vendor:publish --tag=chip-config
# ... etc
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Register Filament Plugins

```php
use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use AIArmada\FilamentDocs\FilamentDocsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCartPlugin::make(),
            FilamentVouchersPlugin::make(),
            FilamentDocsPlugin::make(),
            // ... other plugins
        ]);
}
```

### 5. Configure Payment Gateway

```bash
php artisan commerce:setup
```

## Documentation

See the [docs](docs/) folder for detailed documentation:

- [Installation](docs/01-installation.md) - Complete setup guide
- [Packages](docs/02-packages.md) - Package overview and selection
- [Configuration](docs/03-configuration.md) - Environment and config options

## Individual Package Installation

Install only what you need:

```bash
# Cart functionality
composer require aiarmada/cart aiarmada/filament-cart

# Vouchers/discounts
composer require aiarmada/vouchers aiarmada/filament-vouchers

# Payment processing
composer require aiarmada/chip aiarmada/cashier aiarmada/cashier-chip

# Document generation
composer require aiarmada/docs aiarmada/filament-docs

# Shipping
composer require aiarmada/jnt aiarmada/filament-jnt
```

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
