<?php

declare(strict_types=1);

$tablePrefix = 'inventory_';

$tables = [
    'locations' => $tablePrefix . 'locations',
    'levels' => $tablePrefix . 'levels',
    'movements' => $tablePrefix . 'movements',
    'allocations' => $tablePrefix . 'allocations',
    'batches' => $tablePrefix . 'batches',
    'serials' => $tablePrefix . 'serials',
    'serial_history' => $tablePrefix . 'serial_history',
    'cost_layers' => $tablePrefix . 'cost_layers',
    'standard_costs' => $tablePrefix . 'standard_costs',
    'valuation_snapshots' => $tablePrefix . 'valuation_snapshots',
    'backorders' => $tablePrefix . 'backorders',
    'demand_history' => $tablePrefix . 'demand_history',
    'supplier_leadtimes' => $tablePrefix . 'supplier_leadtimes',
    'reorder_suggestions' => $tablePrefix . 'reorder_suggestions',
    'reservations' => $tablePrefix . 'reservations',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'tables' => $tables,
        'json_column_type' => 'jsonb',
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'currency' => 'MYR',
    ],

    /* Optional integration model overrides */
    'models' => [
        'product' => null,
        'variant' => null,
    ],

    'default_reorder_point' => 10,
    'allocation_strategy' => 'priority',
    'allocation_ttl_minutes' => 30,
    'allow_split_allocation' => true,

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Register a resolver that returns the current owner (merchant, tenant, etc).
        | When enabled, inventory is automatically scoped to the current owner.
        | The OwnerResolverInterface binding is provided by commerce-support.
    |
    */
    'owner' => [
        'enabled' => false,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Integration
    |--------------------------------------------------------------------------
    |
    | Configure tight integration with the Cart package when installed.
    |
    */
    'cart' => [
        // Enable cart integration
        'enabled' => true,

        // Validate stock availability when adding items to cart
        'validate_on_add' => false,

        // Auto-allocate inventory when items are added to cart
        'auto_allocate_on_add' => false,

        // Reserve stock when checkout starts
        'reserve_on_checkout' => true,

        // Block checkout if any item cannot be reserved
        'block_checkout_on_insufficient' => true,

        // Allow adding items even when out of stock (backorder support)
        'allow_backorder' => false,

        // Maximum backorder quantity per item (null = unlimited)
        'max_backorder_quantity' => null,

        // Metadata key for storing allocation status on cart items
        'allocation_metadata_key' => 'inventory_allocated',

        // Metadata key for storing backorder status on cart items
        'backorder_metadata_key' => 'is_backorder',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Integration
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'auto_commit' => true,
        'events' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders Integration
    |--------------------------------------------------------------------------
    |
    | Configure integration with the Orders package when installed.
    | Listens for InventoryDeductionRequired and InventoryReleaseRequired events.
    |
    */
    'orders' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    'events' => [
        'low_inventory' => true,
        'out_of_inventory' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'keep_expired_for_minutes' => 0,
    ],
];
