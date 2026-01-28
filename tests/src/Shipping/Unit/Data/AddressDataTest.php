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
        line1: '123 Test Street',
        postcode: '50000',
    );

    expect($address->name)->toBe('John Doe');
    expect($address->phone)->toBe('+60123456789');
    expect($address->line1)->toBe('123 Test Street');
    expect($address->postcode)->toBe('50000');
    expect($address->country)->toBe('MY'); // default
});

it('creates address data with all fields', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        line1: '123 Test Street',
        postcode: '50000',
        country: 'SG',
        state: 'Kuala Lumpur',
        city: 'KL',
        company: 'Test Company',
        email: 'john@test.com',
        latitude: 3.1390,
        longitude: 101.6869,
    );

    expect($address->country)->toBe('SG');
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
        line1: '123 Test Street',
        postcode: '50000',
    );

    expect($address->name)->not->toBeEmpty();
    expect($address->phone)->not->toBeEmpty();
    expect($address->line1)->not->toBeEmpty();
    expect($address->postcode)->not->toBeEmpty();
});

it('formats full address with all parts', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        line1: '123 Test Street',
        postcode: '50000',
        country: 'MY',
        city: 'Kuala Lumpur',
        state: 'WP',
        line2: 'Suite 100',
    );

    $fullAddress = $address->getFullAddress();

    expect($fullAddress)->toContain('123 Test Street');
    expect($fullAddress)->toContain('Suite 100');
    expect($fullAddress)->toContain('Kuala Lumpur');
    expect($fullAddress)->toContain('WP');
    expect($fullAddress)->toContain('50000');
    expect($fullAddress)->toContain('MY');
});

it('formats full address without optional parts', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        line1: '123 Test Street',
        postcode: '50000',
        country: 'MY',
    );

    $fullAddress = $address->getFullAddress();

    expect($fullAddress)->toBe('123 Test Street, 50000, MY');
});

it('formats name with company when company exists', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        line1: '123 Test Street',
        postcode: '50000',
        company: 'ACME Corp',
    );

    expect($address->getFormattedName())->toBe('John Doe (ACME Corp)');
});

it('formats name without company when company is null', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        line1: '123 Test Street',
        postcode: '50000',
    );

    expect($address->getFormattedName())->toBe('John Doe');
});

it('sets residential flag correctly', function (): void {
    $residentialAddress = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        line1: '123 Test Street',
        postcode: '50000',
        isResidential: true,
    );

    $commercialAddress = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        line1: '123 Test Street',
        postcode: '50000',
        isResidential: false,
    );

    expect($residentialAddress->isResidential)->toBeTrue();
    expect($commercialAddress->isResidential)->toBeFalse();
});

it('defaults to residential address', function (): void {
    $address = new AddressData(
        name: 'John Doe',
        phone: '+60123456789',
        line1: '123 Test Street',
        postcode: '50000',
    );

    expect($address->isResidential)->toBeTrue();
});
