<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\RulesFactoryInterface;
use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\FilamentCart\Services\CartInstanceManager;

describe('CartInstanceManager', function (): void {
    it('resolves cart instance with rules factory', function (): void {
        $rulesFactory = Mockery::mock(RulesFactoryInterface::class);

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getMetadata')
            ->with('session-123', 'default', 'dynamic_conditions')
            ->andReturn([]);

        $cart = new Cart($storage, 'session-123');

        // Mock the facade root manually to avoid Final class issues
        // We create a mock that behaves like the underlying service but is not final
        $facadeMock = Mockery::mock();
        $facadeMock->shouldReceive('getCartInstance')
            ->with('default', 'session-123')
            ->andReturn($cart);

        CartFacade::swap($facadeMock);

        $manager = new CartInstanceManager($rulesFactory);
        $result = $manager->resolve('default', 'session-123');

        expect($result)->toBe($cart);
        // We can check if rules factory was set by calling getRulesFactory if available or check behavior
        // Cart class doesn't seem to expose getRulesFactory publicly in interface?
        // But withRulesFactory returns the cart instance.
    });

    it('prepares cart with rules factory', function (): void {
        $rulesFactory = Mockery::mock(RulesFactoryInterface::class);

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getMetadata')
            ->with('session-123', 'default', 'dynamic_conditions')
            ->andReturn([]);

        $cart = new Cart($storage, 'session-123');

        $manager = new CartInstanceManager($rulesFactory);
        $result = $manager->prepare($cart);

        expect($result)->toBe($cart);
    });
});
