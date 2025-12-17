<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Vouchers & Discounts',

    'resources' => [
        'navigation_sort' => [
            'campaigns' => 5,
            'vouchers' => 10,
            'voucher_usage' => 20,
            'voucher_wallets' => 30,
            'gift_cards' => 50,
            'fraud_signals' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */
    'polling_interval' => '30s',

    'order_resource' => null,
    'owners' => [],
    'default_currency' => 'MYR',
];
