<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\AddressValidationResult;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\LabelData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentResultData;
use AIArmada\Shipping\Data\TrackingData;
use AIArmada\Shipping\Drivers\NullShippingDriver;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\TrackingStatus;

// ============================================
// NullShippingDriver Tests
// ============================================

beforeEach(function (): void {
    $this->driver = new NullShippingDriver;
});

it('returns correct carrier code', function (): void {
    expect($this->driver->getCarrierCode())->toBe('null');
});

it('returns correct carrier name', function (): void {
    expect($this->driver->getCarrierName())->toBe('Null Driver (Testing)');
});

it('reports all capabilities as supported', function (): void {
    // Null driver supports everything for testing purposes
    expect($this->driver->supports(DriverCapability::RateQuotes))->toBeTrue();
    expect($this->driver->supports(DriverCapability::Tracking))->toBeTrue();
    expect($this->driver->supports(DriverCapability::LabelGeneration))->toBeTrue();
});

it('returns test shipping methods', function (): void {
    $methods = $this->driver->getAvailableMethods();

    expect($methods)->not->toBeEmpty();
    expect($methods)->toHaveCount(2);
    expect($methods->first()->code)->toBe('standard');
});

it('returns test rate quotes', function (): void {
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

    $rates = $this->driver->getRates($origin, $destination, $packages);

    expect($rates)->not->toBeEmpty();
    expect($rates)->toHaveCount(2);
    expect($rates->first())->toBeInstanceOf(RateQuoteData::class);
    expect($rates->first()->rate)->toBe(0);
});

it('creates test shipment successfully', function (): void {
    $shipmentData = new ShipmentData(
        reference: 'TEST-001',
        carrierCode: 'null',
        serviceCode: 'test',
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

    expect($result)->toBeInstanceOf(ShipmentResultData::class);
    expect($result->isSuccessful())->toBeTrue();
    expect($result->trackingNumber)->toStartWith('TEST-');
});

it('can cancel shipments', function (): void {
    $result = $this->driver->cancelShipment('TEST-123');

    expect($result)->toBeTrue();
});

it('generates test label', function (): void {
    $label = $this->driver->generateLabel('TEST-123');

    expect($label)->toBeInstanceOf(LabelData::class);
    expect($label->format)->toBe('pdf');
    expect($label->content)->not->toBeNull();
});

it('returns test tracking data', function (): void {
    $tracking = $this->driver->track('TEST-123');

    expect($tracking)->toBeInstanceOf(TrackingData::class);
    expect($tracking->trackingNumber)->toBe('TEST-123');
    expect($tracking->status)->toBe(TrackingStatus::InTransit);
    expect($tracking->events)->not->toBeEmpty();
});

it('returns valid address validation', function (): void {
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

it('services all destinations', function (): void {
    $destination = new AddressData(
        name: 'Test',
        phone: '+60123456789',
        line1: '123 Test St',
        postcode: '50000',
        country: 'MY',
    );

    expect($this->driver->servicesDestination($destination))->toBeTrue();

    $internationalDest = new AddressData(
        name: 'Test',
        phone: '+1234567890',
        line1: '123 Test St',
        postcode: '10001',
        country: 'US',
    );

    expect($this->driver->servicesDestination($internationalDest))->toBeTrue();
});
