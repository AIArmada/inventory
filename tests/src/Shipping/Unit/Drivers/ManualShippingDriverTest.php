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
    $this->driver = new ManualShippingDriver;
});

it('returns correct carrier code', function (): void {
    expect($this->driver->getCarrierCode())->toBe('manual');
});

it('returns correct carrier name', function (): void {
    expect($this->driver->getCarrierName())->toBe('Manual Shipping');
});

it('reports no supported capabilities', function (): void {
    $driver = new ManualShippingDriver;

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
        line1: '123 Test St',
        postcode: '50000',
    );

    $destination = new AddressData(
        name: 'Receiver',
        phone: '+60198765432',
        line1: '456 Target St',
        postcode: '40000',
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
        line1: '123 Test St',
        postcode: '50000',
    );

    $destination = new AddressData(
        name: 'Receiver',
        phone: '+60198765432',
        line1: '456 Target St',
        postcode: '40000',
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
    expect($result->trackingNumber)->toStartWith('MAN-');
});

it('always services all destinations', function (): void {
    $destination = new AddressData(
        name: 'Test',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
        country: 'ANY',
    );

    expect($this->driver->servicesDestination($destination))->toBeTrue();
});

it('validates all addresses as valid', function (): void {
    $address = new AddressData(
        name: 'Test',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
    );

    $result = $this->driver->validateAddress($address);

    expect($result)->toBeInstanceOf(AddressValidationResult::class);
    expect($result->valid)->toBeTrue();
});

it('returns address validation warning', function (): void {
    $address = new AddressData(
        name: 'Test',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
    );

    $result = $this->driver->validateAddress($address);

    expect($result->warnings)->toContain('Address validation not available for manual shipping.');
});

it('tracks shipment with awaiting pickup status', function (): void {
    $trackingData = $this->driver->track('MAN-123ABC');

    expect($trackingData->trackingNumber)->toBe('MAN-123ABC');
    expect($trackingData->status)->toBe(AIArmada\Shipping\Enums\TrackingStatus::AwaitingPickup);
    expect($trackingData->carrier)->toBe('manual');
    expect($trackingData->events)->toHaveCount(1);
    expect($trackingData->events->first()->code)->toBe('MANUAL');
    expect($trackingData->events->first()->description)->toBe('Manual shipment - tracking not available');
});

it('cancels shipment always returns true', function (): void {
    $result = $this->driver->cancelShipment('MAN-123ABC');

    expect($result)->toBeTrue();
});

it('generates label with no content', function (): void {
    $label = $this->driver->generateLabel('MAN-123ABC');

    expect($label->format)->toBe('none');
    expect($label->url)->toBeNull();
    expect($label->content)->toBeNull();
});

it('returns custom carrier name from config', function (): void {
    $customDriver = new ManualShippingDriver([
        'name' => 'My Custom Shipping',
    ]);

    expect($customDriver->getCarrierName())->toBe('My Custom Shipping');
});

it('uses custom estimated days from config', function (): void {
    $customDriver = new ManualShippingDriver([
        'estimated_days' => 7,
    ]);

    $methods = $customDriver->getAvailableMethods();

    expect($methods->first()->minDays)->toBe(7);
    expect($methods->first()->maxDays)->toBe(9);
});

it('applies free shipping when cart total meets threshold', function (): void {
    $customDriver = new ManualShippingDriver([
        'default_rate' => 1500,
        'free_shipping_threshold' => 10000, // RM100
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

    // Cart total above threshold
    $rates = $customDriver->getRates($origin, $destination, $packages, ['cart_total' => 15000]);

    expect($rates->first()->rate)->toBe(0); // Free shipping
});

it('does not apply free shipping when cart total below threshold', function (): void {
    $customDriver = new ManualShippingDriver([
        'default_rate' => 1500,
        'free_shipping_threshold' => 10000, // RM100
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

    // Cart total below threshold
    $rates = $customDriver->getRates($origin, $destination, $packages, ['cart_total' => 5000]);

    expect($rates->first()->rate)->toBe(1500); // Default rate
});

it('uses custom currency from config', function (): void {
    $customDriver = new ManualShippingDriver([
        'default_rate' => 1000,
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

    expect($rates->first()->currency)->toBe('USD');
});
