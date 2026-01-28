<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Contracts\StatusMapperInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Drivers\ManualShippingDriver;
use AIArmada\Shipping\Drivers\NullShippingDriver;
use AIArmada\Shipping\Facades\Shipping;
use AIArmada\Shipping\ShippingManager;

// ============================================
// ShippingManager Tests (using Laravel framework)
// ============================================

beforeEach(function (): void {
    // Configure shipping drivers
    config([
        'shipping.default' => 'manual',
        'shipping.drivers' => [
            'null' => ['driver' => 'null'],
            'manual' => ['driver' => 'manual'],
            'flat_rate' => ['driver' => 'flat_rate'],
        ],
    ]);

    $this->manager = new ShippingManager(app());
});

it('returns null driver by default', function (): void {
    $driver = $this->manager->driver('null');

    expect($driver)->toBeInstanceOf(ShippingDriverInterface::class);
    expect($driver)->toBeInstanceOf(NullShippingDriver::class);
    expect($driver->getCarrierCode())->toBe('null');
});

it('returns manual driver', function (): void {
    $driver = $this->manager->driver('manual');

    expect($driver)->toBeInstanceOf(ManualShippingDriver::class);
    expect($driver->getCarrierCode())->toBe('manual');
});

it('allows extending with custom drivers', function (): void {
    // Create a custom driver that extends the NullShippingDriver
    $customDriver = new class extends NullShippingDriver
    {
        public function getCarrierCode(): string
        {
            return 'custom';
        }
    };

    $this->manager->extend('custom', fn () => $customDriver);

    $driver = $this->manager->driver('custom');

    expect($driver->getCarrierCode())->toBe('custom');
});

it('caches driver instances', function (): void {
    $driver1 = $this->manager->driver('null');
    $driver2 = $this->manager->driver('null');

    expect($driver1)->toBe($driver2);
});

it('returns different instances for different drivers', function (): void {
    $nullDriver = $this->manager->driver('null');
    $manualDriver = $this->manager->driver('manual');

    expect($nullDriver)->not->toBe($manualDriver);
});

it('provides list of available drivers', function (): void {
    $drivers = $this->manager->getAvailableDrivers();

    expect($drivers)->toContain('null');
    expect($drivers)->toContain('manual');
    expect($drivers)->toContain('flat_rate');
});

it('checks if driver is available', function (): void {
    expect($this->manager->hasDriver('null'))->toBeTrue();
    expect($this->manager->hasDriver('manual'))->toBeTrue();
    expect($this->manager->hasDriver('nonexistent'))->toBeFalse();
});

it('provides default driver name', function (): void {
    $defaultName = $this->manager->getDefaultDriver();

    expect($defaultName)->toBe('manual');
});

it('throws exception for unsupported driver', function (): void {
    $this->manager->driver('unsupported_carrier');
})->throws(InvalidArgumentException::class);

it('can set default driver', function (): void {
    $this->manager->setDefaultDriver('null');

    expect($this->manager->getDefaultDriver())->toBe('null');
});

it('can register and retrieve status mapper', function (): void {
    $mapper = new class implements StatusMapperInterface
    {
        public function getCarrierCode(): string
        {
            return 'test_carrier';
        }

        public function map(string $carrierEventCode): AIArmada\Shipping\Enums\TrackingStatus
        {
            return AIArmada\Shipping\Enums\TrackingStatus::InTransit;
        }
    };

    $this->manager->registerStatusMapper($mapper);

    $retrieved = $this->manager->getStatusMapper('test_carrier');

    expect($retrieved)->toBe($mapper);
});

it('returns null for unregistered status mapper', function (): void {
    $mapper = $this->manager->getStatusMapper('nonexistent');

    expect($mapper)->toBeNull();
});

it('can get drivers for destination', function (): void {
    $destination = new AddressData(
        name: 'Test Destination',
        phone: '123-456-7890',
        line1: '123 Test St',
        postcode: '12345',
        country: 'US',
        city: 'Test City',
        state: 'TS'
    );

    // Both null and manual drivers should return true for servicesDestination by default
    $drivers = $this->manager->getDriversForDestination($destination);

    // At minimum, we should have drivers that service the destination
    expect($drivers)->toBeInstanceOf(Illuminate\Support\Collection::class);
});

it('supports dynamic method calls via facade', function (): void {
    // Test that the facade routes calls to the manager
    $driver = Shipping::driver('null');

    expect($driver)->toBeInstanceOf(NullShippingDriver::class);
});
