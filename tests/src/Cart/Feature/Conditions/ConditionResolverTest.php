<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Contracts\CartConditionConvertible;
use AIArmada\Cart\Exceptions\InvalidCartConditionException;
use AIArmada\Cart\Services\CartConditionResolver;
use Tests\Support\Cart\InMemoryStorage;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage;
    $this->resolver = app(CartConditionResolver::class);
    $this->resolver->clear();
    $this->cart = new Cart($this->storage, 'condition_resolver_test', conditionResolver: $this->resolver);
});

afterEach(function (): void {
    $this->cart->clear();
    $this->resolver->clear();
});

it('accepts conditions implementing CartConditionConvertible', function (): void {
    $convertible = new class implements CartConditionConvertible
    {
        public function toCartCondition(): CartCondition
        {
            return new CartCondition(
                name: 'convertible_discount',
                type: 'discount',
                target: 'cart@cart_subtotal/aggregate',
                value: '-10'
            );
        }
    };

    $this->cart->addCondition($convertible);

    expect($this->cart->getConditions()->has('convertible_discount'))->toBeTrue();
});

it('resolves conditions using registered resolver callbacks', function (): void {
    $this->resolver->register(function ($condition) {
        if (! $condition instanceof stdClass) {
            return null;
        }

        return new CartCondition(
            name: 'resolved_discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-5'
        );
    });

    $this->cart->addCondition(new stdClass);

    expect($this->cart->getConditions()->has('resolved_discount'))->toBeTrue();
});

it('throws when the condition cannot be converted', function (): void {
    expect(fn () => $this->cart->addCondition('invalid'))
        ->toThrow(InvalidCartConditionException::class, 'Condition of type string cannot be converted to CartCondition');
});
