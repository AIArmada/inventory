<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineContext;
use AIArmada\Cart\Conditions\Pipeline\ConditionPipelinePhaseContext;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\ConditionScopeResolverInterface;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\DefaultScopeResolver;
use Tests\Support\Cart\InMemoryStorage;

describe('DefaultScopeResolver', function (): void {
    it('can be instantiated', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);

        expect($resolver)->toBeInstanceOf(DefaultScopeResolver::class)
            ->and($resolver)->toBeInstanceOf(ConditionScopeResolverInterface::class);
    });

    it('supports matching scope', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);

        expect($resolver->supports(ConditionScope::CART))->toBeTrue();
    });

    it('does not support non-matching scope', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);

        expect($resolver->supports(ConditionScope::ITEMS))->toBeFalse()
            ->and($resolver->supports(ConditionScope::CUSTOM))->toBeFalse();
    });

    it('resolves with empty conditions returns current amount', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);
        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'test-user');
        $pipelineContext = ConditionPipelineContext::fromCart($cart);
        $conditions = new CartConditionCollection;

        $phaseContext = new ConditionPipelinePhaseContext(
            ConditionPhase::CART_SUBTOTAL,
            1000,
            $conditions,
            $pipelineContext
        );

        $result = $resolver->resolve($phaseContext, ConditionScope::CART, $conditions, 1000);

        expect($result)->toBe(1000);
    });

    it('resolves with discount condition reduces amount', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);
        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'test-user');
        $cart->add('item-1', 'Product', 1000, 1);
        $pipelineContext = ConditionPipelineContext::fromCart($cart);

        $condition = new CartCondition(
            name: 'Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%'
        );
        $conditions = new CartConditionCollection([$condition]);

        $phaseContext = new ConditionPipelinePhaseContext(
            ConditionPhase::CART_SUBTOTAL,
            1000,
            $conditions,
            $pipelineContext
        );

        $result = $resolver->resolve($phaseContext, ConditionScope::CART, $conditions, 1000);

        expect($result)->toBe(900); // 1000 - 10% = 900
    });

    it('resolves with fixed discount condition', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);
        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'test-user');
        $pipelineContext = ConditionPipelineContext::fromCart($cart);

        $condition = new CartCondition(
            name: 'Fixed Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-200'
        );
        $conditions = new CartConditionCollection([$condition]);

        $phaseContext = new ConditionPipelinePhaseContext(
            ConditionPhase::CART_SUBTOTAL,
            1000,
            $conditions,
            $pipelineContext
        );

        $result = $resolver->resolve($phaseContext, ConditionScope::CART, $conditions, 1000);

        expect($result)->toBe(800); // 1000 - 200 = 800
    });

    it('resolves with fee condition adds amount', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);
        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'test-user');
        $pipelineContext = ConditionPipelineContext::fromCart($cart);

        $condition = new CartCondition(
            name: 'Shipping',
            type: 'shipping',
            target: 'cart@cart_subtotal/aggregate',
            value: '+500'
        );
        $conditions = new CartConditionCollection([$condition]);

        $phaseContext = new ConditionPipelinePhaseContext(
            ConditionPhase::CART_SUBTOTAL,
            1000,
            $conditions,
            $pipelineContext
        );

        $result = $resolver->resolve($phaseContext, ConditionScope::CART, $conditions, 1000);

        expect($result)->toBe(1500); // 1000 + 500 = 1500
    });

    it('resolves multiple conditions in order', function (): void {
        $resolver = new DefaultScopeResolver(ConditionScope::CART);
        $storage = new InMemoryStorage;
        $cart = new Cart($storage, 'test-user');
        $pipelineContext = ConditionPipelineContext::fromCart($cart);

        $discount = new CartCondition(
            name: 'Discount',
            type: 'discount',
            target: 'cart@cart_subtotal/aggregate',
            value: '-10%',
            order: 1
        );
        $fee = new CartCondition(
            name: 'Fee',
            type: 'fee',
            target: 'cart@cart_subtotal/aggregate',
            value: '+100',
            order: 2
        );
        $conditions = new CartConditionCollection([$fee, $discount]); // Out of order

        $phaseContext = new ConditionPipelinePhaseContext(
            ConditionPhase::CART_SUBTOTAL,
            1000,
            $conditions,
            $pipelineContext
        );

        $result = $resolver->resolve($phaseContext, ConditionScope::CART, $conditions, 1000);

        // Sorted by order: discount first (1000 - 10% = 900), then fee (900 + 100 = 1000)
        expect($result)->toBe(1000);
    });
});
