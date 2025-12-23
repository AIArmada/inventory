<?php

declare(strict_types=1);

use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;

uses(CashierTestCase::class);

describe('CashierServiceProvider', function (): void {
    it('registers the GatewayManager as a singleton', function (): void {
        $manager1 = $this->app->make(GatewayManager::class);
        $manager2 = $this->app->make(GatewayManager::class);

        expect($manager1)->toBe($manager2);
    });

    it('registers the cashier alias', function (): void {
        $manager = $this->app->make('cashier');

        expect($manager)->toBeInstanceOf(GatewayManager::class);
    });

    it('merges the configuration', function (): void {
        expect(config('cashier.default'))->toBe('stripe');
    });

    it('has the stripe gateway configured', function (): void {
        expect(config('cashier.gateways.stripe'))->toBeArray()
            ->and(config('cashier.gateways.stripe.secret'))->toBe('sk_test_xxx');
    });

    it('has the chip gateway configured', function (): void {
        expect(config('cashier.gateways.chip'))->toBeArray()
            ->and(config('cashier.gateways.chip.brand_id'))->toBe('test_brand_id');
    });
});
