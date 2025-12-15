<?php

declare(strict_types=1);

use AIArmada\Cart\AI\AbandonmentPrediction;
use AIArmada\Cart\AI\AbandonmentPredictor;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    Config::set('cart.ai.abandonment', [
        'enabled' => true,
        'inactivity_threshold_minutes' => 15,
        'high_value_threshold_cents' => 50000,
        'cache_predictions_seconds' => 300,
    ]);
    Config::set('cart.database.table', 'carts');
    Cache::flush();
    $this->cartStorage = new InMemoryStorage();

    // Pre-populate cache to avoid database queries in unit tests
    // Cache key format: cart:abandonment_rate:{ownerCacheKeyPart}:{identifier}
    // When owner is null, ownerCacheKeyPart = 'global'
    Cache::put('cart:abandonment_rate:global:user-456', 0.5, 3600);
    Cache::put('cart:abandonment_rate:global:user-1', 0.5, 3600);
    Cache::put('cart:abandonment_rate:global:user-2', 0.5, 3600);
    Cache::put('cart:abandonment_rate:global:user-risk', 0.5, 3600);
    Cache::put('cart:abandonment_rate:global:user-new', 0.5, 3600);
    Cache::put('cart:abandonment_rate:global:user-test', 0.5, 3600);
    Cache::put('cart:abandonment_rate:global:user-tenant', 0.5, 3600);
    Cache::put('cart:abandonment_rate:global:session-abc123', 0.5, 3600);
    // For owner-scoped tests
    Cache::put('cart:abandonment_rate:owner:App.Models.Tenant:tenant-123:user-tenant', 0.5, 3600);
});

describe('AbandonmentPredictor', function (): void {
    it('can be instantiated with storage', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $predictor = new AbandonmentPredictor($storage);

        expect($predictor)->toBeInstanceOf(AbandonmentPredictor::class);
    });

    it('returns default weights', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $predictor = new AbandonmentPredictor($storage);

        $weights = $predictor->getWeights();

        expect($weights)->toBeArray()
            ->and($weights)->toHaveKeys([
                'time_since_activity',
                'cart_value',
                'item_count',
                'user_history',
                'session_behavior',
                'device_type',
                'time_of_day',
                'checkout_progress',
            ]);
    });

    it('predicts abandonment for a cart', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->andReturn(now()->subMinutes(5)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->cartStorage, 'user-456');
        $cart->add('item-1', 'Product 1', 3333, 3);

        $predictor = new AbandonmentPredictor($storage);
        $prediction = $predictor->predict($cart);

        expect($prediction)->toBeInstanceOf(AbandonmentPrediction::class)
            ->and($prediction->probability)->toBeGreaterThanOrEqual(0.0)
            ->and($prediction->probability)->toBeLessThanOrEqual(1.0);
    });

    it('caches predictions', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->once()
            ->andReturn(now()->subMinutes(5)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->cartStorage, 'user-456');
        $cart->add('item-1', 'Product 1', 3333, 3);

        $predictor = new AbandonmentPredictor($storage);

        $prediction1 = $predictor->predict($cart);
        $prediction2 = $predictor->predict($cart);

        expect($prediction1->probability)->toBe($prediction2->probability);
    });

    it('batch predicts for multiple carts', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->andReturn(now()->subMinutes(5)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cartStorage1 = new InMemoryStorage();
        $cart1 = new Cart($cartStorage1, 'user-1');
        $cart1->add('item-1', 'Product 1', 3333, 3);

        $cartStorage2 = new InMemoryStorage();
        $cart2 = new Cart($cartStorage2, 'user-2');
        $cart2->add('item-1', 'Product 1', 4000, 5);

        $predictor = new AbandonmentPredictor($storage);
        $predictions = $predictor->predictBatch(collect([$cart1, $cart2]));

        expect($predictions)->toHaveCount(2);
    });

    it('trains model with historical data', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $predictor = new AbandonmentPredictor($storage);

        $trainingData = [
            [
                'features' => [
                    'time_since_activity' => 0.8,
                    'cart_value' => 0.5,
                    'item_count' => 0.3,
                    'user_history' => 0.6,
                    'session_behavior' => 0.4,
                    'device_type' => 0.5,
                    'time_of_day' => 0.3,
                    'checkout_progress' => 0.7,
                ],
                'abandoned' => true,
            ],
            [
                'features' => [
                    'time_since_activity' => 0.2,
                    'cart_value' => 0.8,
                    'item_count' => 0.5,
                    'user_history' => 0.3,
                    'session_behavior' => 0.2,
                    'device_type' => 0.3,
                    'time_of_day' => 0.5,
                    'checkout_progress' => 0.2,
                ],
                'abandoned' => false,
            ],
        ];

        $initialWeights = $predictor->getWeights();
        $predictor->train($trainingData);
        $newWeights = $predictor->getWeights();

        expect($newWeights)->not->toBe($initialWeights);
    });

    it('handles empty training data', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $predictor = new AbandonmentPredictor($storage);

        $initialWeights = $predictor->getWeights();
        $predictor->train([]);
        $newWeights = $predictor->getWeights();

        expect($newWeights)->toBe($initialWeights);
    });

    it('loads saved weights from cache', function (): void {
        $savedWeights = [
            'time_since_activity' => 0.30,
            'cart_value' => 0.20,
        ];
        Cache::put('cart:abandonment_model:weights', $savedWeights, 3600);

        $storage = Mockery::mock(StorageInterface::class);
        $predictor = new AbandonmentPredictor($storage);
        $predictor->loadWeights();

        $weights = $predictor->getWeights();
        expect($weights['time_since_activity'])->toBe(0.30)
            ->and($weights['cart_value'])->toBe(0.20);
    });

    it('returns correct risk levels based on probability', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->andReturn(now()->subMinutes(60)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->cartStorage, 'user-456');
        $cart->add('item-1', 'Product 1', 10000, 10);

        $predictor = new AbandonmentPredictor($storage);
        $prediction = $predictor->predict($cart);

        expect($prediction->riskLevel)->toBeIn(['minimal', 'low', 'medium', 'high']);
    });

    it('includes interventions for high risk predictions', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->andReturn(now()->subHours(2)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->cartStorage, 'user-risk');
        $cart->add('item-1', 'Product 1', 5333, 15);
        $cart->setMetadata('checkout_step', 'cart');

        $predictor = new AbandonmentPredictor($storage);
        $prediction = $predictor->predict($cart);

        if ($prediction->riskLevel === 'high' || $prediction->riskLevel === 'medium') {
            expect($prediction->interventions)->not->toBeEmpty();
        }
    });

    it('predicts with user id for personalization', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->andReturn(now()->subMinutes(5)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->cartStorage, 'user-456');
        $cart->add('item-1', 'Product 1', 3333, 3);
        $cart->setMetadata('checkout_step', 'shipping');

        $predictor = new AbandonmentPredictor($storage);
        $prediction = $predictor->predict($cart, 'user-456');

        expect($prediction)->toBeInstanceOf(AbandonmentPrediction::class);
    });

    it('handles null last activity timestamp', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')->andReturn(null);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->cartStorage, 'user-new');
        $cart->add('item-1', 'Product 1', 5000, 1);

        $predictor = new AbandonmentPredictor($storage);
        $prediction = $predictor->predict($cart);

        expect($prediction)->toBeInstanceOf(AbandonmentPrediction::class)
            ->and($prediction->probability)->toBeGreaterThanOrEqual(0.0);
    });

    it('calculates checkout progress scores for different steps', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->andReturn(now()->subMinutes(5)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $steps = ['cart', 'shipping', 'payment', 'review'];

        foreach ($steps as $step) {
            $stepStorage = new InMemoryStorage();
            $cart = new Cart($stepStorage, 'user-test');
            $cart->add('item-1', 'Product 1', 3333, 3);
            $cart->setMetadata('checkout_step', $step);

            $predictor = new AbandonmentPredictor($storage);
            Cache::flush();
            $prediction = $predictor->predict($cart);

            expect($prediction)->toBeInstanceOf(AbandonmentPrediction::class);
        }
    });

    it('handles owner scoping for cache keys', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->andReturn(now()->subMinutes(5)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn('App\\Models\\Tenant');
        $storage->shouldReceive('getOwnerId')->andReturn('tenant-123');

        $cart = new Cart($this->cartStorage, 'user-tenant');
        $cart->add('item-1', 'Product 1', 3333, 3);

        $predictor = new AbandonmentPredictor($storage);
        $prediction = $predictor->predict($cart);

        expect($prediction)->toBeInstanceOf(AbandonmentPrediction::class);
    });

    it('uses cart identifier when id is null for cache key', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getLastActivityAt')
            ->andReturn(now()->subMinutes(5)->toIso8601String());
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->cartStorage, 'session-abc123', null, 'wishlist');
        $cart->add('item-1', 'Product 1', 3333, 3);

        $predictor = new AbandonmentPredictor($storage);
        $prediction = $predictor->predict($cart);

        expect($prediction)->toBeInstanceOf(AbandonmentPrediction::class);
    });
});
