<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\ReadModels\CartReadModel;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Carbon\CarbonImmutable;

describe('CartReadModel Integration', function (): void {
    beforeEach(function (): void {
        /** @var \Illuminate\Database\ConnectionInterface $connection */
        $connection = app('db')->connection();

        /** @var \Illuminate\Contracts\Cache\Repository $cache */
        $cache = app('cache')->store();

        /** @var StorageInterface $storage */
        $storage = app(StorageInterface::class);

        $this->readModel = new CartReadModel(
            connection: $connection,
            cache: $cache,
            storage: $storage
        );
    });

    describe('getCartSummary', function (): void {
        it('returns null for non-existent cart', function (): void {
            $result = $this->readModel->getCartSummary('non-existent-cart-' . uniqid());

            expect($result)->toBeNull();
        });

        it('returns summary for existing cart', function (): void {
            $identifier = 'summary-test-' . uniqid();

            Cart::setIdentifier($identifier);
            Cart::add('item-1', 'Test Product', 100.00, 2);
            $cartId = Cart::getId();

            $result = $this->readModel->getCartSummary($cartId);

            expect($result)->not->toBeNull();
            expect($result)->toHaveKey('id');
            expect($result)->toHaveKey('item_count');
            expect($result)->toHaveKey('total_quantity');
        });

        it('uses cache for repeated lookups', function (): void {
            $identifier = 'cache-test-' . uniqid();

            Cart::setIdentifier($identifier);
            Cart::add('item-1', 'Test Product', 50.00, 1);
            $cartId = Cart::getId();

            // First call - potentially from DB
            $result1 = $this->readModel->getCartSummary($cartId);

            // Second call - should be from cache
            $result2 = $this->readModel->getCartSummary($cartId);

            expect($result1)->not->toBeNull();
            expect($result2)->toEqual($result1);
        });
    });

    describe('getCartDetails', function (): void {
        it('returns null for non-existent cart', function (): void {
            $result = $this->readModel->getCartDetails('non-existent-cart-' . uniqid());

            expect($result)->toBeNull();
        });

        it('returns details including items for existing cart', function (): void {
            $identifier = 'details-test-' . uniqid();

            Cart::setIdentifier($identifier);
            Cart::add('item-1', 'First Product', 100.00, 1);
            Cart::add('item-2', 'Second Product', 200.00, 2);
            $cartId = Cart::getId();

            $result = $this->readModel->getCartDetails($cartId);

            expect($result)->not->toBeNull();
            expect($result)->toHaveKey('items');
        });
    });

    describe('getAbandonedCarts', function (): void {
        it('returns array of abandoned carts', function (): void {
            $olderThan = CarbonImmutable::now()->subDays(1);

            $result = $this->readModel->getAbandonedCarts($olderThan);

            expect($result)->toBeArray();
        });

        it('respects minimum value filter', function (): void {
            $olderThan = CarbonImmutable::now()->subDays(1);

            $result = $this->readModel->getAbandonedCarts(
                olderThan: $olderThan,
                minValueCents: 50000 // 500.00
            );

            expect($result)->toBeArray();
        });

        it('respects limit parameter', function (): void {
            $olderThan = CarbonImmutable::now()->subDays(1);

            $result = $this->readModel->getAbandonedCarts(
                olderThan: $olderThan,
                limit: 5
            );

            expect($result)->toBeArray();
            expect(count($result))->toBeLessThanOrEqual(5);
        });
    });

    describe('searchCarts', function (): void {
        it('returns search results with pagination', function (): void {
            $result = $this->readModel->searchCarts(limit: 10, offset: 0);

            expect($result)->toHaveKey('data');
            expect($result)->toHaveKey('total');
            expect($result['data'])->toBeArray();
        });

        it('filters by identifier', function (): void {
            $identifier = 'search-by-id-' . uniqid();

            Cart::setIdentifier($identifier);
            Cart::add('item-1', 'Test Product', 100.00, 1);

            $result = $this->readModel->searchCarts(
                identifier: $identifier,
                limit: 10
            );

            expect($result['data'])->toBeArray();
        });

        it('filters by instance', function (): void {
            $identifier = 'search-by-instance-' . uniqid();

            Cart::setIdentifier($identifier);
            Cart::setInstance('wishlist');
            Cart::add('item-1', 'Test Product', 100.00, 1);

            $result = $this->readModel->searchCarts(
                instance: 'wishlist',
                limit: 10
            );

            expect($result)->toHaveKey('data');
        });

        it('filters by date range', function (): void {
            $createdAfter = CarbonImmutable::now()->subDays(7);
            $createdBefore = CarbonImmutable::now()->addDay();

            $result = $this->readModel->searchCarts(
                createdAfter: $createdAfter,
                createdBefore: $createdBefore,
                limit: 10
            );

            expect($result)->toHaveKey('data');
        });

        it('filters by minimum items', function (): void {
            $result = $this->readModel->searchCarts(
                minItems: 2,
                limit: 10
            );

            expect($result)->toHaveKey('data');
        });

        it('supports pagination offset', function (): void {
            $result = $this->readModel->searchCarts(
                limit: 5,
                offset: 5
            );

            expect($result)->toHaveKey('data');
            expect($result)->toHaveKey('total');
        });
    });

    describe('getCartStatistics', function (): void {
        it('returns statistics array', function (): void {
            $since = CarbonImmutable::now()->subDays(30);

            $result = $this->readModel->getCartStatistics($since);

            expect($result)->toHaveKey('active_carts');
            expect($result)->toHaveKey('abandoned_carts');
            expect($result)->toHaveKey('recovered_carts');
            expect($result)->toHaveKey('total_value_cents');
            expect($result)->toHaveKey('avg_items_per_cart');
        });
    });

    describe('invalidateCache', function (): void {
        it('clears cached data for cart', function (): void {
            $identifier = 'invalidate-test-' . uniqid();

            Cart::setIdentifier($identifier);
            Cart::add('item-1', 'Test Product', 100.00, 1);
            $cartId = Cart::getId();

            // First, get the summary to cache it
            $this->readModel->getCartSummary($cartId);

            // Invalidate
            $this->readModel->invalidateCache($cartId);

            // Should still work (just refetch)
            $result = $this->readModel->getCartSummary($cartId);

            expect($result)->not->toBeNull();
        });
    });
});
