---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11.0+
- At least one payment gateway package installed (chip, cashier-chip, or cashier)

## Installation via Composer

```bash
composer require aiarmada/checkout
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=checkout-config
```

## Run Migrations

```bash
php artisan migrate
```

This creates the `checkout_sessions` table for persisting checkout state.

## Required Dependencies

The checkout package requires these ecosystem packages:

```bash
# Required packages
composer require aiarmada/commerce-support
composer require aiarmada/cart
composer require aiarmada/orders

# At least one payment gateway (pick one or more)
composer require aiarmada/chip
# or
composer require aiarmada/cashier-chip
# or
composer require aiarmada/cashier
```

## Optional Dependencies

For enhanced functionality, install these optional packages:

```bash
# Inventory management
composer require aiarmada/inventory

# Tax calculation
composer require aiarmada/tax

# Promotions and discounts
composer require aiarmada/promotions

# Voucher/coupon support
composer require aiarmada/vouchers
```

## Custom Order Model (Non-Orders Integration)

If you are not using `aiarmada/orders` but still want to use checkout, bind your
own implementation of `OrderServiceInterface` and point checkout to your model:

```php
use AIArmada\Orders\Contracts\OrderServiceInterface;

public function register(): void
{
    $this->app->singleton(OrderServiceInterface::class, App\Checkout\OrderService::class);
}
```

```php
// config/checkout.php
'models' => [
    'order' => App\Models\Order::class,
],
```

Your `OrderServiceInterface` implementation should return your custom order
model and handle item/address creation in `createOrder()`.

## Service Provider

The package auto-discovers its service provider. If needed, manually register:

```php
// config/app.php
'providers' => [
    // ...
    AIArmada\Checkout\CheckoutServiceProvider::class,
],
```

## Facade Alias

The `Checkout` facade is auto-registered. For manual setup:

```php
// config/app.php
'aliases' => [
    // ...
    'Checkout' => AIArmada\Checkout\Facades\Checkout::class,
],
```

## Multi-tenancy Setup

If using owner-scoping, ensure the `OwnerResolverInterface` is bound:

```php
// AppServiceProvider.php
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;

public function register(): void
{
    $this->app->singleton(OwnerResolverInterface::class, YourOwnerResolver::class);
}
```

And enable in config:

```php
// config/checkout.php
'owner' => [
    'enabled' => true,
],
```

## Verifying Installation

```bash
php artisan checkout:status
```

Or via Tinker:

```php
// Check service binding
app(\AIArmada\Checkout\Contracts\CheckoutServiceInterface::class);

// Check payment gateways
app(\AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface::class)->available();
```
