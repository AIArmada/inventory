<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Projectors\CartProjector;
use AIArmada\Cart\ReadModels\CartReadModel;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Events\Dispatcher;

describe('CartProjector', function (): void {
    beforeEach(function (): void {
        $this->connection = Mockery::mock(ConnectionInterface::class);
        $this->cache = new Repository(new ArrayStore);
        $this->storage = Mockery::mock(StorageInterface::class);
        $this->storage->shouldReceive('getOwnerType')->andReturn(null);
        $this->storage->shouldReceive('getOwnerId')->andReturn(null);

        // Use real ReadModel since it's a final class
        $this->readModel = new CartReadModel($this->connection, $this->cache, $this->storage);
        $this->projector = new CartProjector($this->readModel);

        // Create a cart for events
        $memoryStorage = new InMemoryStorage;
        $this->cart = new Cart($memoryStorage, 'user-projector-test');
        $this->cart->add('item-1', 'Test Product', 1000, 1);
    });

    it('can be instantiated', function (): void {
        expect($this->projector)->toBeInstanceOf(CartProjector::class);
    });

    it('subscribes to all cart events', function (): void {
        $dispatcher = new Dispatcher;

        $this->projector->subscribe($dispatcher);

        expect($dispatcher->hasListeners(CartCreated::class))->toBeTrue()
            ->and($dispatcher->hasListeners(CartDestroyed::class))->toBeTrue()
            ->and($dispatcher->hasListeners(CartCleared::class))->toBeTrue()
            ->and($dispatcher->hasListeners(ItemAdded::class))->toBeTrue()
            ->and($dispatcher->hasListeners(ItemRemoved::class))->toBeTrue()
            ->and($dispatcher->hasListeners(ItemUpdated::class))->toBeTrue()
            ->and($dispatcher->hasListeners(CartConditionAdded::class))->toBeTrue()
            ->and($dispatcher->hasListeners(CartConditionRemoved::class))->toBeTrue();
    });

    // Test event handlers - they invalidate cache which doesn't throw errors
    it('handles cart created event', function (): void {
        $event = new CartCreated($this->cart);

        // Should not throw any exceptions
        $this->projector->onCartCreated($event);

        expect(true)->toBeTrue();
    });

    it('handles cart destroyed event', function (): void {
        // CartDestroyed takes (identifier, instance, cartId)
        $event = new CartDestroyed(
            identifier: $this->cart->getIdentifier(),
            instance: $this->cart->instance(),
            cartId: $this->cart->getId()
        );

        $this->projector->onCartDestroyed($event);

        expect(true)->toBeTrue();
    });

    it('handles cart cleared event', function (): void {
        $event = new CartCleared($this->cart);

        $this->projector->onCartCleared($event);

        expect(true)->toBeTrue();
    });

    it('handles item added event', function (): void {
        $item = $this->cart->getItems()->first();
        $event = new ItemAdded($item, $this->cart);

        $this->projector->onItemAdded($event);

        expect(true)->toBeTrue();
    });

    it('handles item removed event', function (): void {
        $item = $this->cart->getItems()->first();
        $event = new ItemRemoved($item, $this->cart);

        $this->projector->onItemRemoved($event);

        expect(true)->toBeTrue();
    });

    it('handles item updated event', function (): void {
        $item = $this->cart->getItems()->first();
        $event = new ItemUpdated($item, $this->cart, 1, 2);

        $this->projector->onItemUpdated($event);

        expect(true)->toBeTrue();
    });

    it('handles condition added event', function (): void {
        $condition = new CartCondition(
            name: 'Test Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%'
        );
        $event = new CartConditionAdded($condition, $this->cart);

        $this->projector->onConditionAdded($event);

        expect(true)->toBeTrue();
    });

    it('handles condition removed event', function (): void {
        $condition = new CartCondition(
            name: 'Test Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%'
        );
        $event = new CartConditionRemoved($condition, $this->cart);

        $this->projector->onConditionRemoved($event);

        expect(true)->toBeTrue();
    });
});
