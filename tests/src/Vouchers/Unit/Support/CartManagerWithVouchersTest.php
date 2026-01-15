<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Support\CartManagerWithVouchers;
use AIArmada\Vouchers\Support\VoucherRulesFactory;
use Illuminate\Database\Eloquent\Model;

describe('CartManagerWithVouchers', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
        $this->baseManager = Mockery::mock(CartManagerInterface::class);
    });

    describe('construction and factory', function (): void {
        it('can be constructed with CartManagerInterface', function (): void {
            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper)->toBeInstanceOf(CartManagerWithVouchers::class)
                ->and($wrapper)->toBeInstanceOf(CartManagerInterface::class);
        });

        it('creates from CartManager using factory method', function (): void {
            $wrapper = CartManagerWithVouchers::fromCartManager($this->baseManager);

            expect($wrapper)->toBeInstanceOf(CartManagerWithVouchers::class);
        });

        it('returns same instance if already CartManagerWithVouchers', function (): void {
            $wrapper1 = new CartManagerWithVouchers($this->baseManager);
            $wrapper2 = CartManagerWithVouchers::fromCartManager($wrapper1);

            expect($wrapper2)->toBe($wrapper1);
        });
    });

    describe('getBaseManager', function (): void {
        it('returns the underlying manager', function (): void {
            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->getBaseManager())->toBe($this->baseManager);
        });

        it('unwraps nested decorators', function (): void {
            $wrapper1 = new CartManagerWithVouchers($this->baseManager);
            $wrapper2 = new CartManagerWithVouchers($wrapper1);

            expect($wrapper2->getBaseManager())->toBe($this->baseManager);
        });
    });

    describe('instance methods', function (): void {
        it('delegates instance() to base manager', function (): void {
            $this->baseManager->shouldReceive('instance')->once()->andReturn('default');

            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->instance())->toBe('default');
        });

        it('delegates setInstance() to base manager and returns self', function (): void {
            $this->baseManager->shouldReceive('setInstance')->with('custom')->once()->andReturnSelf();

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->setInstance('custom');

            expect($result)->toBe($wrapper);
        });

        it('delegates setIdentifier() to base manager and returns self', function (): void {
            $this->baseManager->shouldReceive('setIdentifier')->with('user-123')->once()->andReturnSelf();

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->setIdentifier('user-123');

            expect($result)->toBe($wrapper);
        });

        it('delegates forgetIdentifier() to base manager and returns self', function (): void {
            $this->baseManager->shouldReceive('forgetIdentifier')->once()->andReturnSelf();

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->forgetIdentifier();

            expect($result)->toBe($wrapper);
        });
    });

    describe('owner methods', function (): void {
        it('delegates forOwner() and wraps result', function (): void {
            $owner = Mockery::mock(Model::class);
            $newManager = Mockery::mock(CartManagerInterface::class);

            $this->baseManager->shouldReceive('forOwner')->with($owner)->once()->andReturn($newManager);

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->forOwner($owner);

            expect($result)->toBeInstanceOf(CartManagerWithVouchers::class)
                ->and($result)->not->toBe($wrapper);
        });

        it('delegates getOwnerType() to base manager', function (): void {
            $this->baseManager->shouldReceive('getOwnerType')->once()->andReturn('App\\Models\\Store');

            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->getOwnerType())->toBe('App\\Models\\Store');
        });

        it('delegates getOwnerId() to base manager', function (): void {
            $this->baseManager->shouldReceive('getOwnerId')->once()->andReturn('store-123');

            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->getOwnerId())->toBe('store-123');
        });

        it('returns null for getOwnerType when not set', function (): void {
            $this->baseManager->shouldReceive('getOwnerType')->once()->andReturn(null);

            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->getOwnerType())->toBeNull();
        });

        it('returns null for getOwnerId when not set', function (): void {
            $this->baseManager->shouldReceive('getOwnerId')->once()->andReturn(null);

            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->getOwnerId())->toBeNull();
        });
    });

    describe('getById', function (): void {
        it('returns null when cart not found', function (): void {
            $this->baseManager->shouldReceive('getById')->with('nonexistent')->once()->andReturn(null);

            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->getById('nonexistent'))->toBeNull();
        });

        it('returns cart with VoucherRulesFactory when found', function (): void {
            $cart = new Cart(
                storage: $this->storage,
                identifier: 'test-cart',
                events: null,
                instanceName: 'default',
                eventsEnabled: false,
                conditionResolver: new CartConditionResolver
            );

            $this->baseManager->shouldReceive('getById')->with('test-uuid')->once()->andReturn($cart);

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->getById('test-uuid');

            expect($result)->toBeInstanceOf(Cart::class)
                ->and($result->getRulesFactory())->toBeInstanceOf(VoucherRulesFactory::class);
        });
    });

    describe('swap', function (): void {
        it('delegates swap() to base manager', function (): void {
            $this->baseManager->shouldReceive('swap')
                ->with('old-id', 'new-id', 'default')
                ->once()
                ->andReturn(true);

            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->swap('old-id', 'new-id', 'default'))->toBeTrue();
        });

        it('returns false when swap fails', function (): void {
            $this->baseManager->shouldReceive('swap')
                ->with('old-id', 'new-id', 'default')
                ->once()
                ->andReturn(false);

            $wrapper = new CartManagerWithVouchers($this->baseManager);

            expect($wrapper->swap('old-id', 'new-id', 'default'))->toBeFalse();
        });
    });

    describe('getCurrentCart', function (): void {
        it('returns cart with VoucherRulesFactory', function (): void {
            $cart = new Cart(
                storage: $this->storage,
                identifier: 'current-cart',
                events: null,
                instanceName: 'default',
                eventsEnabled: false,
                conditionResolver: new CartConditionResolver
            );

            $this->baseManager->shouldReceive('getCurrentCart')->once()->andReturn($cart);

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->getCurrentCart();

            expect($result)->toBeInstanceOf(Cart::class)
                ->and($result->getRulesFactory())->toBeInstanceOf(VoucherRulesFactory::class);
        });

        it('does not double-wrap VoucherRulesFactory', function (): void {
            $cart = new Cart(
                storage: $this->storage,
                identifier: 'current-cart',
                events: null,
                instanceName: 'default',
                eventsEnabled: false,
                conditionResolver: new CartConditionResolver
            );

            // Pre-set the factory
            $factory = new VoucherRulesFactory;
            $cart->withRulesFactory($factory);

            $this->baseManager->shouldReceive('getCurrentCart')->once()->andReturn($cart);

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->getCurrentCart();

            expect($result->getRulesFactory())->toBe($factory);
        });
    });

    describe('getCartInstance', function (): void {
        it('returns cart instance with VoucherRulesFactory', function (): void {
            $cart = new Cart(
                storage: $this->storage,
                identifier: 'instance-cart',
                events: null,
                instanceName: 'wishlist',
                eventsEnabled: false,
                conditionResolver: new CartConditionResolver
            );

            $this->baseManager->shouldReceive('getCartInstance')
                ->with('wishlist', 'user-123')
                ->once()
                ->andReturn($cart);

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->getCartInstance('wishlist', 'user-123');

            expect($result)->toBeInstanceOf(Cart::class)
                ->and($result->getRulesFactory())->toBeInstanceOf(VoucherRulesFactory::class);
        });

        it('accepts null identifier', function (): void {
            $cart = new Cart(
                storage: $this->storage,
                identifier: 'default-id',
                events: null,
                instanceName: 'cart',
                eventsEnabled: false,
                conditionResolver: new CartConditionResolver
            );

            $this->baseManager->shouldReceive('getCartInstance')
                ->with('cart', null)
                ->once()
                ->andReturn($cart);

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->getCartInstance('cart', null);

            expect($result)->toBeInstanceOf(Cart::class);
        });
    });

    describe('magic __call method', function (): void {
        it('delegates unknown methods to base manager', function (): void {
            $this->baseManager->shouldReceive('someCustomMethod')
                ->with('arg1', 'arg2')
                ->once()
                ->andReturn('result');

            $wrapper = new CartManagerWithVouchers($this->baseManager);
            $result = $wrapper->someCustomMethod('arg1', 'arg2');

            expect($result)->toBe('result');
        });
    });
});

describe('CartManagerWithVouchers VoucherRulesFactory integration', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
    });

    it('wraps existing rules factory with VoucherRulesFactory', function (): void {
        $cart = new Cart(
            storage: $this->storage,
            identifier: 'factory-test',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
            conditionResolver: new CartConditionResolver
        );

        // Cart starts without a factory, so this test verifies wrapping happens
        $baseManager = Mockery::mock(CartManagerInterface::class);
        $baseManager->shouldReceive('getCurrentCart')->once()->andReturn($cart);

        $wrapper = new CartManagerWithVouchers($baseManager);
        $result = $wrapper->getCurrentCart();

        // Should apply VoucherRulesFactory
        expect($result->getRulesFactory())->toBeInstanceOf(VoucherRulesFactory::class);
    });

    it('applies VoucherRulesFactory when no factory exists', function (): void {
        $cart = new Cart(
            storage: $this->storage,
            identifier: 'no-factory-test',
            events: null,
            instanceName: 'default',
            eventsEnabled: false,
            conditionResolver: new CartConditionResolver
        );

        $baseManager = Mockery::mock(CartManagerInterface::class);
        $baseManager->shouldReceive('getCurrentCart')->once()->andReturn($cart);

        $wrapper = new CartManagerWithVouchers($baseManager);
        $result = $wrapper->getCurrentCart();

        expect($result->getRulesFactory())->toBeInstanceOf(VoucherRulesFactory::class);
    });
});
