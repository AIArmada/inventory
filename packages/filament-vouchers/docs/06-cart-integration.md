---
title: Cart Integration
---

# Cart Integration

Integrating vouchers with Filament Cart for seamless discount application.

## Prerequisites

Install both packages:

```bash
composer require aiarmada/filament-cart aiarmada/filament-vouchers
```

When both packages are installed, Filament Vouchers automatically enables cart integration features.

## Available Actions

Use `CartVoucherActions` to add voucher functionality to cart pages:

```php
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;
```

### Apply Voucher

Opens a modal form to enter and apply a voucher code:

```php
CartVoucherActions::applyVoucher()
```

Features:
- Text input for voucher code
- Validation and error handling
- Success/failure notifications
- Automatic cart refresh

### Show Applied Vouchers

Displays currently applied vouchers in a modal:

```php
CartVoucherActions::showAppliedVouchers()
```

Features:
- Lists all applied voucher codes
- Shows voucher type (percentage, fixed, etc.)
- Empty state when no vouchers applied

### Remove Voucher

Creates an action to remove a specific voucher:

```php
CartVoucherActions::removeVoucher('SUMMER2024')
```

Features:
- Confirmation dialog
- Success notification
- Automatic cart refresh

## Usage in Cart Resource

### View Cart Page

```php
// app/Filament/Resources/CartResource/Pages/ViewCart.php
namespace App\Filament\Resources\CartResource\Pages;

use AIArmada\FilamentCart\Resources\CartResource\Pages\ViewCart as BaseViewCart;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;

class ViewCart extends BaseViewCart
{
    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [
            CartVoucherActions::applyVoucher(),
            CartVoucherActions::showAppliedVouchers(),
        ]);
    }
}
```

### Edit Cart Page

```php
// app/Filament/Resources/CartResource/Pages/EditCart.php
namespace App\Filament\Resources\CartResource\Pages;

use AIArmada\FilamentCart\Resources\CartResource\Pages\EditCart as BaseEditCart;
use AIArmada\FilamentVouchers\Extensions\CartVoucherActions;

class EditCart extends BaseEditCart
{
    protected function getHeaderActions(): array
    {
        return [
            CartVoucherActions::applyVoucher(),
            ...parent::getHeaderActions(),
        ];
    }
}
```

## Cart Widgets

Add voucher widgets to cart detail pages. See [Widgets](05-widgets.md) for full documentation.

### Applied Voucher Badges

```php
use AIArmada\FilamentVouchers\Widgets\AppliedVoucherBadgesWidget;

protected function getHeaderWidgets(): array
{
    return [
        AppliedVoucherBadgesWidget::class,
    ];
}
```

### Quick Apply Widget

```php
use AIArmada\FilamentVouchers\Widgets\QuickApplyVoucherWidget;

protected function getFooterWidgets(): array
{
    return [
        QuickApplyVoucherWidget::class,
    ];
}
```

### Voucher Suggestions

```php
use AIArmada\FilamentVouchers\Widgets\VoucherSuggestionsWidget;

protected function getFooterWidgets(): array
{
    return [
        VoucherSuggestionsWidget::class,
    ];
}
```

## Error Handling

The integration handles common voucher errors gracefully:

| Error | User Message |
|-------|-------------|
| Invalid code | "Voucher Application Failed" with specific reason |
| Expired voucher | Shows expiration message |
| Usage limit reached | Shows limit exceeded message |
| Minimum not met | Shows minimum requirement |

All errors are logged for debugging:

```php
Log::warning('Voucher application failed', [
    'code' => $code,
    'cart_id' => $record->id,
    'error' => $exception->getMessage(),
]);
```

## Deep Linking

Voucher usage records automatically link to cart detail pages when `aiarmada/filament-cart` is installed. Click a cart reference in the usage table to view the full cart.

## Bridge Service

The `FilamentCartBridge` service provides comprehensive integration between vouchers and carts:

```php
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;

$bridge = app(FilamentCartBridge::class);

// Check if cart package is available
if ($bridge->isAvailable()) {
    // Integration features enabled
}
```

### Available Methods

| Method | Description |
|--------|-------------|
| `isAvailable()` | Check if `aiarmada/filament-cart` is installed |
| `isWarmed()` | Check if integration hooks are ready |
| `warm()` | Initialize integration (called automatically) |
| `getCartModel()` | Get the Cart model class name |
| `getCartResource()` | Get the Filament Cart resource class |
| `resolveCartUrl($cartId)` | Generate URL to a specific cart |
| `findCart($cartId)` | Find a cart by ID with owner scoping |
| `getCartInstance($cart)` | Get a `CartInstanceManager` for the cart |
| `getAppliedVouchers($cart)` | Get collection of applied voucher codes |
| `applyVoucher($cart, $code)` | Apply a voucher code to a cart |
| `removeVoucher($cart, $code)` | Remove a voucher from a cart |
| `hasVoucher($cart, $code)` | Check if voucher is applied to cart |
| `countCartsWithVoucher($code)` | Count active carts using a voucher |
| `getVoucherCartStats()` | Get aggregate statistics for dashboards |

### Example: Cart Lookup

```php
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;

$bridge = app(FilamentCartBridge::class);

// Find a cart (respects owner scoping)
$cart = $bridge->findCart($cartId);

if ($cart) {
    // Get applied vouchers
    $vouchers = $bridge->getAppliedVouchers($cart);
    
    // Apply a new voucher
    if (!$bridge->hasVoucher($cart, 'SUMMER2024')) {
        try {
            $bridge->applyVoucher($cart, 'SUMMER2024');
        } catch (VoucherApplicationException $e) {
            // Handle error
        }
    }
}
```

### Example: Dashboard Statistics

```php
$stats = $bridge->getVoucherCartStats();

// Returns:
// [
//     'active_carts_with_vouchers' => 42,
//     'total_potential_discount' => 125000, // cents
// ]
```

The bridge is warmed automatically during Filament serving, ensuring integration hooks are ready before rendering.

## Owner Scoping

When owner scoping is enabled, the cart integration respects tenant boundaries:

- Vouchers can only be applied to carts belonging to the same owner
- Cross-tenant cart access is blocked
- Global vouchers can be applied based on `include_global` setting
