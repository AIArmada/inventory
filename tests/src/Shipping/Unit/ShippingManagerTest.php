<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Drivers\ManualShippingDriver;
use AIArmada\Shipping\Drivers\NullShippingDriver;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Contracts\Foundation\Application;

// ============================================
// ShippingManager Tests
// ============================================

beforeEach(function (): void {
    $this->app = Mockery::mock(Application::class);
    $this->app->shouldReceive('make')->andReturnUsing(function ($class) {
        if ($class === NullShippingDriver::class) {
            return new NullShippingDriver();
        }
        if ($class === ManualShippingDriver::class) {
            return new ManualShippingDriver();
        }

        return null;
    });

    // Mock config repository
    $config = Mockery::mock(Illuminate\Contracts\Config\Repository::class);
    $config->shouldReceive('get')->with('shipping.drivers', [])->andReturn([
        'null' => ['driver' => 'null'],
        'manual' => ['driver' => 'manual'],
        'flat_rate' => ['driver' => 'flat_rate'],
    ]);
    $config->shouldReceive('get')->with('shipping.default', 'manual')->andReturn('manual');
    $config->shouldReceive('get')->with('shipping.drivers.manual', [])->andReturn([]);
    $config->shouldReceive('get')->with('shipping.drivers.flat_rate', [])->andReturn([]);
    $config->shouldReceive('has')->with(Mockery::pattern('/^shipping\.drivers\./'))->andReturnUsing(function ($key) {
        return in_array($key, [
            'shipping.drivers.null',
            'shipping.drivers.manual',
            'shipping.drivers.flat_rate',
        ]);
    });

    $this->app->shouldReceive('get')->with('config')->andReturn($config);

    $this->manager = new ShippingManager($this->app);
});

afterEach(function (): void {
    Mockery::close();
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
    $customDriver = Mockery::mock(ShippingDriverInterface::class);
    $customDriver->shouldReceive('getCarrierCode')->andReturn('custom');

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
