<?php

declare(strict_types=1);
use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table' => env('CART_DB_TABLE', 'carts'),
        'conditions_table' => env('CART_CONDITIONS_TABLE', 'conditions'),
        'events_table' => env('CART_EVENTS_TABLE', 'cart_events'),
        'json_column_type' => env('CART_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'ttl' => env('CART_DB_TTL', 60 * 60 * 24 * 30), // 30 days, null to disable
        'lock_for_update' => env('CART_DB_LOCK_FOR_UPDATE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'storage' => env('CART_STORAGE_DRIVER', 'database'), // session, database, cache

    'money' => [
        'default_currency' => env('CART_DEFAULT_CURRENCY', 'MYR'),
        'rounding_mode' => env('CART_ROUNDING_MODE', 'half_up'), // half_up, half_even, floor, ceil
    ],

    'tax' => [
        'default_rate' => env('CART_TAX_RATE', 0.0),
        'default_region' => env('CART_TAX_REGION'),
        'prices_include_tax' => env('CART_TAX_INCLUSIVE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Behavior
    |--------------------------------------------------------------------------
    */
    'empty_cart_behavior' => env('CART_EMPTY_BEHAVIOR', 'destroy'), // destroy, clear, preserve

    'migration' => [
        'auto_migrate_on_login' => env('CART_AUTO_MIGRATE', true),
        'merge_strategy' => env('CART_MERGE_STRATEGY', 'add_quantities'),
    ],

    'events' => env('CART_EVENTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Ownership (Multi-Tenancy)
    |--------------------------------------------------------------------------
    |
    | Multi-tenancy support for scoping carts by owner. When enabled, carts
    | are isolated per owner using the configured resolver. The resolver must
    | implement OwnerResolverInterface from commerce-support.
    |
    */
    'owner' => [
        'enabled' => env('CART_OWNER_ENABLED', false),
        'resolver' => env('CART_OWNER_RESOLVER', NullOwnerResolver::class),
        'include_global' => env('CART_OWNER_INCLUDE_GLOBAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_items' => env('CART_MAX_ITEMS', 1000),
        'max_item_quantity' => env('CART_MAX_QUANTITY', 10000),
        'max_data_size_bytes' => env('CART_MAX_DATA_BYTES', 1048576), // 1MB
        'max_string_length' => env('CART_MAX_STRING_LENGTH', 255),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for cart operations. Limits are per identifier
    | (user ID, session ID, or IP address).
    |
    */
    'rate_limiting' => [
        'enabled' => env('CART_RATE_LIMITING_ENABLED', true),
        'limits' => [
            'add_item' => ['perMinute' => 60, 'perHour' => 500],
            'update_item' => ['perMinute' => 120, 'perHour' => 1000],
            'remove_item' => ['perMinute' => 60, 'perHour' => 500],
            'clear_cart' => ['perMinute' => 10, 'perHour' => 50],
            'checkout' => ['perMinute' => 5, 'perHour' => 20],
            'merge_cart' => ['perMinute' => 5, 'perHour' => 30],
            'get_cart' => ['perMinute' => 300, 'perHour' => 3000],
            'add_condition' => ['perMinute' => 30, 'perHour' => 200],
            'remove_condition' => ['perMinute' => 30, 'perHour' => 200],
            'default' => ['perMinute' => 60, 'perHour' => 500],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Performance optimizations for cart calculations. The lazy pipeline
    | enables memoized condition evaluation with 60-92% fewer computations.
    |
    */
    'performance' => [
        'lazy_pipeline' => env('CART_LAZY_PIPELINE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Sourcing
    |--------------------------------------------------------------------------
    |
    | Event sourcing configuration for cart audit trails and replay.
    | When enabled, cart events are persisted to the cart_events table.
    |
    */
    'event_sourcing' => [
        'enabled' => env('CART_EVENT_SOURCING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Storage
    |--------------------------------------------------------------------------
    */
    'session' => [
        'key' => env('CART_SESSION_KEY', 'cart'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Storage
    |--------------------------------------------------------------------------
    |
    | Multi-tier caching configuration for cart data. When enabled, carts are
    | cached using a read-through pattern with automatic cache invalidation.
    |
    */
    'cache' => [
        'enabled' => env('CART_CACHE_ENABLED', false),
        'store' => env('CART_CACHE_STORE', 'redis'),
        'prefix' => env('CART_CACHE_PREFIX', 'cart'),
        'ttl' => env('CART_CACHE_TTL', 3600), // 1 hour
        'queue' => env('CART_CACHE_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Intelligence (Phase 3)
    |--------------------------------------------------------------------------
    |
    | AI-powered features for abandonment prediction, recovery optimization,
    | and product recommendations.
    |
    */
    'ai' => [
        'abandonment' => [
            'enabled' => env('CART_AI_ABANDONMENT_ENABLED', true),
            'inactivity_threshold_minutes' => env('CART_AI_INACTIVITY_THRESHOLD', 15),
            'high_value_threshold_cents' => env('CART_AI_HIGH_VALUE_THRESHOLD', 50000),
            'cache_predictions_seconds' => env('CART_AI_CACHE_PREDICTIONS', 300),
        ],
        'recovery' => [
            'enabled' => env('CART_AI_RECOVERY_ENABLED', true),
            'max_attempts' => env('CART_AI_RECOVERY_MAX_ATTEMPTS', 3),
            'exploration_rate' => env('CART_AI_RECOVERY_EXPLORATION_RATE', 0.1),
            'discount_ceiling_percent' => env('CART_AI_RECOVERY_DISCOUNT_CEILING', 20),
            'strategies' => ['email', 'discount', 'free_shipping', 'urgency'],
        ],
        'recommendations' => [
            'enabled' => env('CART_AI_RECOMMENDATIONS_ENABLED', true),
            'max_recommendations' => env('CART_AI_MAX_RECOMMENDATIONS', 5),
            'types' => ['frequently_bought', 'complementary', 'upsell', 'trending'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection (Phase 2)
    |--------------------------------------------------------------------------
    |
    | Fraud detection engine configuration with pluggable detectors and
    | risk scoring thresholds.
    |
    */
    'fraud' => [
        'enabled' => env('CART_FRAUD_ENABLED', true),
        'block_threshold' => env('CART_FRAUD_BLOCK_THRESHOLD', 80),
        'review_threshold' => env('CART_FRAUD_REVIEW_THRESHOLD', 60),
        'collector' => [
            'enabled' => env('CART_FRAUD_COLLECTOR_ENABLED', true),
            'store' => env('CART_FRAUD_COLLECTOR_STORE', 'database'),
        ],
        'detectors' => [
            'price_manipulation' => [
                'enabled' => env('CART_FRAUD_PRICE_ENABLED', true),
                'weight' => 1.0,
                'max_discount_percentage' => 50,
                'min_price_cents' => 0,
            ],
            'velocity' => [
                'enabled' => env('CART_FRAUD_VELOCITY_ENABLED', true),
                'weight' => 1.0,
                'max_operations_per_minute' => 100,
                'suspicious_ip_multiplier' => 2.0,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Collaborative Carts (Phase 3)
    |--------------------------------------------------------------------------
    |
    | Enable real-time collaborative shopping with CRDT conflict resolution
    | and WebSocket broadcasting.
    |
    */
    'collaboration' => [
        'enabled' => env('CART_COLLABORATION_ENABLED', false),
        'max_collaborators' => env('CART_COLLABORATION_MAX_COLLABORATORS', 5),
        'share_link_expiry_days' => env('CART_COLLABORATION_LINK_EXPIRY', 7),
        'invitation_expiry_hours' => env('CART_COLLABORATION_INVITE_EXPIRY', 48),
        'broadcast_channel' => env('CART_COLLABORATION_CHANNEL', 'presence'),
        'invitation_mailable' => null, // Custom mailable class for invitations
    ],

    /*
    |--------------------------------------------------------------------------
    | Blockchain Proofs (Phase 3)
    |--------------------------------------------------------------------------
    |
    | Merkle tree proof generation for cart state verification and optional
    | multi-chain anchoring.
    |
    */
    'blockchain' => [
        'enabled' => env('CART_BLOCKCHAIN_ENABLED', false),
        'signing_key' => env('CART_BLOCKCHAIN_SIGNING_KEY'),
        'anchoring' => [
            'enabled' => env('CART_BLOCKCHAIN_ANCHORING_ENABLED', false),
            'chains' => ['internal'], // Options: internal, ethereum, bitcoin, opentimestamps
            'ethereum' => [
                'rpc_url' => env('CART_BLOCKCHAIN_ETH_RPC'),
                'contract_address' => env('CART_BLOCKCHAIN_ETH_CONTRACT'),
                'private_key' => env('CART_BLOCKCHAIN_ETH_KEY'),
            ],
        ],
    ],
];
