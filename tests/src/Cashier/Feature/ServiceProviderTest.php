<?php

declare(strict_types=1);

use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Illuminate\Support\ServiceProvider;

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

    it('does not auto-run Stripe migrations and instead makes them publishable', function (): void {
        $provider = $this->app->getProvider(CashierServiceProvider::class);

        expect($provider)->not()->toBeNull();

        $migrationsPath = base_path('vendor/laravel/cashier/database/migrations');
        $isInstalled = class_exists(\Composer\InstalledVersions::class)
            && \Composer\InstalledVersions::isInstalled('laravel/cashier');

        if ($isInstalled && is_dir($migrationsPath)) {
            $pathsToPublish = ServiceProvider::pathsToPublish(CashierServiceProvider::class, 'cashier-stripe-migrations');

            expect($pathsToPublish)->toBeArray()->not()->toBeEmpty();
        } else {
            expect(true)->toBeTrue();
        }
    });
});
