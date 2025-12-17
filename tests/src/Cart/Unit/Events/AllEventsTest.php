<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Events\CartCleared;
use AIArmada\Cart\Events\CartConditionAdded;
use AIArmada\Cart\Events\CartConditionRemoved;
use AIArmada\Cart\Events\CartCreated;
use AIArmada\Cart\Events\CartDestroyed;
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\Cart\Events\ItemUpdated;
use AIArmada\Cart\Events\MetadataAdded;
use AIArmada\Cart\Events\MetadataCleared;
use AIArmada\Cart\Events\MetadataRemoved;
use AIArmada\Cart\Testing\InMemoryStorage;

describe('CartCleared Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'test-user');
        $this->cart->add('item-1', 'Test Product', 1000, 1);
        $this->event = new CartCleared($this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(CartCleared::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.cleared');
    });

    it('returns cart identifier', function (): void {
        expect($this->event->getCartIdentifier())->toBe('test-user');
    });

    it('returns cart instance', function (): void {
        expect($this->event->getCartInstance())->toBe('default');
    });

    it('returns cart id', function (): void {
        expect($this->event->getCartId())->not->toBeNull();
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['identifier', 'instance_name', 'timestamp'])
            ->and($array['identifier'])->toBe('test-user')
            ->and($array['instance_name'])->toBe('default');
    });
});

describe('CartCreated Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'new-user');
        $this->event = new CartCreated($this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(CartCreated::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.created');
    });

    it('returns cart identifier', function (): void {
        expect($this->event->getCartIdentifier())->toBe('new-user');
    });

    it('returns cart instance', function (): void {
        expect($this->event->getCartInstance())->toBe('default');
    });

    it('returns cart id', function (): void {
        expect($this->event->getCartId())->not->toBeNull();
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['identifier', 'instance_name', 'timestamp'])
            ->and($array['identifier'])->toBe('new-user');
    });
});

describe('CartDestroyed Event', function (): void {
    beforeEach(function (): void {
        $this->event = new CartDestroyed(
            identifier: 'destroyed-user',
            instance: 'wishlist',
            cartId: 'cart-uuid-123'
        );
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(CartDestroyed::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.destroyed');
    });

    it('returns cart identifier', function (): void {
        expect($this->event->getCartIdentifier())->toBe('destroyed-user');
    });

    it('returns cart instance', function (): void {
        expect($this->event->getCartInstance())->toBe('wishlist');
    });

    it('returns cart id', function (): void {
        expect($this->event->getCartId())->toBe('cart-uuid-123');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['identifier', 'instance_name', 'cart_id', 'timestamp'])
            ->and($array['identifier'])->toBe('destroyed-user')
            ->and($array['instance_name'])->toBe('wishlist')
            ->and($array['cart_id'])->toBe('cart-uuid-123');
    });
});

describe('ItemAdded Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'item-user');
        $this->cart->add('prod-1', 'Test Product', 2500, 2);
        $this->item = $this->cart->getItems()->first();
        $this->event = new ItemAdded($this->item, $this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(ItemAdded::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.item.added');
    });

    it('returns cart identifier', function (): void {
        expect($this->event->getCartIdentifier())->toBe('item-user');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['item_id', 'item_name', 'quantity', 'price', 'identifier', 'instance_name', 'timestamp'])
            ->and($array['item_name'])->toBe('Test Product')
            ->and($array['quantity'])->toBe(2)
            ->and($array['price'])->toBe(2500);
    });
});

describe('ItemRemoved Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'remove-user');
        $this->cart->add('prod-2', 'Removed Product', 1500, 3);
        $this->item = $this->cart->getItems()->first();
        $this->event = new ItemRemoved($this->item, $this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(ItemRemoved::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.item.removed');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['item_id', 'item_name', 'quantity', 'price', 'identifier', 'instance_name', 'timestamp'])
            ->and($array['item_name'])->toBe('Removed Product');
    });
});

describe('ItemUpdated Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'update-user');
        $this->cart->add('prod-3', 'Updated Product', 3000, 1);
        $this->item = $this->cart->getItems()->first();
        // ItemUpdated only takes (item, cart) - no old/new quantities in constructor
        $this->event = new ItemUpdated($this->item, $this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(ItemUpdated::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.item.updated');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['item_id', 'item_name', 'quantity', 'price', 'identifier', 'instance_name', 'timestamp']);
    });
});

describe('CartConditionAdded Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'condition-user');
        $this->condition = new CartCondition(
            name: 'Discount 10%',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%'
        );
        $this->event = new CartConditionAdded($this->condition, $this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(CartConditionAdded::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.condition.added');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['condition', 'cart', 'impact', 'timestamp'])
            ->and($array['condition']['name'])->toBe('Discount 10%')
            ->and($array['condition']['type'])->toBe('discount');
    });

    it('calculates condition impact', function (): void {
        $impact = $this->event->getConditionImpact();
        expect($impact)->toBeFloat();
    });
});

describe('CartConditionRemoved Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'remove-condition-user');
        $this->condition = new CartCondition(
            name: 'Shipping Fee',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '+500'
        );
        $this->event = new CartConditionRemoved($this->condition, $this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(CartConditionRemoved::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.condition.removed');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['condition', 'cart', 'timestamp'])
            ->and($array['condition']['name'])->toBe('Shipping Fee');
    });
});

describe('MetadataAdded Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'meta-user');
        $this->event = new MetadataAdded('coupon_code', 'SAVE20', $this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(MetadataAdded::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.metadata.added');
    });

    it('stores key and value', function (): void {
        expect($this->event->key)->toBe('coupon_code')
            ->and($this->event->value)->toBe('SAVE20');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['key', 'value', 'cart', 'timestamp'])
            ->and($array['key'])->toBe('coupon_code')
            ->and($array['value'])->toBe('SAVE20');
    });
});

describe('MetadataRemoved Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'meta-remove-user');
        // MetadataRemoved takes (key, cart) - no value
        $this->event = new MetadataRemoved('coupon_code', $this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(MetadataRemoved::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.metadata.removed');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['key', 'cart', 'timestamp'])
            ->and($array['key'])->toBe('coupon_code');
    });
});

describe('MetadataCleared Event', function (): void {
    beforeEach(function (): void {
        $storage = new InMemoryStorage;
        $this->cart = new Cart($storage, 'meta-clear-user');
        $this->event = new MetadataCleared($this->cart);
    });

    it('can be instantiated', function (): void {
        expect($this->event)->toBeInstanceOf(MetadataCleared::class);
    });

    it('returns correct event type', function (): void {
        expect($this->event->getEventType())->toBe('cart.metadata.cleared');
    });

    it('converts to array', function (): void {
        $array = $this->event->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKeys(['cart', 'timestamp']);
    });
});
