<?php

declare(strict_types=1);

return [
    /* Navigation */

    'navigation_group' => 'E-commerce',

    /* Features */

    'widgets' => [
        'show_conversion_rate' => true,
        'currency' => env('AFFILIATES_DEFAULT_CURRENCY', 'USD'),
    ],

    'portal' => [
        'enabled' => env('AFFILIATES_PORTAL_ENABLED', true),
        'panel_id' => env('AFFILIATES_PORTAL_PANEL_ID', 'affiliate'),
        'path' => env('AFFILIATES_PORTAL_PATH', 'affiliate'),
        'brand_name' => env('AFFILIATES_PORTAL_BRAND_NAME', 'Affiliate Portal'),
        'primary_color' => env('AFFILIATES_PORTAL_PRIMARY_COLOR', '#6366f1'),
        'login_enabled' => env('AFFILIATES_PORTAL_LOGIN_ENABLED', true),
        'registration_enabled' => env('AFFILIATES_PORTAL_REGISTRATION_ENABLED', true),
        'auth_guard' => env('AFFILIATES_PORTAL_AUTH_GUARD', 'web'),
        'features' => [
            'dashboard' => true,
            'links' => true,
            'conversions' => true,
            'payouts' => true,
        ],
    ],

    /* Integrations */

    'integrations' => [
        'filament_cart' => true,
        'filament_vouchers' => true,
    ],

    /* Resources */

    'resources' => [
        'navigation_sort' => [
            'affiliates' => 60,
            'affiliate_conversions' => 61,
            'affiliate_payouts' => 62,
        ],
    ],
];
