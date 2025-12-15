<?php

declare(strict_types=1);

use AIArmada\Cart\AI\ProductRecommendation;
use AIArmada\Cart\AI\ProductRecommender;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('cart.ai.recommendations', [
        'enabled' => true,
        'max_recommendations' => 5,
        'cache_ttl_seconds' => 300,
        'min_confidence' => 0.3,
    ]);
    Cache::flush();
    $this->storage = new InMemoryStorage();
});

describe('ProductRecommender', function (): void {
    it('can be instantiated', function (): void {
        $recommender = new ProductRecommender();

        expect($recommender)->toBeInstanceOf(ProductRecommender::class);
    });

    it('recommends products for a cart', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        expect($recommendations)->toBeInstanceOf(Collection::class);
    });

    it('caches recommendations', function (): void {
        $cart = new Cart($this->storage, 'cart-cache-test');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();

        $recommendations1 = $recommender->recommend($cart);
        $recommendations2 = $recommender->recommend($cart);

        expect($recommendations1->count())->toBe($recommendations2->count());
    });

    it('recommends products with user id for personalization', function (): void {
        $cart = new Cart($this->storage, 'cart-personalized');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart, 'user-123');

        expect($recommendations)->toBeInstanceOf(Collection::class);
    });

    it('recommends for recovery with filtered results', function (): void {
        $cart = new Cart($this->storage, 'cart-recovery');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommendForRecovery($cart);

        expect($recommendations)->toBeInstanceOf(Collection::class)
            ->and($recommendations->count())->toBeLessThanOrEqual(3);
    });

    it('records recommendation clicks', function (): void {
        $recommender = new ProductRecommender();

        $recommender->recordClick('product-123', 'cart-456', 'frequently_bought');

        $clicks = Cache::get('recommendations:clicks:frequently_bought', []);
        expect($clicks)->toHaveCount(1)
            ->and($clicks[0]['product_id'])->toBe('product-123')
            ->and($clicks[0]['cart_id'])->toBe('cart-456');
    });

    it('records recommendation conversions', function (): void {
        $recommender = new ProductRecommender();

        $recommender->recordConversion('product-123', 'cart-456', 'complementary');

        $conversions = Cache::get('recommendations:conversions:complementary', []);
        expect($conversions)->toHaveCount(1)
            ->and($conversions[0]['product_id'])->toBe('product-123');
    });

    it('limits stored clicks to 1000', function (): void {
        $recommender = new ProductRecommender();

        for ($i = 0; $i < 1050; $i++) {
            $recommender->recordClick("product-{$i}", 'cart-123', 'trending');
        }

        $clicks = Cache::get('recommendations:clicks:trending', []);
        expect($clicks)->toHaveCount(1000);
    });

    it('limits stored conversions to 1000', function (): void {
        $recommender = new ProductRecommender();

        for ($i = 0; $i < 1050; $i++) {
            $recommender->recordConversion("product-{$i}", 'cart-123', 'upsell');
        }

        $conversions = Cache::get('recommendations:conversions:upsell', []);
        expect($conversions)->toHaveCount(1000);
    });

    it('returns statistics for all recommendation types', function (): void {
        $recommender = new ProductRecommender();

        $recommender->recordClick('p1', 'c1', 'frequently_bought');
        $recommender->recordClick('p2', 'c2', 'frequently_bought');
        $recommender->recordConversion('p1', 'c1', 'frequently_bought');

        $stats = $recommender->getStatistics();

        expect($stats)->toBeArray()
            ->and($stats)->toHaveKeys(['frequently_bought', 'complementary', 'personalized', 'upsell', 'trending'])
            ->and($stats['frequently_bought']['clicks'])->toBe(2)
            ->and($stats['frequently_bought']['conversions'])->toBe(1)
            ->and($stats['frequently_bought']['conversion_rate'])->toBe(50.0);
    });

    it('handles empty cart for recommendations', function (): void {
        $cart = new Cart($this->storage, 'cart-empty');

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        expect($recommendations)->toBeInstanceOf(Collection::class);
    });

    it('generates upsell recommendations for non-premium items', function (): void {
        $cart = new Cart($this->storage, 'cart-upsell');
        $cart->add('item-basic', 'Basic Product', 5000, 1, ['category' => 'software', 'tier' => 'standard']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        expect($recommendations)->toBeInstanceOf(Collection::class);
    });

    it('respects min confidence setting', function (): void {
        Config::set('cart.ai.recommendations.min_confidence', 0.8);
        Cache::flush();

        $cart = new Cart($this->storage, 'cart-high-confidence');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        foreach ($recommendations as $rec) {
            expect($rec->confidence)->toBeGreaterThanOrEqual(0.8);
        }
    });

    it('respects max recommendations setting', function (): void {
        Config::set('cart.ai.recommendations.max_recommendations', 3);
        Cache::flush();

        $cart = new Cart($this->storage, 'cart-max-recs');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        expect($recommendations->count())->toBeLessThanOrEqual(3);
    });

    it('handles items without category attribute', function (): void {
        $cart = new Cart($this->storage, 'cart-no-cat');
        $cart->add('item-no-category', 'Uncategorized Product', 1000, 1);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        expect($recommendations)->toBeInstanceOf(Collection::class);
    });

    it('includes trending products in recommendations', function (): void {
        Cache::put('products:trending', [
            ['product_id' => 'trending-1', 'name' => 'Trending Product 1', 'trend_score' => 0.9, 'price' => 2000],
            ['product_id' => 'trending-2', 'name' => 'Trending Product 2', 'trend_score' => 0.8, 'price' => 3000],
        ], 3600);

        $cart = new Cart($this->storage, 'cart-trending');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        $trendingRecs = $recommendations->filter(fn (ProductRecommendation $r) => $r->type === 'trending');
        expect($trendingRecs)->not->toBeEmpty();
    });

    it('excludes cart items from trending recommendations', function (): void {
        Cache::put('products:trending', [
            ['product_id' => 'item-1', 'name' => 'Already in cart', 'trend_score' => 0.9, 'price' => 1000],
            ['product_id' => 'trending-new', 'name' => 'New Trending', 'trend_score' => 0.8, 'price' => 2000],
        ], 3600);

        $cart = new Cart($this->storage, 'cart-exclude');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        $inCartRecs = $recommendations->filter(fn (ProductRecommendation $r) => $r->productId === 'item-1');
        expect($inCartRecs)->toBeEmpty();
    });

    it('includes complementary products based on category', function (): void {
        Cache::put('category:complements:electronics', [
            ['product_id' => 'comp-1', 'name' => 'Complementary 1', 'confidence' => 0.7, 'price' => 500],
        ], 3600);

        $cart = new Cart($this->storage, 'cart-comp');
        $cart->add('item-1', 'Electronic Device', 10000, 1, ['category' => 'electronics']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart);

        $compRecs = $recommendations->filter(fn (ProductRecommendation $r) => $r->type === 'complementary');
        expect($compRecs)->not->toBeEmpty();
    });

    it('includes personalized recommendations for authenticated users', function (): void {
        Cache::put('user:preferences:user-123', [
            ['product_id' => 'pref-1', 'name' => 'Preferred Product', 'score' => 0.8, 'price' => 1500],
        ], 3600);

        $cart = new Cart($this->storage, 'cart-pers');
        $cart->add('item-1', 'Test Product', 1000, 1, ['category' => 'books']);

        $recommender = new ProductRecommender();
        $recommendations = $recommender->recommend($cart, 'user-123');

        $persRecs = $recommendations->filter(fn (ProductRecommendation $r) => $r->type === 'personalized');
        expect($persRecs)->not->toBeEmpty();
    });

    it('calculates zero conversion rate when no clicks', function (): void {
        $recommender = new ProductRecommender();
        $stats = $recommender->getStatistics();

        foreach ($stats as $data) {
            expect($data['conversion_rate'])->toBe(0.0);
        }
    });
});
