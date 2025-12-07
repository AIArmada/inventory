<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\AddressData;

// ============================================
// AddressData DTO Tests
// ============================================

it('creates address data with required fields', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        address: '123 Test Street',
        postCode: '50000',
    );

    expect($address->name)->toBe('John Doe');
    expect($address->phone)->toBe('+60123456789');
    expect($address->address)->toBe('123 Test Street');
    expect($address->postCode)->toBe('50000');
    expect($address->countryCode)->toBe('MYS'); // default
});

it('creates address data with all fields', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        address: '123 Test Street',
        postCode: '50000',
        countryCode: 'SGP',
        state: 'Kuala Lumpur',
        city: 'KL',
        company: 'Test Company',
        email: 'john@test.com',
        latitude: 3.1390,
        longitude: 101.6869,
    );

    expect($address->countryCode)->toBe('SGP');
    expect($address->state)->toBe('Kuala Lumpur');
    expect($address->city)->toBe('KL');
    expect($address->company)->toBe('Test Company');
    expect($address->email)->toBe('john@test.com');
    expect($address->latitude)->toBe(3.1390);
    expect($address->longitude)->toBe(101.6869);
});

it('validates required fields are present', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        address: '123 Test Street',
        postCode: '50000',
    );

    expect($address->name)->not->toBeEmpty();
    expect($address->phone)->not->toBeEmpty();
    expect($address->address)->not->toBeEmpty();
    expect($address->postCode)->not->toBeEmpty();
});
