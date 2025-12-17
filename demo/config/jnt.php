<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | J&T Express API Credentials
    |--------------------------------------------------------------------------
    */
    'customer_code' => env('JNT_CUSTOMER_CODE'),
    'password' => env('JNT_PASSWORD'),
    'api_account' => env('JNT_API_ACCOUNT'),
    'private_key' => env('JNT_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    */
    'environment' => env('JNT_ENVIRONMENT', 'testing'),
    'base_urls' => [
        'testing' => env('JNT_BASE_URL_TESTING', 'https://demoopenapi.jtexpress.my/webopenplatformapi'),
        'production' => env('JNT_BASE_URL_PRODUCTION', 'https://ylopenapi.jtexpress.my/webopenplatformapi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => 'jnt_',
        'tables' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Sender (Shipper) Details
    |--------------------------------------------------------------------------
    */
    'sender' => [
        'name' => env('JNT_SENDER_NAME', 'AIArmada Commerce'),
        'phone' => env('JNT_SENDER_PHONE', '+60123456789'),
        'address' => env('JNT_SENDER_ADDRESS', 'Lot 15, Jalan Perusahaan 2'),
        'city' => env('JNT_SENDER_CITY', 'Shah Alam'),
        'state' => env('JNT_SENDER_STATE', 'Selangor'),
        'postcode' => env('JNT_SENDER_POSTCODE', '40150'),
        'country' => env('JNT_SENDER_COUNTRY', 'MY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'enabled' => env('JNT_WEBHOOKS_ENABLED', true),
        'verify_signature' => env('JNT_WEBHOOKS_VERIFY_SIGNATURE', true),
    ],
];
