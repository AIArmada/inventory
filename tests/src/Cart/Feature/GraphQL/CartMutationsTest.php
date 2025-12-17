<?php

declare(strict_types=1);

use AIArmada\Cart\Commands\CartCommandBus;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\GraphQL\Mutations\CartMutations;

describe('CartMutations Integration', function (): void {
    beforeEach(function (): void {
        $this->cartManager = app(CartManagerInterface::class);
        $this->commandBus = app(CartCommandBus::class);
        $this->mutations = new CartMutations($this->cartManager, $this->commandBus);
    });

    describe('sdl', function (): void {
        it('returns valid GraphQL schema definition', function (): void {
            $sdl = CartMutations::sdl();

            expect($sdl)
                ->toContain('extend type Mutation')
                ->toContain('addToCart')
                ->toContain('updateCartItem')
                ->toContain('removeFromCart')
                ->toContain('applyCondition')
                ->toContain('removeCondition')
                ->toContain('clearCart')
                ->toContain('checkout');
        });

        it('defines AddToCartInput correctly', function (): void {
            $sdl = CartMutations::sdl();

            expect($sdl)
                ->toContain('input AddToCartInput')
                ->toContain('identifier: String!')
                ->toContain('itemId: ID!')
                ->toContain('priceInCents: Int!');
        });

        it('defines CartMutationResult type', function (): void {
            $sdl = CartMutations::sdl();

            expect($sdl)
                ->toContain('type CartMutationResult')
                ->toContain('success: Boolean!')
                ->toContain('cart: Cart')
                ->toContain('errors: [CartError!]');
        });

        it('defines CheckoutResult type', function (): void {
            $sdl = CartMutations::sdl();

            expect($sdl)
                ->toContain('type CheckoutResult')
                ->toContain('orderId: ID')
                ->toContain('paymentUrl: String');
        });
    });

    describe('addToCart', function (): void {
        it('returns result structure', function (): void {
            $identifier = 'graphql-add-test-' . uniqid();

            $result = $this->mutations->addToCart(null, [
                'input' => [
                    'identifier' => $identifier,
                    'instance' => 'default',
                    'itemId' => 'product-1',
                    'name' => 'Test Product',
                    'priceInCents' => 10000,
                    'quantity' => 2,
                ],
            ]);

            // Result should have expected structure
            expect($result)->toHaveKey('success');
            expect($result)->toHaveKey('cart');
            expect($result)->toHaveKey('errors');
        });

        it('returns error details on failure', function (): void {
            $result = $this->mutations->addToCart(null, [
                'input' => [
                    'identifier' => '',
                    'itemId' => '',
                    'name' => '',
                    'priceInCents' => -100,
                ],
            ]);

            expect($result['success'])->toBeFalse();
            expect($result['errors'])->not->toBeEmpty();
            expect($result['errors'][0])->toHaveKey('code');
            expect($result['errors'][0])->toHaveKey('message');
        });
    });

    describe('updateCartItem', function (): void {
        it('returns result structure', function (): void {
            $identifier = 'graphql-update-test-' . uniqid();

            $result = $this->mutations->updateCartItem(null, [
                'input' => [
                    'identifier' => $identifier,
                    'instance' => 'default',
                    'itemId' => 'product-2',
                    'quantity' => 5,
                ],
            ]);

            expect($result)->toHaveKey('success');
            expect($result)->toHaveKey('cart');
            expect($result)->toHaveKey('errors');
        });
    });

    describe('removeFromCart', function (): void {
        it('returns result structure', function (): void {
            $identifier = 'graphql-remove-test-' . uniqid();

            $result = $this->mutations->removeFromCart(null, [
                'identifier' => $identifier,
                'instance' => 'default',
                'itemId' => 'product-3',
            ]);

            expect($result)->toHaveKey('success');
            expect($result)->toHaveKey('cart');
            expect($result)->toHaveKey('errors');
        });
    });

    describe('clearCart', function (): void {
        it('returns result structure', function (): void {
            $identifier = 'graphql-clear-test-' . uniqid();

            $result = $this->mutations->clearCart(null, [
                'identifier' => $identifier,
                'instance' => 'default',
            ]);

            expect($result)->toHaveKey('success');
            expect($result)->toHaveKey('cart');
            expect($result)->toHaveKey('errors');
        });
    });

    describe('applyCondition', function (): void {
        it('returns result structure', function (): void {
            $identifier = 'graphql-condition-test-' . uniqid();

            $result = $this->mutations->applyCondition(null, [
                'input' => [
                    'identifier' => $identifier,
                    'instance' => 'default',
                    'name' => 'test_discount',
                    'type' => 'discount',
                    'value' => '-10%',
                    'target' => 'cart@cart_subtotal/aggregate',
                ],
            ]);

            expect($result)->toHaveKey('success');
            expect($result)->toHaveKey('cart');
            expect($result)->toHaveKey('errors');
        });
    });

    describe('removeCondition', function (): void {
        it('returns result structure', function (): void {
            $identifier = 'graphql-remove-cond-test-' . uniqid();

            $result = $this->mutations->removeCondition(null, [
                'identifier' => $identifier,
                'instance' => 'default',
                'conditionName' => 'non_existent_condition',
            ]);

            expect($result)->toHaveKey('success');
            expect($result)->toHaveKey('cart');
            expect($result)->toHaveKey('errors');
        });
    });
});
