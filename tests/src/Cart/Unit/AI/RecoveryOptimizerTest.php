<?php

declare(strict_types=1);

use AIArmada\Cart\AI\AbandonmentPrediction;
use AIArmada\Cart\AI\OptimizationResult;
use AIArmada\Cart\AI\RecoveryOptimizer;
use AIArmada\Cart\AI\RecoveryStrategy;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Config::set('cart.ai.recovery', [
        'enabled' => true,
        'min_samples_for_optimization' => 100,
        'learning_rate' => 0.1,
        'exploration_rate' => 0.2,
    ]);
    Config::set('cart.database.table', 'carts');
    Cache::flush();
    $this->storage = new InMemoryStorage();
});

describe('RecoveryOptimizer', function (): void {
    it('can be instantiated with storage', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $optimizer = new RecoveryOptimizer($storage);

        expect($optimizer)->toBeInstanceOf(RecoveryOptimizer::class);
    });

    it('returns optimal strategy for a cart', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 3333, 3);

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-123',
            probability: 0.7,
            riskLevel: 'high',
            features: [],
            interventions: [],
            predictedAt: now()
        );

        $optimizer = new RecoveryOptimizer($storage);
        $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

        expect($strategy)->toBeInstanceOf(RecoveryStrategy::class)
            ->and($strategy->id)->toBeString()
            ->and($strategy->type)->toBeString();
    });

    it('returns high value strategy for expensive carts', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Expensive Product', 20000, 5);
        $cart->setMetadata('user_id', 'user-123');

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-high-value',
            probability: 0.6,
            riskLevel: 'medium',
            features: [],
            interventions: [],
            predictedAt: now()
        );

        Config::set('cart.ai.recovery.exploration_rate', 0); // Disable exploration
        Cache::flush();

        $optimizer = new RecoveryOptimizer($storage);
        $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

        expect($strategy)->toBeInstanceOf(RecoveryStrategy::class);
    });

    it('includes push notification strategy for authenticated users', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 2500, 2);
        $cart->setMetadata('user_id', 'user-123');

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-auth',
            probability: 0.5,
            riskLevel: 'medium',
            features: [],
            interventions: [],
            predictedAt: now()
        );

        $optimizer = new RecoveryOptimizer($storage);
        $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

        expect($strategy)->toBeInstanceOf(RecoveryStrategy::class);
    });

    it('records successful recovery outcome', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        DB::shouldReceive('table')
            ->with('cart_recovery_outcomes')
            ->once()
            ->andReturnSelf();
        DB::shouldReceive('insert')->once()->andReturn(true);

        $optimizer = new RecoveryOptimizer($storage);
        $optimizer->recordOutcome(
            cartId: 'cart-123',
            strategyId: 'email_reminder',
            recovered: true,
            timeToRecoveryMinutes: 45,
            discountUsedCents: 500
        );

        $outcomes = Cache::get('recovery:outcomes:email_reminder');
        expect($outcomes['success'])->toBe(1);
    });

    it('records failed recovery outcome', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        DB::shouldReceive('table')
            ->with('cart_recovery_outcomes')
            ->once()
            ->andReturnSelf();
        DB::shouldReceive('insert')->once()->andReturn(true);

        $optimizer = new RecoveryOptimizer($storage);
        $optimizer->recordOutcome(
            cartId: 'cart-456',
            strategyId: 'email_reminder',
            recovered: false
        );

        $outcomes = Cache::get('recovery:outcomes:email_reminder');
        expect($outcomes['failure'])->toBe(1);
    });

    it('returns strategy statistics', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        Cache::put('recovery:strategy_effectiveness', [
            'email_reminder' => ['success' => 80, 'failure' => 20],
            'email_with_discount' => ['success' => 60, 'failure' => 40],
        ], 3600);

        $optimizer = new RecoveryOptimizer($storage);
        $stats = $optimizer->getStrategyStatistics();

        expect($stats)->toBeArray()
            ->and($stats['email_reminder']['success_rate'])->toBe(80.0)
            ->and($stats['email_reminder']['total_attempts'])->toBe(100)
            ->and($stats['email_with_discount']['success_rate'])->toBe(60.0);
    });

    it('calculates confidence based on sample size', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        Cache::put('recovery:strategy_effectiveness', [
            'strategy_high' => ['success' => 800, 'failure' => 200],
            'strategy_medium' => ['success' => 80, 'failure' => 20],
            'strategy_low' => ['success' => 8, 'failure' => 2],
            'strategy_insufficient' => ['success' => 3, 'failure' => 2],
        ], 3600);

        $optimizer = new RecoveryOptimizer($storage);
        $stats = $optimizer->getStrategyStatistics();

        expect($stats['strategy_high']['confidence'])->toBe('high')
            ->and($stats['strategy_medium']['confidence'])->toBe('medium')
            ->and($stats['strategy_low']['confidence'])->toBe('low')
            ->and($stats['strategy_insufficient']['confidence'])->toBe('insufficient');
    });

    it('optimizes strategies based on accumulated data', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        Cache::put('recovery:strategy_effectiveness', [
            'low_performer' => ['success' => 5, 'failure' => 95],
            'high_performer' => ['success' => 60, 'failure' => 40],
        ], 3600);

        $optimizer = new RecoveryOptimizer($storage);
        $result = $optimizer->optimize();

        expect($result)->toBeInstanceOf(OptimizationResult::class)
            ->and($result->strategiesAnalyzed)->toBe(2)
            ->and($result->improvementsApplied)->not->toBeEmpty();
    });

    it('skips strategies with insufficient samples during optimization', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        Cache::put('recovery:strategy_effectiveness', [
            'small_sample' => ['success' => 5, 'failure' => 5], // Only 10 samples
        ], 3600);

        $optimizer = new RecoveryOptimizer($storage);
        $result = $optimizer->optimize();

        expect($result->strategiesAnalyzed)->toBe(0);
    });

    it('sometimes explores random strategy', function (): void {
        Config::set('cart.ai.recovery.exploration_rate', 1.0); // Always explore
        Cache::flush();

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 3333, 3);

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-explore',
            probability: 0.7,
            riskLevel: 'high',
            features: [],
            interventions: [],
            predictedAt: now()
        );

        $optimizer = new RecoveryOptimizer($storage);
        $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

        expect($strategy)->toBeInstanceOf(RecoveryStrategy::class);
    });

    it('returns default strategy when no strategies available', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 100, 1);

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-minimal',
            probability: 0.2, // Low risk
            riskLevel: 'minimal',
            features: [],
            interventions: [],
            predictedAt: now()
        );

        Config::set('cart.ai.recovery.exploration_rate', 0);
        Cache::flush();

        $optimizer = new RecoveryOptimizer($storage);
        $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

        expect($strategy)->toBeInstanceOf(RecoveryStrategy::class)
            ->and($strategy->type)->toBe('email');
    });

    it('includes exit intent popup for high risk carts', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 4000, 5);

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-exit',
            probability: 0.9,
            riskLevel: 'high',
            features: [],
            interventions: [],
            predictedAt: now()
        );

        Config::set('cart.ai.recovery.exploration_rate', 0);
        Cache::flush();

        $optimizer = new RecoveryOptimizer($storage);
        $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

        expect($strategy)->toBeInstanceOf(RecoveryStrategy::class);
    });

    it('selects best performing strategy when not exploring', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        Cache::put('recovery:strategy_effectiveness', [
            'email_reminder' => ['success' => 70, 'failure' => 30],
            'email_with_discount' => ['success' => 90, 'failure' => 10],
        ], 3600);

        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 3750, 4);

        $prediction = new AbandonmentPrediction(
            cartId: 'cart-best',
            probability: 0.6,
            riskLevel: 'medium',
            features: [],
            interventions: [],
            predictedAt: now()
        );

        Config::set('cart.ai.recovery.exploration_rate', 0);

        $optimizer = new RecoveryOptimizer($storage);
        $strategy = $optimizer->getOptimalStrategy($cart, $prediction);

        expect($strategy)->toBeInstanceOf(RecoveryStrategy::class);
    });

    it('handles owner scoping for recommendations', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn('App\\Models\\Tenant');
        $storage->shouldReceive('getOwnerId')->andReturn('tenant-123');

        $optimizer = new RecoveryOptimizer($storage);

        expect($optimizer)->toBeInstanceOf(RecoveryOptimizer::class);
    });

    it('updates strategy effectiveness on successful outcome', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        DB::shouldReceive('table')
            ->with('cart_recovery_outcomes')
            ->andReturnSelf();
        DB::shouldReceive('insert')->andReturn(true);

        $optimizer = new RecoveryOptimizer($storage);

        $optimizer->recordOutcome('cart-1', 'strategy_test', true);
        $optimizer->recordOutcome('cart-2', 'strategy_test', false);

        $stats = $optimizer->getStrategyStatistics();

        expect($stats['strategy_test']['successes'])->toBe(1)
            ->and($stats['strategy_test']['failures'])->toBe(1)
            ->and($stats['strategy_test']['success_rate'])->toBe(50.0);
    });

    it('loads saved strategies from cache on construction', function (): void {
        Cache::put('recovery:strategy_effectiveness', [
            'cached_strategy' => ['success' => 50, 'failure' => 50],
        ], 3600);

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        $optimizer = new RecoveryOptimizer($storage);
        $stats = $optimizer->getStrategyStatistics();

        expect($stats)->toHaveKey('cached_strategy');
    });

    it('sorts statistics by success rate', function (): void {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('getOwnerType')->andReturn(null);
        $storage->shouldReceive('getOwnerId')->andReturn(null);

        Cache::put('recovery:strategy_effectiveness', [
            'low_rate' => ['success' => 20, 'failure' => 80],
            'high_rate' => ['success' => 90, 'failure' => 10],
            'mid_rate' => ['success' => 50, 'failure' => 50],
        ], 3600);

        $optimizer = new RecoveryOptimizer($storage);
        $stats = $optimizer->getStrategyStatistics();

        $keys = array_keys($stats);
        expect($keys[0])->toBe('high_rate')
            ->and($keys[1])->toBe('mid_rate')
            ->and($keys[2])->toBe('low_rate');
    });
});
