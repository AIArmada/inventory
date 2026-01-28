<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentItemData;

describe('ShipmentData', function (): void {
    it('can create shipment data with required fields', function (): void {
        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            line1: '123 Origin St',
            postcode: '12345',
            country: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            line1: '456 Dest St',
            postcode: '67890',
            country: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $shipment = new ShipmentData(
            reference: 'TEST-SHIP-001',
            carrierCode: 'fedex',
            serviceCode: 'standard',
            origin: $origin,
            destination: $destination
        );

        expect($shipment->reference)->toBe('TEST-SHIP-001');
        expect($shipment->carrierCode)->toBe('fedex');
        expect($shipment->serviceCode)->toBe('standard');
        expect($shipment->origin)->toBe($origin);
        expect($shipment->destination)->toBe($destination);
        expect($shipment->items)->toBe([]);
        expect($shipment->packages)->toBe([]);
        expect($shipment->declaredValue)->toBeNull();
        expect($shipment->currency)->toBeNull();
        expect($shipment->signatureRequired)->toBeFalse();
        expect($shipment->insuranceRequired)->toBeFalse();
        expect($shipment->codAmount)->toBeNull();
        expect($shipment->metadata)->toBe([]);
    });

    it('can create shipment data with all fields', function (): void {
        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            line1: '123 Origin St',
            postcode: '12345',
            country: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            line1: '456 Dest St',
            postcode: '67890',
            country: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $items = [
            new ShipmentItemData(
                name: 'Test Item',
                quantity: 2,
                weight: 500,
                declaredValue: 1000
            ),
        ];

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $shipment = new ShipmentData(
            reference: 'TEST-SHIP-001',
            carrierCode: 'fedex',
            serviceCode: 'standard',
            origin: $origin,
            destination: $destination,
            items: $items,
            packages: $packages,
            declaredValue: 2000,
            currency: 'USD',
            instructions: 'Handle with care',
            signatureRequired: true,
            insuranceRequired: true,
            codAmount: 1500,
            metadata: ['priority' => 'high']
        );

        expect($shipment->items)->toBe($items);
        expect($shipment->packages)->toBe($packages);
        expect($shipment->declaredValue)->toBe(2000);
        expect($shipment->currency)->toBe('USD');
        expect($shipment->instructions)->toBe('Handle with care');
        expect($shipment->signatureRequired)->toBeTrue();
        expect($shipment->insuranceRequired)->toBeTrue();
        expect($shipment->codAmount)->toBe(1500);
        expect($shipment->metadata)->toBe(['priority' => 'high']);
    });

    it('calculates total weight from packages when available', function (): void {
        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            line1: '123 Origin St',
            postcode: '12345',
            country: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            line1: '456 Dest St',
            postcode: '67890',
            country: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 2), // 2 packages * 1000g = 2000g
            new PackageData(1500, 15, 7, 7, 750, 'envelope', 1), // 1 package * 1500g = 1500g
        ];

        $shipment = new ShipmentData(
            reference: 'TEST-SHIP-001',
            carrierCode: 'fedex',
            serviceCode: 'standard',
            origin: $origin,
            destination: $destination,
            packages: $packages
        );

        expect($shipment->getTotalWeight())->toBe(3500); // 2000 + 1500
    });

    it('calculates total weight from items when no packages', function (): void {
        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            line1: '123 Origin St',
            postcode: '12345',
            country: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            line1: '456 Dest St',
            postcode: '67890',
            country: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $items = [
            new ShipmentItemData(
                name: 'Item 1',
                quantity: 2,
                weight: 500, // 2 * 500 = 1000g
                declaredValue: 1000
            ),
            new ShipmentItemData(
                name: 'Item 2',
                quantity: 1,
                weight: 750, // 1 * 750 = 750g
                declaredValue: 500
            ),
        ];

        $shipment = new ShipmentData(
            reference: 'TEST-SHIP-001',
            carrierCode: 'fedex',
            serviceCode: 'standard',
            origin: $origin,
            destination: $destination,
            items: $items
        );

        expect($shipment->getTotalWeight())->toBe(1750); // 1000 + 750
    });

    it('checks if shipment is cash on delivery', function (): void {
        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            line1: '123 Origin St',
            postcode: '12345',
            country: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            line1: '456 Dest St',
            postcode: '67890',
            country: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $codShipment = new ShipmentData(
            reference: 'TEST-SHIP-001',
            carrierCode: 'fedex',
            serviceCode: 'standard',
            origin: $origin,
            destination: $destination,
            codAmount: 1500
        );

        $regularShipment = new ShipmentData(
            reference: 'TEST-SHIP-002',
            carrierCode: 'fedex',
            serviceCode: 'standard',
            origin: $origin,
            destination: $destination
        );

        expect($codShipment->isCashOnDelivery())->toBeTrue();
        expect($regularShipment->isCashOnDelivery())->toBeFalse();
    });
});
