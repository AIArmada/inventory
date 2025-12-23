<?php

declare(strict_types=1);

use AIArmada\Shipping\Actions\CalculateShippingRate;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\ShippingManager;

describe('CalculateShippingRate Action', function (): void {
    it('can calculate rates for a specific carrier', function (): void {
        $action = app(CalculateShippingRate::class);

        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            address: '456 Dest St',
            postCode: '67890',
            countryCode: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $rates = $action->handle($origin, $destination, $packages, 'null');

        expect($rates)->toBeInstanceOf(Illuminate\Support\Collection::class);
        expect($rates)->toHaveCount(2);

        $standardRate = $rates->first();
        expect($standardRate)->toBeInstanceOf(AIArmada\Shipping\Data\RateQuoteData::class);
        expect($standardRate->carrier)->toBe('null');
        expect($standardRate->service)->toBe('standard');
    });

    it('can calculate rates from all carriers when no carrier specified', function (): void {
        // Isolate from any custom drivers registered by other packages.
        app()->instance(ShippingManager::class, new ShippingManager(app()));

        // Ensure the expected built-in drivers are configured for this test.
        config([
            'shipping.drivers' => [
                'manual' => [
                    'driver' => 'manual',
                    'name' => 'Manual Shipping',
                    'default_rate' => 1000,
                    'estimated_days' => 3,
                    'free_shipping_threshold' => null,
                ],
                'flat_rate' => [
                    'driver' => 'flat_rate',
                    'name' => 'Flat Rate Shipping',
                    'rates' => [
                        'standard' => [
                            'name' => 'Standard Delivery',
                            'rate' => 800,
                            'estimated_days' => 3,
                        ],
                        'express' => [
                            'name' => 'Express Delivery',
                            'rate' => 1500,
                            'estimated_days' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $action = app(CalculateShippingRate::class);

        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            address: '456 Dest St',
            postCode: '67890',
            countryCode: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $rates = $action->handle($origin, $destination, $packages);

        expect($rates)->toBeInstanceOf(Illuminate\Support\Collection::class);
        expect($rates)->toHaveCount(3); // manual (1) + flat_rate (2)
    });

    it('handles multiple packages correctly', function (): void {
        $action = app(CalculateShippingRate::class);

        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            address: '456 Dest St',
            postCode: '67890',
            countryCode: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
            new PackageData(2000, 15, 10, 8, 1000, 'box', 2),
        ];

        $rates = $action->handle($origin, $destination, $packages, 'null');

        expect($rates)->toHaveCount(2);
        $rate = $rates->first();
        expect($rate->rate)->toBe(0); // Null driver returns 0
    });

    it('passes options to the driver', function (): void {
        $action = app(CalculateShippingRate::class);

        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            address: '456 Dest St',
            postCode: '67890',
            countryCode: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $packages = [
            new PackageData(1000, 10, 5, 5, 500, 'box', 1),
        ];

        $options = ['insurance' => true, 'signature' => true];

        $rates = $action->handle($origin, $destination, $packages, 'null', $options);

        expect($rates)->toHaveCount(2);
    });

    it('skips carriers that throw exceptions', function (): void {
        // Isolate from any custom drivers registered by other packages.
        app()->instance(ShippingManager::class, new ShippingManager(app()));

        // Configure drivers including one that will fail
        config(['shipping.drivers' => [
            'manual' => [],
            'failing_carrier' => [], // No creator method exists -> driver() throws
        ]]);

        $action = app(CalculateShippingRate::class);

        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            address: '456 Dest St',
            postCode: '67890',
            countryCode: 'US',
        );

        $packages = [
            new PackageData(1000),
        ];

        // Should not throw, just skip failing carrier
        $rates = $action->handle($origin, $destination, $packages);

        expect($rates)->toBeInstanceOf(Illuminate\Support\Collection::class);
        // Only manual driver rates should be included
        expect($rates)->toHaveCount(1);
        expect($rates->first()?->carrier)->toBe('manual');
    });
});
