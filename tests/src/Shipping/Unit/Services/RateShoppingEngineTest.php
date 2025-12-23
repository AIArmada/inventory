<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Services\RateShoppingEngine;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;

describe('RateShoppingEngine', function (): void {
    it('can get all rates from multiple carriers', function (): void {
        // Mock ShippingManager
        $shippingManager = Mockery::mock(ShippingManager::class);

        // Mock drivers
        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $upsDriver = Mockery::mock(ShippingDriverInterface::class);

        $fedexDriver->shouldReceive('getCarrierCode')->andReturn('fedex');
        $upsDriver->shouldReceive('getCarrierCode')->andReturn('ups');

        $fedexDriver->shouldReceive('servicesDestination')->andReturn(true);
        $upsDriver->shouldReceive('servicesDestination')->andReturn(true);

        $shippingManager->shouldReceive('hasDriver')->with('fedex')->andReturn(true);
        $shippingManager->shouldReceive('hasDriver')->with('ups')->andReturn(true);
        $shippingManager->shouldReceive('driver')->with('fedex')->andReturn($fedexDriver);
        $shippingManager->shouldReceive('driver')->with('ups')->andReturn($upsDriver);

        // Mock Concurrency to return the rates directly
        $fedexRates = collect([
            new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1500, currency: 'USD', estimatedDays: 3),
            new RateQuoteData(carrier: 'fedex', service: 'express', rate: 2500, currency: 'USD', estimatedDays: 1),
        ]);

        $upsRates = collect([
            new RateQuoteData(carrier: 'ups', service: 'ground', rate: 1400, currency: 'USD', estimatedDays: 4),
        ]);

        Concurrency::shouldReceive('run')
            ->once()
            ->andReturn([
                'fedex' => $fedexRates,
                'ups' => $upsRates,
            ]);

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0, // Disable caching for tests
            'strategy' => 'cheapest',
        ]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $shippingManager->shouldReceive('getDriversForDestination')
            ->with($destination)
            ->andReturn(collect([$fedexDriver, $upsDriver]));

        $rates = $engine->getAllRates($origin, $destination, $packages);

        expect($rates)->toBeInstanceOf(Collection::class);
        expect($rates)->toHaveCount(3);
        expect($rates->first()->carrier)->toBe('ups'); // Should be sorted by rate
        expect($rates->first()->rate)->toBe(1400);
    });

    it('can get best rate using selection strategy', function (): void {
        // Mock ShippingManager
        $shippingManager = Mockery::mock(ShippingManager::class);

        // Mock drivers
        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $upsDriver = Mockery::mock(ShippingDriverInterface::class);

        $fedexDriver->shouldReceive('getCarrierCode')->andReturn('fedex');
        $upsDriver->shouldReceive('getCarrierCode')->andReturn('ups');

        $fedexDriver->shouldReceive('servicesDestination')->andReturn(true);
        $upsDriver->shouldReceive('servicesDestination')->andReturn(true);

        $shippingManager->shouldReceive('hasDriver')->with('fedex')->andReturn(true);
        $shippingManager->shouldReceive('hasDriver')->with('ups')->andReturn(true);
        $shippingManager->shouldReceive('driver')->with('fedex')->andReturn($fedexDriver);
        $shippingManager->shouldReceive('driver')->with('ups')->andReturn($upsDriver);

        // Mock Concurrency
        Concurrency::shouldReceive('run')
            ->once()
            ->andReturn([
                'fedex' => collect([
                    new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1500, currency: 'USD', estimatedDays: 3),
                ]),
                'ups' => collect([
                    new RateQuoteData(carrier: 'ups', service: 'ground', rate: 1400, currency: 'USD', estimatedDays: 4),
                ]),
            ]);

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0, // Disable caching for tests
            'strategy' => 'cheapest',
        ]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $shippingManager->shouldReceive('getDriversForDestination')
            ->with($destination)
            ->andReturn(collect([$fedexDriver, $upsDriver]));

        $bestRate = $engine->getBestRate($origin, $destination, $packages);

        expect($bestRate)->toBeInstanceOf(RateQuoteData::class);
        expect($bestRate->carrier)->toBe('ups'); // Cheapest strategy
        expect($bestRate->rate)->toBe(1400);
    });

    it('can set custom strategy', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);
        $engine = new RateShoppingEngine($shippingManager, ['cache_ttl' => 0]);

        $strategy = Mockery::mock(AIArmada\Shipping\Contracts\RateSelectionStrategyInterface::class);
        $strategy->shouldReceive('select')->andReturn(null);

        $result = $engine->setStrategy($strategy);

        expect($result)->toBe($engine);
    });

    it('can clear cache', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);
        $engine = new RateShoppingEngine($shippingManager, ['cache_ttl' => 0]);

        Cache::shouldReceive('store')->andReturnSelf();
        Cache::shouldReceive('getStore')->andReturn(new class {});
        // Non-taggable stores are a no-op; ensure we do not globally flush.
        Cache::shouldReceive('flush')->never();

        $engine->clearCache();
    });

    it('returns fallback rate when no carriers available', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $manualDriver = Mockery::mock(ShippingDriverInterface::class);
        $manualDriver->shouldReceive('getCarrierCode')->andReturn('manual');
        $manualDriver->shouldReceive('servicesDestination')->andReturn(true);

        $shippingManager->shouldReceive('hasDriver')->with('manual')->andReturn(true);
        $shippingManager->shouldReceive('driver')->with('manual')->andReturn($manualDriver);

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0,
            'fallback_to_manual' => true,
        ]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $fallbackRates = collect([
            new RateQuoteData(carrier: 'manual', service: 'standard', rate: 500, currency: 'USD', estimatedDays: 7),
        ]);

        $manualDriver->shouldReceive('getRates')
            ->andReturn($fallbackRates);

        $shippingManager->shouldReceive('getDriversForDestination')
            ->with($destination)
            ->andReturn(collect()); // No drivers available

        $bestRate = $engine->getBestRate($origin, $destination, $packages);

        expect($bestRate)->toBeInstanceOf(RateQuoteData::class);
        expect($bestRate->carrier)->toBe('manual');
        expect($bestRate->rate)->toBe(500);
    });

    it('returns null when fallback is disabled and no carriers available', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0,
            'fallback_to_manual' => false,
        ]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $shippingManager->shouldReceive('getDriversForDestination')
            ->with($destination)
            ->andReturn(collect()); // No drivers available

        $bestRate = $engine->getBestRate($origin, $destination, $packages);

        expect($bestRate)->toBeNull();
    });

    it('uses fastest strategy when configured', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $fedexDriver->shouldReceive('getCarrierCode')->andReturn('fedex');

        Concurrency::shouldReceive('run')
            ->once()
            ->andReturn([
                'fedex' => collect([
                    new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1000, currency: 'USD', estimatedDays: 5),
                    new RateQuoteData(carrier: 'fedex', service: 'express', rate: 2500, currency: 'USD', estimatedDays: 1),
                ]),
            ]);

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0,
            'strategy' => 'fastest',
        ]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $shippingManager->shouldReceive('getDriversForDestination')
            ->with($destination)
            ->andReturn(collect([$fedexDriver]));

        $bestRate = $engine->getBestRate($origin, $destination, $packages);

        expect($bestRate)->toBeInstanceOf(RateQuoteData::class);
        expect($bestRate->service)->toBe('express'); // Fastest (1 day)
        expect($bestRate->estimatedDays)->toBe(1);
    });

    it('uses preferred carrier strategy when configured', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $upsDriver = Mockery::mock(ShippingDriverInterface::class);
        $fedexDriver->shouldReceive('getCarrierCode')->andReturn('fedex');
        $upsDriver->shouldReceive('getCarrierCode')->andReturn('ups');

        Concurrency::shouldReceive('run')
            ->once()
            ->andReturn([
                'fedex' => collect([
                    new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1500, currency: 'USD', estimatedDays: 3),
                ]),
                'ups' => collect([
                    new RateQuoteData(carrier: 'ups', service: 'ground', rate: 1000, currency: 'USD', estimatedDays: 4),
                ]),
            ]);

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0,
            'strategy' => 'preferred',
            'carrier_priority' => ['fedex' => 1, 'ups' => 2], // Prefer FedEx (lower number = higher priority)
        ]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $shippingManager->shouldReceive('getDriversForDestination')
            ->with($destination)
            ->andReturn(collect([$fedexDriver, $upsDriver]));

        $bestRate = $engine->getBestRate($origin, $destination, $packages);

        expect($bestRate)->toBeInstanceOf(RateQuoteData::class);
        expect($bestRate->carrier)->toBe('fedex'); // Preferred carrier even though UPS is cheaper
    });

    it('uses caching when cache_ttl is positive', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $fedexDriver->shouldReceive('getCarrierCode')->andReturn('fedex');

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 300,
            'strategy' => 'cheapest',
        ]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $rates = collect([
            new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1000, currency: 'USD', estimatedDays: 3),
        ]);

        $cacheRepository = Mockery::mock(Illuminate\Contracts\Cache\Repository::class);
        $cacheRepository->shouldReceive('getStore')
            ->andReturn(new stdClass); // Not taggable

        // Cache::store()->remember should be called
        Cache::shouldReceive('store')
            ->once()
            ->andReturn($cacheRepository);

        $cacheRepository->shouldReceive('remember')
            ->once()
            ->andReturn($rates);

        $shippingManager->shouldReceive('getDriversForDestination')
            ->never(); // Should not be called because cache handles it

        $result = $engine->getAllRates($origin, $destination, $packages);

        expect($result)->toHaveCount(1);
    });

    it('gets rates from specific carriers only', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $fedexDriver->shouldReceive('servicesDestination')->andReturn(true);
        $fedexDriver->shouldReceive('getRates')->andReturn(collect([
            new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1500, currency: 'USD', estimatedDays: 3),
        ]));

        $shippingManager->shouldReceive('hasDriver')->with('fedex')->andReturn(true);
        $shippingManager->shouldReceive('hasDriver')->with('ups')->andReturn(false); // UPS not available
        $shippingManager->shouldReceive('driver')->with('fedex')->andReturn($fedexDriver);

        $engine = new RateShoppingEngine($shippingManager, ['cache_ttl' => 0]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $rates = $engine->getRatesFromCarriers(['fedex', 'ups'], $origin, $destination, $packages);

        expect($rates)->toHaveCount(1);
        expect($rates->first()->carrier)->toBe('fedex');
    });

    it('skips carriers that do not service destination in getRatesFromCarriers', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $fedexDriver->shouldReceive('servicesDestination')->andReturn(false); // Does not service destination

        $shippingManager->shouldReceive('hasDriver')->with('fedex')->andReturn(true);
        $shippingManager->shouldReceive('driver')->with('fedex')->andReturn($fedexDriver);

        $engine = new RateShoppingEngine($shippingManager, ['cache_ttl' => 0]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        $rates = $engine->getRatesFromCarriers(['fedex'], $origin, $destination, $packages);

        expect($rates)->toBeEmpty();
    });

    it('handles carrier errors in getRatesFromCarriers gracefully', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $fedexDriver->shouldReceive('servicesDestination')->andReturn(true);
        $fedexDriver->shouldReceive('getRates')->andThrow(new Exception('API Error'));

        $shippingManager->shouldReceive('hasDriver')->with('fedex')->andReturn(true);
        $shippingManager->shouldReceive('driver')->with('fedex')->andReturn($fedexDriver);

        $engine = new RateShoppingEngine($shippingManager, ['cache_ttl' => 0]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Destination',
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

        // Should not throw, just return empty
        $rates = $engine->getRatesFromCarriers(['fedex'], $origin, $destination, $packages);

        expect($rates)->toBeEmpty();
    });

    it('falls back to sequential rate fetching when options are not concurrency-safe', function (): void {
        $shippingManager = Mockery::mock(ShippingManager::class);

        $fedexDriver = Mockery::mock(ShippingDriverInterface::class);
        $fedexDriver->shouldReceive('getCarrierCode')->andReturn('fedex');
        $fedexDriver->shouldReceive('servicesDestination')->andReturn(true);
        $fedexDriver->shouldReceive('getRates')->andReturn(collect([
            new RateQuoteData(carrier: 'fedex', service: 'ground', rate: 1500, currency: 'USD', estimatedDays: 3),
        ]));

        $shippingManager->shouldReceive('getDriversForDestination')->andReturn(collect([$fedexDriver]));
        $shippingManager->shouldReceive('hasDriver')->with('fedex')->andReturn(true);
        $shippingManager->shouldReceive('driver')->with('fedex')->andReturn($fedexDriver);

        Concurrency::shouldReceive('run')->never();

        $engine = new RateShoppingEngine($shippingManager, [
            'cache_ttl' => 0,
        ]);

        $origin = new AddressData(
            name: 'Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US'
        );

        $destination = new AddressData(
            name: 'Destination',
            phone: '987-654-3210',
            address: '456 Dest St',
            postCode: '67890',
            countryCode: 'US'
        );

        $packages = [new PackageData(1000, 10, 5, 5, 500, 'box', 1)];

        $unsafeOptions = ['note' => (object) ['x' => 1]];

        $rates = $engine->getAllRates($origin, $destination, $packages, $unsafeOptions);

        expect($rates)->toHaveCount(1);
        expect($rates->first()->carrier)->toBe('fedex');
    });
});
