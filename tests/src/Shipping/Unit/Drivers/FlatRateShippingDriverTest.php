<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Drivers\FlatRateShippingDriver;
use AIArmada\Shipping\Enums\DriverCapability;

// ============================================
// FlatRateShippingDriver Tests
// ============================================

beforeEach(function (): void {
    $this->driver = new FlatRateShippingDriver;
});

it('returns correct carrier code', function (): void {
    expect($this->driver->getCarrierCode())->toBe('flat_rate');
});

it('returns correct carrier name', function (): void {
    expect($this->driver->getCarrierName())->toBe('Flat Rate Shipping');
});

it('supports rate quotes capability', function (): void {
    expect($this->driver->supports(DriverCapability::RateQuotes))->toBeTrue();
});

it('does not support tracking capability', function (): void {
    expect($this->driver->supports(DriverCapability::Tracking))->toBeFalse();
});

it('returns empty methods when not configured', function (): void {
    $methods = $this->driver->getAvailableMethods();

    expect($methods)->toBeEmpty();
});

it('returns empty rates when not configured', function (): void {
    $origin = new AddressData(
        name: 'Sender',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
    );

    $destination = new AddressData(
        name: 'Receiver',
        phone: '+60198765432',
        line1: '456 Target St',
        postcode: '40000',
    );

    $lightPackage = [new PackageData(weight: 500)];

    $rates = $this->driver->getRates($origin, $destination, $lightPackage);

    expect($rates)->toBeEmpty();
});

it('applies custom flat rate from configuration', function (): void {
    $customDriver = new FlatRateShippingDriver([
        'rates' => [
            'budget' => [
                'name' => 'Budget Shipping',
                'rate' => 1500, // RM15.00
                'estimated_days' => 7,
            ],
        ],
    ]);

    $origin = new AddressData(
        name: 'Sender',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
    );

    $destination = new AddressData(
        name: 'Receiver',
        phone: '+60198765432',
        line1: '456 Target St',
        postcode: '40000',
    );

    $packages = [new PackageData(weight: 1000)];

    $rates = $customDriver->getRates($origin, $destination, $packages);

    expect($rates->first()->rate)->toBe(1500);
    expect($rates->first()->estimatedDays)->toBe(7);
});

it('creates shipment with generated tracking number', function (): void {
    $shipmentData = new ShipmentData(
        reference: 'TEST-001',
        carrierCode: 'flat_rate',
        serviceCode: 'standard',
        origin: new AddressData(
            name: 'Sender',
            phone: '+60123456789',
            line1: '123 Test St',
            postcode: '50000',
        ),
        destination: new AddressData(
            name: 'Receiver',
            phone: '+60198765432',
            line1: '456 Target St',
            postcode: '40000',
        ),
        items: [],
    );

    $result = $this->driver->createShipment($shipmentData);

    expect($result->isSuccessful())->toBeTrue();
    expect($result->trackingNumber)->toStartWith('FLAT-');
});

it('services all destinations', function (): void {
    $myDestination = new AddressData(
        name: 'Test',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
        country: 'MY',
    );

    $sgDestination = new AddressData(
        name: 'Test',
        phone: '+6512345678',
        line1: '123 Test St',
        postcode: '123456',
        country: 'SG',
    );

    expect($this->driver->servicesDestination($myDestination))->toBeTrue();
    expect($this->driver->servicesDestination($sgDestination))->toBeTrue();
});

it('tracks shipment with awaiting pickup status', function (): void {
    $trackingData = $this->driver->track('FLAT-123ABC');

    expect($trackingData->trackingNumber)->toBe('FLAT-123ABC');
    expect($trackingData->status)->toBe(AIArmada\Shipping\Enums\TrackingStatus::AwaitingPickup);
    expect($trackingData->carrier)->toBe('flat_rate');
    expect($trackingData->events)->toHaveCount(1);
    expect($trackingData->events->first()->code)->toBe('FLAT');
    expect($trackingData->events->first()->description)->toBe('Flat rate shipment - tracking not available');
});

it('validates address always returns valid', function (): void {
    $address = new AddressData(
        name: 'Test',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
    );

    $result = $this->driver->validateAddress($address);

    expect($result->isValid())->toBeTrue();
});

it('cancels shipment always returns true', function (): void {
    $result = $this->driver->cancelShipment('FLAT-123ABC');

    expect($result)->toBeTrue();
});

it('generates label with no content', function (): void {
    $label = $this->driver->generateLabel('FLAT-123ABC');

    expect($label->format)->toBe('none');
    expect($label->url)->toBeNull();
    expect($label->content)->toBeNull();
});

it('returns custom carrier name from config', function (): void {
    $customDriver = new FlatRateShippingDriver([
        'name' => 'Custom Flat Rate',
    ]);

    expect($customDriver->getCarrierName())->toBe('Custom Flat Rate');
});

it('returns configured methods', function (): void {
    $customDriver = new FlatRateShippingDriver([
        'rates' => [
            'standard' => [
                'name' => 'Standard Shipping',
                'rate' => 1000,
                'estimated_days' => 5,
            ],
            'express' => [
                'name' => 'Express Shipping',
                'rate' => 2000,
                'estimated_days' => 2,
            ],
        ],
    ]);

    $methods = $customDriver->getAvailableMethods();

    expect($methods)->toHaveCount(2);
    expect($methods->first()->code)->toBe('standard');
    expect($methods->first()->name)->toBe('Standard Shipping');
    expect($methods->last()->code)->toBe('express');
});

it('returns multiple configured rates', function (): void {
    $customDriver = new FlatRateShippingDriver([
        'rates' => [
            'standard' => [
                'name' => 'Standard',
                'rate' => 1000,
                'estimated_days' => 5,
            ],
            'express' => [
                'name' => 'Express',
                'rate' => 2500,
                'estimated_days' => 2,
            ],
        ],
        'currency' => 'USD',
    ]);

    $origin = new AddressData(
        name: 'Sender',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
    );

    $destination = new AddressData(
        name: 'Receiver',
        phone: '+60198765432',
        line1: '456 Target St',
        postcode: '40000',
    );

    $packages = [new PackageData(weight: 500)];

    $rates = $customDriver->getRates($origin, $destination, $packages);

    expect($rates)->toHaveCount(2);
    expect($rates->first()->currency)->toBe('USD');
    expect($rates->first()->calculatedLocally)->toBeTrue();
});
