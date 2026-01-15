<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Exceptions\InvalidCartConditionException;
use Tests\Support\Cart\InMemoryStorage;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage;
    $this->cart = new Cart($this->storage, 'dynamic_guard_test');
});

afterEach(function (): void {
    $this->cart->clear();
});

it('prevents adding dynamic conditions using addCondition', function (): void {
    // Create a dynamic condition (with rules)
    $dynamicCondition = new CartCondition(
        name: 'dynamic_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%',
        rules: [
            fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 10000,
        ]
    );

    // Attempting to add it via addCondition should throw exception
    expect(fn() => $this->cart->addCondition($dynamicCondition))
        ->toThrow(
            InvalidCartConditionException::class,
            'Cannot add dynamic condition "dynamic_discount" using addCondition(). Dynamic conditions (with validation rules) must be registered using registerDynamicCondition() instead.'
        );
});

it('allows adding static conditions using addCondition', function (): void {
    // Create a static condition (no rules)
    $staticCondition = new CartCondition(
        name: 'static_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10',
        rules: null
    );

    // Should work fine
    $this->cart->addCondition($staticCondition);

    expect($this->cart->getConditions()->has('static_discount'))->toBeTrue();
});

it('correctly registers dynamic conditions using registerDynamicCondition', function (): void {
    $this->cart->add('product-1', 'Test Product', 10000, 1); // 10000 cents = $100 to meet min order

    // Create a dynamic condition
    $dynamicCondition = new CartCondition(
        name: 'min_order_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%',
        rules: [
            fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 10000,
        ]
    );

    // Should work via registerDynamicCondition
    $this->cart->registerDynamicCondition($dynamicCondition);

    // Dynamic condition should be in dynamic conditions collection
    expect($this->cart->getDynamicConditions()->has('min_order_discount'))->toBeTrue();

    // A static copy should be added to cart (rules met)
    expect($this->cart->getConditions()->has('min_order_discount'))->toBeTrue();

    // The cart condition should NOT have rules (it's the static copy)
    $cartCondition = $this->cart->getCondition('min_order_discount');
    expect($cartCondition)->not->toBeNull();
    expect($cartCondition->isDynamic())->toBeFalse();
});

it('provides helpful error message about using withoutRules', function (): void {
    $dynamicCondition = new CartCondition(
        name: 'dynamic_fee',
        type: 'fee',
        target: 'cart@grand_total/aggregate',
        value: '+5',
        rules: [fn($cart) => true]
    );

    try {
        $this->cart->addCondition($dynamicCondition);
        expect(false)->toBeTrue('Exception should have been thrown');
    } catch (InvalidCartConditionException $e) {
        expect($e->getMessage())->toContain('withoutRules()');
        expect($e->getMessage())->toContain('registerDynamicCondition()');
    }
});

it('allows adding static copy of dynamic condition via withoutRules', function (): void {
    // Create a dynamic condition
    $dynamicCondition = new CartCondition(
        name: 'bypass_validation',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-15%',
        rules: [
            fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 20000,
        ]
    );

    // Create static copy
    $staticCopy = $dynamicCondition->withoutRules();

    // Should work
    $this->cart->addCondition($staticCopy);

    expect($this->cart->getConditions()->has('bypass_validation'))->toBeTrue();
    expect($this->cart->getCondition('bypass_validation')->isDynamic())->toBeFalse();
});

it('handles array of conditions with mixed static and dynamic', function (): void {
    $staticCondition = new CartCondition(
        name: 'static_tax',
        type: 'tax',
        target: 'cart@cart_subtotal/aggregate',
        value: '10%',
        rules: null
    );

    $dynamicCondition = new CartCondition(
        name: 'dynamic_discount',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-5',
        rules: [fn($cart) => true]
    );

    // Attempting to add array with dynamic condition should fail
    expect(fn() => $this->cart->addCondition([$staticCondition, $dynamicCondition]))
        ->toThrow(
            InvalidCartConditionException::class,
            'Cannot add dynamic condition "dynamic_discount"'
        );

    // The static condition WAS added before the exception was thrown
    // This is expected behavior (not transactional)
    expect($this->cart->getConditions()->has('static_tax'))->toBeTrue();
    expect($this->cart->getConditions()->has('dynamic_discount'))->toBeFalse();
});

it('works correctly with VoucherCondition pattern', function (): void {
    // Simulating VoucherCondition's $dynamic parameter pattern

    // Static voucher (simple "$5 off")
    $staticVoucher = new CartCondition(
        name: 'voucher_SIMPLE5',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-5',
        rules: null  // Static mode
    );

    $this->cart->addCondition($staticVoucher);
    expect($this->cart->getConditions()->has('voucher_SIMPLE5'))->toBeTrue();

    // Dynamic voucher (with minimum order)
    $dynamicVoucher = new CartCondition(
        name: 'voucher_MIN100',
        type: 'discount',
        target: 'cart@cart_subtotal/aggregate',
        value: '-10%',
        rules: [
            fn($cart) => $cart->getRawSubtotalWithoutConditions() >= 10000,
        ]
    );

    $this->cart->registerDynamicCondition($dynamicVoucher);
    expect($this->cart->getDynamicConditions()->has('voucher_MIN100'))->toBeTrue();
});
