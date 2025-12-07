<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Drivers\FlatRateShippingDriver;
use AIArmada\Shipping\Enums\DriverCapability;

// ============================================
// FlatRateShippingDriver Tests
// ============================================

beforeEach(function (): void {
    $this->driver = new FlatRateShippingDriver();
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
        address: '123 Test St',
        postCode: '50000',
    );

    $destination = new AddressData(
        name: 'Receiver',
        phone: '+60198765432',
        address: '456 Target St',
        postCode: '40000',
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
        address: '123 Test St',
        postCode: '50000',
    );

    $destination = new AddressData(
        name: 'Receiver',
        phone: '+60198765432',
        address: '456 Target St',
        postCode: '40000',
    );

    $packages = [new PackageData(weight: 1000)];

    $rates = $customDriver->getRates($origin, $destination, $packages);

    expect($rates->first()->rate)->toBe(1500);
    expect($rates->first()->estimatedDays)->toBe(7);
});

it('creates shipment with generated tracking number', function (): void {
    $shipmentData = new AIArmada\Shipping\Data\ShipmentData(
        reference: 'TEST-001',
        carrierCode: 'flat_rate',
        serviceCode: 'standard',
        origin: new AddressData(
            name: 'Sender',
            phone: '+60123456789',
            address: '123 Test St',
            postCode: '50000',
        ),
        destination: new AddressData(
            name: 'Receiver',
            phone: '+60198765432',
            address: '456 Target St',
            postCode: '40000',
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
        address: '123 Test St',
        postCode: '50000',
        countryCode: 'MYS',
    );

    $sgDestination = new AddressData(
        name: 'Test',
        phone: '+6512345678',
        address: '123 Test St',
        postCode: '123456',
        countryCode: 'SGP',
    );

    expect($this->driver->servicesDestination($myDestination))->toBeTrue();
    expect($this->driver->servicesDestination($sgDestination))->toBeTrue();
});
