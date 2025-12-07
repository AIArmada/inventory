<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\AddressValidationResult;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Drivers\ManualShippingDriver;
use AIArmada\Shipping\Enums\DriverCapability;

// ============================================
// ManualShippingDriver Tests
// ============================================

beforeEach(function (): void {
    $this->driver = new ManualShippingDriver();
});

it('returns correct carrier code', function (): void {
    expect($this->driver->getCarrierCode())->toBe('manual');
});

it('returns correct carrier name', function (): void {
    expect($this->driver->getCarrierName())->toBe('Manual Shipping');
});

it('reports no supported capabilities', function (): void {
    $driver = new ManualShippingDriver();

    // Manual driver doesn't support any automated capabilities
    expect($driver->supports(DriverCapability::RateQuotes))->toBeFalse();
    expect($driver->supports(DriverCapability::LabelGeneration))->toBeFalse();
    expect($driver->supports(DriverCapability::Tracking))->toBeFalse();
});

it('returns standard shipping method', function (): void {
    $methods = $this->driver->getAvailableMethods();

    expect($methods)->toHaveCount(1);
    expect($methods->first()->code)->toBe('standard');
    expect($methods->first()->name)->toBe('Standard Shipping');
});

it('calculates rates based on weight', function (): void {
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

    $packages = [new PackageData(weight: 2000)]; // 2kg

    $rates = $this->driver->getRates($origin, $destination, $packages);

    expect($rates)->toHaveCount(1);
    expect($rates->first())->toBeInstanceOf(RateQuoteData::class);
    expect($rates->first()->carrier)->toBe('manual');
    expect($rates->first()->service)->toBe('standard');
    expect($rates->first()->rate)->toBe(0); // Default rate is 0
});

it('applies custom configuration for rates', function (): void {
    $customDriver = new ManualShippingDriver([
        'default_rate' => 1000, // RM10.00
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

    $packages = [new PackageData(weight: 3000)]; // 3kg

    $rates = $customDriver->getRates($origin, $destination, $packages);

    // Manual driver uses flat default_rate
    expect($rates->first()->rate)->toBe(1000);
});

it('creates shipment with manual tracking number', function (): void {
    $shipmentData = new ShipmentData(
        reference: 'TEST-001',
        carrierCode: 'manual',
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
    expect($result->trackingNumber)->toStartWith('MAN-');
});

it('always services all destinations', function (): void {
    $destination = new AddressData(
        name: 'Test',
        phone: '+60123456789',
        address: '123 Test St',
        postCode: '50000',
        countryCode: 'ANY',
    );

    expect($this->driver->servicesDestination($destination))->toBeTrue();
});

it('validates all addresses as valid', function (): void {
    $address = new AddressData(
        name: 'Test',
        phone: '+60123456789',
        address: '123 Test St',
        postCode: '50000',
    );

    $result = $this->driver->validateAddress($address);

    expect($result)->toBeInstanceOf(AddressValidationResult::class);
    expect($result->valid)->toBeTrue();
});
