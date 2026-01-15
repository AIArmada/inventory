---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- MySQL 8.0+, PostgreSQL 14+, or SQLite 3.35+

## Installing via Composer

```bash
composer require aiarmada/cart
```

The package uses Laravel's auto-discovery, so no manual service provider registration is required.

## Publishing Configuration

```bash
php artisan vendor:publish --tag=cart-config
```

This publishes `config/cart.php` with all configuration options.

## Running Migrations

```bash
php artisan migrate
```

This creates the following tables:

1. **carts** - Main cart storage with JSON columns for items, conditions, metadata
2. **conditions** - Reusable condition definitions

## Verify Installation

```php
use AIArmada\Cart\Facades\Cart;

// Add an item
$item = Cart::add(
    id: 'product-001',
    name: 'Test Product',
    price: 9999, // $99.99 in cents
    quantity: 1
);

// Check cart
dump(Cart::total()->format()); // $99.99
```

## Post-Installation Steps

### 1. Configure Currency

Edit `config/cart.php`:

```php
'money' => [
    'default_currency' => 'USD', // or your currency
    'rounding_mode' => 'half_up',
],
```

### 2. Configure Cart Behavior

```php
// What happens when cart becomes empty
'empty_cart_behavior' => 'destroy', // Options: destroy, clear, preserve
```

### 3. Enable Multi-Tenancy (Optional)

If your application is multi-tenant:

```php
'owner' => [
    'enabled' => true,
    'include_global' => false,
],
```

### 4. Configure Login Migration (Optional)

To automatically migrate guest carts on login:

```php
'migration' => [
    'auto_migrate_on_login' => true,
    'merge_strategy' => 'add_quantities', // or: keep_highest_quantity, keep_user_cart, replace_with_guest
],
```

## Database Configuration

### Table Names

Customize table names via environment or config:

```php
'database' => [
    'table' => env('CART_DB_TABLE', 'carts'),
    'conditions_table' => env('CART_CONDITIONS_TABLE', 'conditions'),
],
```

### TTL (Time-To-Live)

Configure cart expiration:

```php
'database' => [
    'ttl' => 60 * 60 * 24 * 30, // 30 days in seconds
    // Set to null for no expiration
],
```

### JSON Column Type

For PostgreSQL with JSONB support:

```php
'database' => [
    'json_column_type' => 'jsonb', // Uses GIN indexes for better performance
],
```

## Development Setup

For development, you may want to use session storage:

```php
// In a service provider or test setup
$this->app->bind('cart.storage', function ($app) {
    return new \AIArmada\Cart\Storage\SessionStorage(
        session: $app['session']
    );
});
```

## Troubleshooting

### Common Issues

**1. "Cart table not found"**

Run migrations: `php artisan migrate`

**2. "Money currency not found"**

Ensure `akaunting/money` is installed and your currency code is valid.

**3. "Class Cart not found"**

Clear config cache: `php artisan config:clear`

**4. JSON column errors on SQLite**

SQLite has limited JSON support. Use `json` type (default) or upgrade to MySQL/PostgreSQL for production.
