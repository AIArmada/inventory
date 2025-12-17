<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\GraphQL\Queries\CartQuery;
use AIArmada\Cart\Queries\CartQueryHandler;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\Auth;

describe('CartQuery Integration', function (): void {
    beforeEach(function (): void {
        $this->cartManager = app(CartManagerInterface::class);
        $this->queryHandler = app(CartQueryHandler::class);
        $this->query = new CartQuery($this->cartManager, $this->queryHandler);
    });

    describe('sdl', function (): void {
        it('returns valid GraphQL schema definition', function (): void {
            $sdl = CartQuery::sdl();

            expect($sdl)
                ->toContain('extend type Query')
                ->toContain('cart(id: ID!): Cart')
                ->toContain('cartByIdentifier')
                ->toContain('myCart')
                ->toContain('abandonedCarts')
                ->toContain('searchCarts');
        });
    });

    describe('cart', function (): void {
        it('returns null for non-existent cart id', function (): void {
            $result = $this->query->cart(null, ['id' => 'non-existent-id']);

            expect($result)->toBeNull();
        });
    });

    describe('cartByIdentifier', function (): void {
        it('returns cart by identifier', function (): void {
            $identifier = 'query-test-' . uniqid();

            // Create a cart with items
            $cart = $this->cartManager
                ->setIdentifier($identifier)
                ->setInstance('default')
                ->getCart();
            $cart->add('item-1', 'Test Product', 10000, 1);

            $result = $this->query->cartByIdentifier(null, [
                'identifier' => $identifier,
                'instance' => 'default',
            ]);

            expect($result)->not->toBeNull();
            expect($result['identifier'])->toBe($identifier);
            expect($result['itemCount'])->toBe(1);
        });

        it('returns null for empty cart', function (): void {
            $identifier = 'empty-query-test-' . uniqid();

            $result = $this->query->cartByIdentifier(null, [
                'identifier' => $identifier,
                'instance' => 'default',
            ]);

            expect($result)->toBeNull();
        });
    });

    describe('myCart', function (): void {
        it('returns null when no user authenticated', function (): void {
            // Clear any existing user
            Auth::forgetGuards();

            $result = $this->query->myCart(null, ['instance' => 'default']);

            expect($result)->toBeNull();
        });
    });

    describe('abandonedCarts', function (): void {
        it('returns empty array when no abandoned carts', function (): void {
            $result = $this->query->abandonedCarts(null, [
                'olderThan' => now()->subMinutes(30)->toIso8601String(),
                'limit' => 10,
            ]);

            expect($result)->toBeArray();
        });

        it('respects minValueCents filter', function (): void {
            $result = $this->query->abandonedCarts(null, [
                'olderThan' => now()->subMinutes(30)->toIso8601String(),
                'minValueCents' => 100000,
                'limit' => 10,
            ]);

            expect($result)->toBeArray();
        });
    });

    describe('searchCarts', function (): void {
        it('returns search result structure', function (): void {
            $result = $this->query->searchCarts(null, [
                'limit' => 10,
                'offset' => 0,
            ]);

            expect($result)->toHaveKey('data');
            expect($result)->toHaveKey('total');
            expect($result['data'])->toBeArray();
        });

        it('filters by identifier', function (): void {
            $result = $this->query->searchCarts(null, [
                'identifier' => 'specific-identifier',
                'limit' => 10,
            ]);

            expect($result)->toHaveKey('data');
            expect($result)->toHaveKey('total');
        });

        it('filters by date range', function (): void {
            $result = $this->query->searchCarts(null, [
                'createdAfter' => now()->subDays(7)->toIso8601String(),
                'createdBefore' => now()->toIso8601String(),
                'limit' => 10,
            ]);

            expect($result)->toHaveKey('data');
        });

        it('filters by minItems', function (): void {
            $result = $this->query->searchCarts(null, [
                'minItems' => 1,
                'limit' => 10,
            ]);

            expect($result)->toHaveKey('data');
        });

        it('respects pagination', function (): void {
            $result = $this->query->searchCarts(null, [
                'limit' => 5,
                'offset' => 10,
            ]);

            expect($result)->toHaveKey('data');
            expect($result)->toHaveKey('total');
        });
    });
});
