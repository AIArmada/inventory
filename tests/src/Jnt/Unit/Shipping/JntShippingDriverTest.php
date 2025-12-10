<?php

declare(strict_types=1);

use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Jnt\Services\JntTrackingService;
use AIArmada\Jnt\Shipping\JntShippingDriver;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Enums\DriverCapability;

// ============================================
// JntShippingDriver Tests
// ============================================

beforeEach(function (): void {
    $this->jntService = Mockery::mock(JntExpressService::class);
    $this->trackingService = Mockery::mock(JntTrackingService::class);
    $this->statusMapper = new JntStatusMapper;

    $this->driver = new JntShippingDriver(
        $this->jntService,
        $this->trackingService,
        $this->statusMapper
    );
});

afterEach(function (): void {
    Mockery::close();
});

describe('interface implementation', function (): void {
    it('implements ShippingDriverInterface', function (): void {
        expect($this->driver)->toBeInstanceOf(ShippingDriverInterface::class);
    });
});

describe('carrier identification', function (): void {
    it('returns correct carrier code', function (): void {
        expect($this->driver->getCarrierCode())->toBe('jnt');
    });

    it('returns correct carrier name', function (): void {
        expect($this->driver->getCarrierName())->toBe('J&T Express');
    });
});

describe('capability support', function (): void {
    it('supports rate quotes', function (): void {
        expect($this->driver->supports(DriverCapability::RateQuotes))->toBeTrue();
    });

    it('supports label generation', function (): void {
        expect($this->driver->supports(DriverCapability::LabelGeneration))->toBeTrue();
    });

    it('supports tracking', function (): void {
        expect($this->driver->supports(DriverCapability::Tracking))->toBeTrue();
    });

    it('supports webhooks', function (): void {
        expect($this->driver->supports(DriverCapability::Webhooks))->toBeTrue();
    });

    it('supports cash on delivery', function (): void {
        expect($this->driver->supports(DriverCapability::CashOnDelivery))->toBeTrue();
    });

    it('supports batch operations', function (): void {
        expect($this->driver->supports(DriverCapability::BatchOperations))->toBeTrue();
    });

    it('supports pickup scheduling', function (): void {
        expect($this->driver->supports(DriverCapability::PickupScheduling))->toBeTrue();
    });

    it('does not support returns', function (): void {
        expect($this->driver->supports(DriverCapability::Returns))->toBeFalse();
    });

    it('does not support address validation', function (): void {
        expect($this->driver->supports(DriverCapability::AddressValidation))->toBeFalse();
    });

    it('does not support multi package', function (): void {
        expect($this->driver->supports(DriverCapability::MultiPackage))->toBeFalse();
    });

    it('does not support international shipping', function (): void {
        expect($this->driver->supports(DriverCapability::InternationalShipping))->toBeFalse();
    });
});

describe('available methods', function (): void {
    it('returns available shipping methods', function (): void {
        $methods = $this->driver->getAvailableMethods();

        expect($methods)->toHaveCount(2);
        expect($methods->first()->code)->toBe('EZ');
        expect($methods->last()->code)->toBe('EXPRESS');
    });

    it('methods have tracking available', function (): void {
        $methods = $this->driver->getAvailableMethods();

        foreach ($methods as $method) {
            expect($method->trackingAvailable)->toBeTrue();
        }
    });
});

describe('getRates', function (): void {
    it('returns rate quotes for packages', function (): void {
        $origin = new AddressData(
            name: 'Sender',
            phone: '+60123456789',
            address: '123 Main St',
            city: 'Kuala Lumpur',
            state: 'WP Kuala Lumpur',
            postCode: '50000',
            countryCode: 'MYS'
        );

        $destination = new AddressData(
            name: 'Receiver',
            phone: '+60198765432',
            address: '456 Second St',
            city: 'Petaling Jaya',
            state: 'Selangor',
            postCode: '47810',
            countryCode: 'MYS'
        );

        $packages = [
            new PackageData(weight: 1000, length: 10, width: 10, height: 10, quantity: 1),
        ];

        $rates = $this->driver->getRates($origin, $destination, $packages);

        expect($rates)->not->toBeEmpty();
        expect($rates->first()->carrier)->toBe('jnt');
        expect($rates->first()->service)->toBe('EZ');
        expect($rates->first()->currency)->toBe('MYR');
    });

    it('applies higher rate for East Malaysia', function (): void {
        $origin = new AddressData(
            name: 'Sender',
            phone: '+60123456789',
            address: '123 Main St',
            postCode: '50000',
            countryCode: 'MYS'
        );

        $destinationWest = new AddressData(
            name: 'Receiver',
            phone: '+60198765432',
            address: '456 Second St',
            postCode: '50000', // West Malaysia
            countryCode: 'MYS'
        );

        $destinationEast = new AddressData(
            name: 'Receiver',
            phone: '+60198765432',
            address: '456 Second St',
            postCode: '88000', // Sabah
            countryCode: 'MYS'
        );

        $packages = [
            new PackageData(weight: 1000, length: 10, width: 10, height: 10, quantity: 1),
        ];

        $ratesWest = $this->driver->getRates($origin, $destinationWest, $packages);
        $ratesEast = $this->driver->getRates($origin, $destinationEast, $packages);

        expect($ratesEast->first()->rate)->toBeGreaterThan($ratesWest->first()->rate);
    });
});

describe('servicesDestination', function (): void {
    it('services Malaysia (MY)', function (): void {
        $address = new AddressData(
            name: 'Test',
            phone: '+60123456789',
            address: '123 Test St',
            postCode: '50000',
            countryCode: 'MY'
        );

        expect($this->driver->servicesDestination($address))->toBeTrue();
    });

    it('services Malaysia (MYS)', function (): void {
        $address = new AddressData(
            name: 'Test',
            phone: '+60123456789',
            address: '123 Test St',
            postCode: '50000',
            countryCode: 'MYS'
        );

        expect($this->driver->servicesDestination($address))->toBeTrue();
    });

    it('does not service other countries', function (): void {
        $address = new AddressData(
            name: 'Test',
            phone: '+65123456789',
            address: '123 Test St',
            postCode: '123456',
            countryCode: 'SGP'
        );

        expect($this->driver->servicesDestination($address))->toBeFalse();
    });
});

describe('validateAddress', function (): void {
    it('returns valid result with warning', function (): void {
        $address = new AddressData(
            name: 'Test',
            phone: '+60123456789',
            address: '123 Test St',
            postCode: '50000',
            countryCode: 'MYS'
        );

        $result = $this->driver->validateAddress($address);

        expect($result->valid)->toBeTrue();
        expect($result->warnings)->toContain('Address validation not available for J&T Express.');
    });
});
