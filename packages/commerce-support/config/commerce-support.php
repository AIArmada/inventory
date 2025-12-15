<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;

return [
    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'resolver' => env('COMMERCE_OWNER_RESOLVER', NullOwnerResolver::class),
    ],
];
