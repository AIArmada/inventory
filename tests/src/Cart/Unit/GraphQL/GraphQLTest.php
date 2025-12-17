<?php

declare(strict_types=1);

use AIArmada\Cart\GraphQL\Mutations\CartMutations;
use AIArmada\Cart\GraphQL\Queries\CartQuery;
use AIArmada\Cart\GraphQL\Subscriptions\CartSubscription;
use AIArmada\Cart\GraphQL\Types\CartType;

describe('CartMutations SDL', function (): void {
    it('generates GraphQL SDL', function (): void {
        $sdl = CartMutations::sdl();

        expect($sdl)->toBeString()
            ->and($sdl)->toContain('Mutation')
            ->and($sdl)->toContain('addToCart')
            ->and($sdl)->toContain('updateCartItem')
            ->and($sdl)->toContain('removeFromCart')
            ->and($sdl)->toContain('applyCondition')
            ->and($sdl)->toContain('removeCondition')
            ->and($sdl)->toContain('clearCart')
            ->and($sdl)->toContain('checkout');
    });

    it('SDL contains input types', function (): void {
        $sdl = CartMutations::sdl();

        expect($sdl)->toContain('AddToCartInput')
            ->and($sdl)->toContain('UpdateCartItemInput')
            ->and($sdl)->toContain('ApplyConditionInput')
            ->and($sdl)->toContain('CheckoutInput');
    });

    it('SDL contains result types', function (): void {
        $sdl = CartMutations::sdl();

        expect($sdl)->toContain('CartMutationResult')
            ->and($sdl)->toContain('CheckoutResult')
            ->and($sdl)->toContain('CartError');
    });
});

describe('CartQuery SDL', function (): void {
    it('generates GraphQL SDL', function (): void {
        $sdl = CartQuery::sdl();

        expect($sdl)->toBeString()
            ->and($sdl)->toContain('Query')
            ->and($sdl)->toContain('cart')
            ->and($sdl)->toContain('cartByIdentifier')
            ->and($sdl)->toContain('myCart')
            ->and($sdl)->toContain('abandonedCarts')
            ->and($sdl)->toContain('searchCarts');
    });

    it('SDL contains search result type', function (): void {
        $sdl = CartQuery::sdl();

        expect($sdl)->toContain('CartSearchResult');
    });
});

describe('CartSubscription SDL', function (): void {
    it('generates GraphQL SDL', function (): void {
        $sdl = CartSubscription::sdl();

        expect($sdl)->toBeString()
            ->and($sdl)->toContain('Subscription')
            ->and($sdl)->toContain('cartUpdated')
            ->and($sdl)->toContain('cartItemChanged')
            ->and($sdl)->toContain('cartConditionChanged');
    });

    it('SDL contains subscription payload types', function (): void {
        $sdl = CartSubscription::sdl();

        expect($sdl)->toContain('CartUpdatePayload')
            ->and($sdl)->toContain('CartItemChangePayload')
            ->and($sdl)->toContain('CartConditionChangePayload');
    });

    it('SDL contains subscription definitions', function (): void {
        $sdl = CartSubscription::sdl();

        expect($sdl)->toContain('identifier: String!')
            ->and($sdl)->toContain('instance: String');
    });
});

describe('CartType', function (): void {
    it('generates GraphQL SDL', function (): void {
        $sdl = CartType::sdl();

        expect($sdl)->toBeString()
            ->and($sdl)->toContain('Cart')
            ->and($sdl)->toContain('CartItem')
            ->and($sdl)->toContain('CartCondition')
            ->and($sdl)->toContain('Money');
    });

    it('returns type definition array', function (): void {
        $definition = CartType::definition();

        expect($definition)->toBeArray()
            ->and($definition)->toHaveKey('name')
            ->and($definition)->toHaveKey('description')
            ->and($definition)->toHaveKey('fields')
            ->and($definition['name'])->toBe('Cart');
    });

    it('definition fields include core cart properties', function (): void {
        $definition = CartType::definition();
        $fields = $definition['fields'];

        expect($fields)->toHaveKeys(['id', 'identifier', 'instance', 'items', 'total', 'subtotal']);
    });
});
