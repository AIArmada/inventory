---
title: Widgets
---

# Widgets

Dashboard widgets for monitoring voucher activity.

## Dashboard Widgets

### VoucherStatsWidget

Overview statistics for all vouchers:

- **Total Vouchers** – all vouchers in the system
- **Active Vouchers** – currently redeemable
- **Upcoming Launches** – scheduled to activate
- **Manual Redemptions** – processed by staff
- **Discount Granted** – total value redeemed

```php
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;

// Automatically registered by the plugin
// Or add to a custom dashboard:
public function getWidgets(): array
{
    return [
        VoucherStatsWidget::class,
    ];
}
```

### RedemptionTrendChart

Chart showing voucher redemption trends over time:

```php
use AIArmada\FilamentVouchers\Widgets\RedemptionTrendChart;

public function getWidgets(): array
{
    return [
        RedemptionTrendChart::class,
    ];
}
```

Features:
- 7-day, 30-day, 90-day filter options
- Owner-scoped when multi-tenancy is enabled
- Uses Filament ChartWidget

## Record-Specific Widgets

### VoucherUsageTimelineWidget

Visual timeline of voucher redemptions. Use on voucher detail pages:

```php
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;

protected function getFooterWidgets(): array
{
    return [
        VoucherUsageTimelineWidget::class,
    ];
}
```

### VoucherWalletStatsWidget

Statistics for wallet-based voucher entries:

```php
use AIArmada\FilamentVouchers\Widgets\VoucherWalletStatsWidget;
```

### VoucherCartStatsWidget

Cart-specific voucher metrics:

```php
use AIArmada\FilamentVouchers\Widgets\VoucherCartStatsWidget;
```

## Cart Integration Widgets

These widgets require `aiarmada/filament-cart` to be installed.

### AppliedVoucherBadgesWidget

Displays badges for vouchers applied to a cart:

```php
use AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget;

protected function getHeaderWidgets(): array
{
    return [
        AppliedVoucherBadgesWidget::class,
    ];
}
```

Features:
- Color-coded status badges
- Remove voucher action
- Graceful fallback when cart package unavailable

### QuickApplyVoucherWidget

Inline form for applying a voucher code:

```php
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;

protected function getFooterWidgets(): array
{
    return [
        QuickApplyVoucherWidget::class,
    ];
}
```

Features:
- Text input with apply button
- Validation and error handling
- Success notifications

### VoucherSuggestionsWidget

Smart suggestions for applicable vouchers based on cart contents:

```php
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;

protected function getFooterWidgets(): array
{
    return [
        VoucherSuggestionsWidget::class,
    ];
}
```

Features:
- Calculates potential savings
- Shows expiration warnings
- Displays remaining uses
- One-click application

### AppliedVouchersWidget

Table of currently applied vouchers:

```php
use AIArmada\FilamentVouchers\Widgets\AppliedVouchersWidget;
```

## Adding Widgets to Pages

### Dashboard

Register widgets in your panel provider:

```php
use AIArmada\FilamentVouchers\Widgets\VoucherStatsWidget;
use AIArmada\FilamentVouchers\Widgets\RedemptionTrendChart;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            VoucherStatsWidget::class,
            RedemptionTrendChart::class,
        ]);
}
```

### Resource Pages

Add widgets to resource page headers or footers:

```php
protected function getHeaderWidgets(): array
{
    return [
        VoucherStatsWidget::class,
    ];
}

protected function getFooterWidgets(): array
{
    return [
        VoucherUsageTimelineWidget::class,
    ];
}
```

## Currency Formatting

Monetary values use the configured default currency:

```php
// config/filament-vouchers.php
'default_currency' => 'MYR',
```

Widgets use `Akaunting\Money\Money` for proper currency formatting.

## Owner Scoping

All widgets respect owner scoping when `vouchers.owner.enabled` is `true`. They will only display data belonging to the resolved owner.
