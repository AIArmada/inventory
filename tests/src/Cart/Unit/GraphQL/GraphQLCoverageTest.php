<?php

declare(strict_types=1);

use AIArmada\Cart\CartManager;
use AIArmada\Cart\GraphQL\Mutations\CartMutations;
use AIArmada\Cart\GraphQL\Queries\CartQuery;
use AIArmada\Cart\GraphQL\Subscriptions\CartSubscription;
use Illuminate\Contracts\Broadcasting\Broadcaster;

describe('GraphQL SDL Coverage', function (): void {
    // CartMutations
    it('CartMutations sdl returns valid GraphQL schema', function (): void {
        $sdl = CartMutations::sdl();

        expect($sdl)->toBeString()
            ->and(strlen($sdl))->toBeGreaterThan(100);
    });

    it('CartMutations sdl contains mutation definitions', function (): void {
        $sdl = CartMutations::sdl();

        expect($sdl)->toContain('addToCart')
            ->and($sdl)->toContain('updateCartItem')
            ->and($sdl)->toContain('removeFromCart')
            ->and($sdl)->toContain('applyCondition')
            ->and($sdl)->toContain('checkout');
    });

    it('CartMutations sdl contains result types', function (): void {
        $sdl = CartMutations::sdl();

        expect($sdl)->toContain('CartMutationResult')
            ->and($sdl)->toContain('CheckoutResult');
    });

    // CartQuery
    it('CartQuery sdl returns valid GraphQL schema', function (): void {
        $sdl = CartQuery::sdl();

        expect($sdl)->toBeString()
            ->and(strlen($sdl))->toBeGreaterThan(100);
    });

    it('CartQuery sdl contains query definitions', function (): void {
        $sdl = CartQuery::sdl();

        expect($sdl)->toContain('cart')
            ->and($sdl)->toContain('cartByIdentifier')
            ->and($sdl)->toContain('myCart')
            ->and($sdl)->toContain('abandonedCarts')
            ->and($sdl)->toContain('searchCarts');
    });

    it('CartQuery sdl contains search result type', function (): void {
        $sdl = CartQuery::sdl();

        expect($sdl)->toContain('CartSearchResult');
    });

    // CartSubscription
    it('CartSubscription sdl returns valid GraphQL schema', function (): void {
        $sdl = CartSubscription::sdl();

        expect($sdl)->toBeString()
            ->and(strlen($sdl))->toBeGreaterThan(100);
    });

    it('CartSubscription sdl contains subscription definitions', function (): void {
        $sdl = CartSubscription::sdl();

        expect($sdl)->toContain('cartUpdated')
            ->and($sdl)->toContain('cartItemChanged')
            ->and($sdl)->toContain('CartUpdatePayload');
    });

    it('CartSubscription sdl contains payload types', function (): void {
        $sdl = CartSubscription::sdl();

        expect($sdl)->toContain('CartUpdatePayload')
            ->and($sdl)->toContain('CartItemChangePayload');
    });

    // Test instantiation of CartSubscription (non-final dependencies)
    it('CartSubscription can be instantiated with mocked dependencies', function (): void {
        $cartManager = Mockery::mock(CartManager::class);
        $broadcaster = Mockery::mock(Broadcaster::class);

        $subscription = new CartSubscription($cartManager, $broadcaster);

        expect($subscription)->toBeInstanceOf(CartSubscription::class);
    });
});
