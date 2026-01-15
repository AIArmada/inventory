---
title: Overview
---

# Filament Vouchers

Filament admin panel plugin for managing vouchers, discounts, and promotional codes.

## Features

Filament Vouchers provides a complete admin interface for:

- **Voucher Management** – Create, edit, and monitor discount vouchers
- **Usage Tracking** – Track voucher redemptions with detailed history
- **Wallet System** – Allow users to save vouchers for later use
- **Cart Integration** – Seamless integration with Filament Cart package
- **Multi-tenant Support** – Owner-scoped resources for marketplace scenarios
- **Targeting Configuration** – Define preset targeting rules for vouchers
- **Stacking Rules** – Configure how vouchers combine with each other

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament v5
- aiarmada/vouchers (core package)

## Package Architecture

```
filament-vouchers/
├── Resources/
│   ├── VoucherResource        # Main voucher CRUD
│   ├── VoucherUsageResource   # Usage tracking
│   └── VoucherWalletResource  # Saved vouchers
├── Pages/
│   ├── StackingConfigurationPage   # Stacking rule config
│   └── TargetingConfigurationPage  # Targeting presets
├── Widgets/
│   ├── VoucherStatsWidget          # Overview stats
│   ├── RedemptionTrendChart        # Usage trends
│   └── (Cart integration widgets)
└── Actions/
    ├── ActivateVoucherAction
    ├── PauseVoucherAction
    └── BulkGenerateVouchersAction
```

## Quick Start

1. Install the package:

```bash
composer require aiarmada/filament-vouchers
```

2. Register the plugin in your panel:

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

3. Run migrations (from core vouchers package):

```bash
php artisan migrate
```

4. Access the voucher resources in your Filament admin panel.
