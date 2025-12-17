<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartCondition;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('CartCondition Model', function (): void {
    beforeEach(function (): void {
        $this->cart = Cart::create([
            'instance' => 'default',
            'identifier' => 'session-123',
            'currency' => 'USD',
        ]);
    });

    it('can be created', function (): void {
        $condition = CartCondition::create([
            'cart_id' => $this->cart->id,
            'name' => 'Discount',
            'type' => 'discount',
            'target' => 'cart.subtotal',
            'target_definition' => [],
            'value' => '-10%',
        ]);

        expect($condition)->toBeInstanceOf(CartCondition::class);
        expect($condition->isDiscount())->toBeTrue();
    });

    it('identifies level', function (): void {
        $cartLevel = CartCondition::create([
            'cart_id' => $this->cart->id,
            'name' => 'Cart Promo',
            'type' => 'discount',
            'target' => 'cart.subtotal',
            'target_definition' => [],
            'value' => '10%',
        ]);

        $itemLevel = CartCondition::create([
            'cart_id' => $this->cart->id,
            'item_id' => 'item-1',
            'name' => 'Item Promo',
            'type' => 'discount',
            'target' => 'item.price',
            'target_definition' => [],
            'value' => '5%',
        ]);

        expect($cartLevel->isCartLevel())->toBeTrue();
        expect($cartLevel->isItemLevel())->toBeFalse();
        expect($cartLevel->level)->toBe('Cart');

        expect($itemLevel->isCartLevel())->toBeFalse();
        expect($itemLevel->isItemLevel())->toBeTrue();
        expect($itemLevel->level)->toBe('Item');
    });

    it('identifies types', function (): void {
        expect(CartCondition::create(['cart_id' => $this->cart->id, 'type' => 'discount', 'target' => 'cart.subtotal', 'target_definition' => [], 'name' => '1', 'value' => '0'])->isDiscount())->toBeTrue();
        expect(CartCondition::create(['cart_id' => $this->cart->id, 'type' => 'tax', 'target' => 'cart.subtotal', 'target_definition' => [], 'name' => '2', 'value' => '0'])->isTax())->toBeTrue();
        expect(CartCondition::create(['cart_id' => $this->cart->id, 'type' => 'fee', 'target' => 'cart.subtotal', 'target_definition' => [], 'name' => '3', 'value' => '0'])->isFee())->toBeTrue();
        expect(CartCondition::create(['cart_id' => $this->cart->id, 'type' => 'shipping', 'target' => 'cart.subtotal', 'target_definition' => [], 'name' => '4', 'value' => '0'])->isShipping())->toBeTrue();
    });

    it('identifies percentage', function (): void {
        $pct = CartCondition::create(['cart_id' => $this->cart->id, 'name' => 'A', 'type' => 'discount', 'target' => 'cart.subtotal', 'target_definition' => [], 'value' => '10%']);
        $fixed = CartCondition::create(['cart_id' => $this->cart->id, 'name' => 'B', 'type' => 'discount', 'target' => 'cart.subtotal', 'target_definition' => [], 'value' => '10']);

        expect($pct->isPercentage())->toBeTrue();
        expect($fixed->isPercentage())->toBeFalse();
    });

    it('formats values', function (): void {
        $pct = CartCondition::create(['cart_id' => $this->cart->id, 'name' => 'A', 'type' => 'discount', 'target' => 'cart.subtotal', 'target_definition' => [], 'value' => '-10%']);
        $fixedPos = CartCondition::create(['cart_id' => $this->cart->id, 'name' => 'B', 'type' => 'discount', 'target' => 'cart.subtotal', 'target_definition' => [], 'value' => '+1000']); // +$10.00
        $fixedNeg = CartCondition::create(['cart_id' => $this->cart->id, 'name' => 'C', 'type' => 'discount', 'target' => 'cart.subtotal', 'target_definition' => [], 'value' => '-500']);  // -$5.00

        expect($pct->formattedValue)->toBe('-10%');
        expect($fixedPos->formattedValue)->toBe('+$10.00');
        expect($fixedNeg->formattedValue)->toBe('-$5.00');
    });

    it('scopes query', function (): void {
        CartCondition::create([
            'cart_id' => $this->cart->id,
            'name' => 'Promo',
            'type' => 'discount',
            'target' => 'cart.subtotal',
            'target_definition' => [],
            'value' => '10%',
        ]);

        $otherCart = Cart::create(['instance' => 'wishlist', 'identifier' => 'other']);
        CartCondition::create([
            'cart_id' => $otherCart->id,
            'name' => 'Other Promo',
            'type' => 'discount',
            'target' => 'cart.subtotal',
            'target_definition' => [],
            'value' => '10%',
        ]);

        expect(CartCondition::instance('default')->count())->toBe(1);
        expect(CartCondition::byIdentifier('session-123')->count())->toBe(1);
        expect(CartCondition::discounts()->count())->toBe(2);
    });
});
