<?php

declare(strict_types=1);

use AIArmada\Cashier\Contracts\GatewayContract;
use AIArmada\Cashier\Exceptions\GatewayNotFoundException;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('GatewayManager - Additional Coverage', function (): void {
    describe('dynamic method calls', function (): void {
        it('proxies method calls to default driver', function (): void {
            $manager = app(GatewayManager::class);

            // name() should be proxied to the default driver
            $name = $manager->name();

            expect($name)->toBe('stripe');
        });
    });

    describe('getDefaultDriver', function (): void {
        it('returns configured default gateway', function (): void {
            $manager = app(GatewayManager::class);

            expect($manager->getDefaultDriver())->toBe('stripe');
        });
    });

    describe('gateway', function (): void {
        it('returns gateway for null parameter', function (): void {
            $manager = app(GatewayManager::class);
            $gateway = $manager->gateway(null);

            expect($gateway)->toBeInstanceOf(GatewayContract::class)
                ->and($gateway->name())->toBe('stripe');
        });

        it('returns gateway for explicit name', function (): void {
            $manager = app(GatewayManager::class);
            $gateway = $manager->gateway('chip');

            expect($gateway)->toBeInstanceOf(GatewayContract::class)
                ->and($gateway->name())->toBe('chip');
        });
    });

    describe('supportedGateways', function (): void {
        it('returns array of configured gateway names', function (): void {
            $manager = app(GatewayManager::class);
            $gateways = $manager->supportedGateways();

            expect($gateways)->toBeArray()
                ->and($gateways)->toContain('stripe')
                ->and($gateways)->toContain('chip');
        });
    });

    describe('supportsGateway', function (): void {
        it('returns true for configured gateways', function (): void {
            $manager = app(GatewayManager::class);

            expect($manager->supportsGateway('stripe'))->toBeTrue()
                ->and($manager->supportsGateway('chip'))->toBeTrue();
        });

        it('returns false for unconfigured gateways', function (): void {
            $manager = app(GatewayManager::class);

            expect($manager->supportsGateway('paypal'))->toBeFalse()
                ->and($manager->supportsGateway('braintree'))->toBeFalse();
        });
    });

    describe('getGatewayConfig', function (): void {
        it('returns configuration array for gateway', function (): void {
            $manager = app(GatewayManager::class);
            $config = $manager->getGatewayConfig('stripe');

            expect($config)->toBeArray()
                ->and($config)->toHaveKey('driver')
                ->and($config)->toHaveKey('secret')
                ->and($config)->toHaveKey('webhook_secret');
        });

        it('returns empty array for unconfigured gateway', function (): void {
            $manager = app(GatewayManager::class);
            $config = $manager->getGatewayConfig('nonexistent');

            expect($config)->toBeArray()
                ->and($config)->toBeEmpty();
        });
    });

    describe('driver', function (): void {
        it('returns same instance for same driver', function (): void {
            $manager = app(GatewayManager::class);

            $driver1 = $manager->driver('stripe');
            $driver2 = $manager->driver('stripe');

            expect($driver1)->toBe($driver2);
        });
    });

    describe('custom driver extension', function (): void {
        it('can be extended via extend method', function (): void {
            $manager = app(GatewayManager::class);

            // Verify extend method exists
            expect(method_exists($manager, 'extend'))->toBeTrue();
        });
    });

    describe('buildGateway exception', function (): void {
        it('throws GatewayNotFoundException when gateway class does not exist', function (): void {
            $manager = app(GatewayManager::class);

            // Use reflection to test the protected buildGateway method
            $reflection = new ReflectionClass($manager);
            $method = $reflection->getMethod('buildGateway');
            $method->setAccessible(true);

            expect(fn () => $method->invoke($manager, 'test', 'NonExistent\\Gateway\\Class', []))
                ->toThrow(GatewayNotFoundException::class, 'Gateway class [NonExistent\\Gateway\\Class] not found');
        });
    });
});
