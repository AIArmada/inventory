<?php

declare(strict_types=1);

use AIArmada\Orders\OrdersServiceProvider;

describe('OrdersServiceProvider', function (): void {
    it('can be instantiated', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect($provider)->toBeInstanceOf(OrdersServiceProvider::class);
    });

    it('has register method', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(method_exists($provider, 'register'))->toBeTrue();
    });

    it('has boot method', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(method_exists($provider, 'boot'))->toBeTrue();
    });

    it('has registerPolicies method', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(method_exists($provider, 'registerPolicies'))->toBeTrue();
    });

    it('has registerEventListeners method', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(method_exists($provider, 'registerEventListeners'))->toBeTrue();
    });

    it('can call register method without errors', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(fn() => $provider->register())->not->toThrow(Exception::class);
    });

    it('can call boot method without errors', function (): void {
        $provider = new OrdersServiceProvider(app());
        expect(fn() => $provider->boot())->not->toThrow(Exception::class);
    });
});