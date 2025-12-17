<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartItem;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('CartItem Model', function (): void {
    beforeEach(function (): void {
        $this->cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
            'currency' => 'USD',
        ]);
    });

    it('can be created', function (): void {
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'prod-123',
            'name' => 'Product X',
            'price' => 1000,
            'quantity' => 2,
        ]);

        expect($item)->toBeInstanceOf(CartItem::class);
        expect($item->name)->toBe('Product X');
    });

    it('calculates subtotal attributes', function (): void {
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'item-1',
            'name' => 'Test Item',
            'price' => 1000, // $10.00
            'quantity' => 3,
        ]);

        expect($item->subtotal)->toBe(3000);
        expect($item->priceInDollars)->toBe(10.00);
        expect($item->subtotalInDollars)->toBe(30.00);
    });

    it('formats money attributes', function (): void {
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'item-2',
            'name' => 'Test Item 2',
            'price' => 1050, // $10.50
            'quantity' => 1,
        ]);

        expect($item->formattedPrice)->toBe('$10.50');
        expect($item->formattedSubtotal)->toBe('$10.50');
    });

    it('identifies conditions', function (): void {
        $itemNoCond = CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'nc',
            'name' => 'No Cond',
            'price' => 100,
            'quantity' => 1,
            'conditions' => []
        ]);
        $itemWithCond = CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'wc',
            'name' => 'With Cond',
            'price' => 100,
            'quantity' => 1,
            'conditions' => [['name' => 'A']]
        ]);

        expect($itemNoCond->hasConditions())->toBeFalse();
        expect($itemNoCond->conditionsCount)->toBe(0);

        expect($itemWithCond->hasConditions())->toBeTrue();
        expect($itemWithCond->conditionsCount)->toBe(1);
    });

    it('counts attributes', function (): void {
        $item = CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'attrs',
            'name' => 'Has Attrs',
            'price' => 100,
            'quantity' => 1,
            'attributes' => ['color' => 'red', 'size' => 'M'],
        ]);

        expect($item->attributesCount)->toBe(2);
    });

    it('scopes query', function (): void {
        CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'w1',
            'name' => 'Widget A',
            'price' => 1000,
            'quantity' => 1,
            'conditions' => [['name' => 'A']],
        ]);

        CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'w2',
            'name' => 'Widget B',
            'price' => 5000,
            'quantity' => 5,
            'conditions' => [],
        ]);

        expect(CartItem::byName('Widget')->count())->toBe(2);
        expect(CartItem::priceBetween(5, 20)->count())->toBe(1); // $10 matches
        expect(CartItem::quantityBetween(4, 6)->count())->toBe(1); // 5 matches

        expect(CartItem::withConditions()->count())->toBe(1);
        expect(CartItem::withoutConditions()->count())->toBe(1);
    });

    it('scopes by cart instance and identifier', function (): void {
        $otherCart = Cart::create([
            'instance' => 'wishlist',
            'identifier' => 'session-999',
            'currency' => 'USD',
        ]);

        $defaultItem = CartItem::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'default-item',
            'name' => 'Default Item',
            'price' => 100,
            'quantity' => 1,
        ]);

        CartItem::create([
            'cart_id' => $otherCart->id,
            'item_id' => 'other-item',
            'name' => 'Other Item',
            'price' => 100,
            'quantity' => 1,
        ]);

        expect(CartItem::instance('default')->pluck('id')->all())->toContain($defaultItem->id);
        expect(CartItem::byIdentifier('session-123')->pluck('id')->all())->toContain($defaultItem->id);
    });
});
