---
title: Configuration
---

# Configuration

All configuration options for the Cart package with explanations.

## Full Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    */
    'database' => [
        // Main carts table name
        'table' => env('CART_DB_TABLE', 'carts'),
        
        // Conditions table for reusable condition definitions
        'conditions_table' => env('CART_CONDITIONS_TABLE', 'conditions'),
        
        // JSON column type: 'json' or 'jsonb' (PostgreSQL)
        // 'jsonb' enables GIN indexes for faster queries
        'json_column_type' => env('CART_JSON_COLUMN_TYPE', 'json'),
        
        // Time-to-live in seconds for cart expiration
        // Set to null to disable expiration
        'ttl' => env('CART_DB_TTL', 60 * 60 * 24 * 30), // 30 days
        
        // Use SELECT FOR UPDATE to prevent race conditions
        // Enable for high-concurrency scenarios
        'lock_for_update' => env('CART_DB_LOCK_FOR_UPDATE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Money Configuration
    |--------------------------------------------------------------------------
    */
    'money' => [
        // Default currency code (ISO 4217)
        'default_currency' => env('CART_DEFAULT_CURRENCY', 'MYR'),
        
        // Rounding mode for percentage calculations:
        // - 'half_up': Round 0.5 up (default, most common)
        // - 'half_even': Round 0.5 to nearest even (banker's rounding)
        // - 'floor': Always round down
        // - 'ceil': Always round up
        'rounding_mode' => env('CART_ROUNDING_MODE', 'half_up'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Behavior
    |--------------------------------------------------------------------------
    */
    
    // What happens when the last item is removed:
    // - 'destroy': Delete cart completely (default)
    // - 'clear': Keep cart, clear all conditions/metadata
    // - 'preserve': Keep everything except items
    'empty_cart_behavior' => env('CART_EMPTY_BEHAVIOR', 'destroy'),

    // Cart migration settings for guest-to-user conversion
    'migration' => [
        // Auto-migrate guest cart when user logs in
        'auto_migrate_on_login' => env('CART_AUTO_MIGRATE', true),
        
        // How to handle item conflicts during merge:
        // - 'add_quantities': Sum guest + user quantities
        // - 'keep_highest_quantity': Use maximum of both
        // - 'keep_user_cart': Ignore guest quantities
        // - 'replace_with_guest': Use guest quantities
        'merge_strategy' => env('CART_MERGE_STRATEGY', 'add_quantities'),
    ],

    // Enable/disable event dispatching
    'events' => env('CART_EVENTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy / Owner Scoping
    |--------------------------------------------------------------------------
    */
    'owner' => [
        // Enable owner-based data isolation
        'enabled' => env('CART_OWNER_ENABLED', false),
        
        // Include global (owner=null) records in queries
        // When false, only owner-specific records are returned
        'include_global' => env('CART_OWNER_INCLUDE_GLOBAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        // Maximum unique items in a cart
        'max_items' => env('CART_MAX_ITEMS', 1000),
        
        // Maximum quantity for a single item
        'max_item_quantity' => env('CART_MAX_QUANTITY', 10000),
        
        // Maximum cart data size in bytes (prevents DoS)
        'max_data_size_bytes' => env('CART_MAX_DATA_BYTES', 1048576), // 1MB
        
        // Maximum string length for names/attributes
        'max_string_length' => env('CART_MAX_STRING_LENGTH', 255),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    */
    'performance' => [
        // Enable lazy pipeline evaluation with memoization
        // Caches condition calculations until cart changes
        'lazy_pipeline' => env('CART_LAZY_PIPELINE_ENABLED', true),
    ],
];
```

## Configuration Scenarios

### E-commerce Store

```php
'money' => [
    'default_currency' => 'USD',
    'rounding_mode' => 'half_up',
],
'database' => [
    'ttl' => 60 * 60 * 24 * 7, // 7 days
],
'empty_cart_behavior' => 'destroy',
```

### Multi-Tenant SaaS

```php
'owner' => [
    'enabled' => true,
    'include_global' => false, // Strict tenant isolation
],
'database' => [
    'lock_for_update' => true, // Prevent race conditions
],
```

### High-Traffic Site

```php
'database' => [
    'json_column_type' => 'jsonb', // PostgreSQL with GIN indexes
    'lock_for_update' => false, // Use optimistic locking only
],
'performance' => [
    'lazy_pipeline' => true,
],
```

### Point-of-Sale System

```php
'money' => [
    'rounding_mode' => 'half_even', // Banker's rounding
],
'database' => [
    'ttl' => 60 * 60 * 8, // 8 hours
],
'empty_cart_behavior' => 'preserve', // Keep for audit
```

## Environment Variables

All configuration can be overridden via environment variables:

```bash
# Database
CART_DB_TABLE=shopping_carts
CART_CONDITIONS_TABLE=cart_conditions
CART_JSON_COLUMN_TYPE=jsonb
CART_DB_TTL=604800
CART_DB_LOCK_FOR_UPDATE=true

# Money
CART_DEFAULT_CURRENCY=EUR
CART_ROUNDING_MODE=half_even

# Behavior
CART_EMPTY_BEHAVIOR=clear
CART_AUTO_MIGRATE=false
CART_MERGE_STRATEGY=keep_highest_quantity
CART_EVENTS_ENABLED=true

# Owner
CART_OWNER_ENABLED=true
CART_OWNER_INCLUDE_GLOBAL=true

# Limits
CART_MAX_ITEMS=500
CART_MAX_QUANTITY=999
CART_MAX_DATA_BYTES=524288

# Performance
CART_LAZY_PIPELINE_ENABLED=true
```

## Runtime Configuration

Some settings can be changed at runtime:

```php
use AIArmada\Cart\Facades\Cart;

// Disable lazy pipeline for debugging
Cart::withoutLazyPipeline();

// Re-enable lazy pipeline
Cart::withLazyPipeline();

// Get pipeline stats
$stats = Cart::getPipelineCacheStats();
```
