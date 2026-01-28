<?php

declare(strict_types=1);

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Transformers\OrderAddressTransformer;

it('transforms address data into order schema', function (): void {
    $transformer = new OrderAddressTransformer;
    $session = new CheckoutSession;

    $data = [
        'name' => 'Jane Doe',
        'street1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country' => 'MY',
        'email' => 'jane@example.com',
        'phone' => '+60123456789',
    ];

    $result = $transformer->transform($data, $session);

    expect($result)->toMatchArray([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'postcode' => '50000',
        'country' => 'MY',
        'email' => 'jane@example.com',
        'phone' => '+60123456789',
    ]);
});

it('returns empty data when address input is empty', function (): void {
    $transformer = new OrderAddressTransformer;
    $session = new CheckoutSession;

    expect($transformer->transform([], $session))->toBe([]);
});
