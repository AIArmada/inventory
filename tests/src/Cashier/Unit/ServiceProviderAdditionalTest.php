<?php

declare(strict_types=1);

use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Cashier\Support\CartIntegrationRegistrar;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('CashierServiceProvider - Additional Coverage', function (): void {
    it('merges config from package', function (): void {
        $config = config('cashier');

        expect($config)->toBeArray()
            ->and($config)->toHaveKey('default');
    });

    it('registers GatewayManager as singleton', function (): void {
        $manager1 = app(GatewayManager::class);
        $manager2 = app(GatewayManager::class);

        expect($manager1)->toBe($manager2);
    });

    it('registers cashier alias', function (): void {
        $manager = app('cashier');

        expect($manager)->toBeInstanceOf(GatewayManager::class);
    });

    it('registers CartIntegrationRegistrar as singleton', function (): void {
        $registrar1 = app(CartIntegrationRegistrar::class);
        $registrar2 = app(CartIntegrationRegistrar::class);

        expect($registrar1)->toBe($registrar2);
    });

    it('provides expected services', function (): void {
        $provider = new CashierServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toContain(GatewayManager::class)
            ->and($provides)->toContain(CartIntegrationRegistrar::class)
            ->and($provides)->toContain('cashier');
    });

    describe('route registration', function (): void {
        it('registers routes by default', function (): void {
            // Routes should be registered when $registersRoutes is true
            expect(AIArmada\Cashier\Cashier::$registersRoutes)->toBeTrue();
        });
    });
});
