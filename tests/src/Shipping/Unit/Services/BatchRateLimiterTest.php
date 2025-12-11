<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Shipping\Services\BatchRateLimiter;
use Illuminate\Support\Facades\RateLimiter;

uses(TestCase::class);

// ============================================
// BatchRateLimiter Tests
// ============================================

beforeEach(function (): void {
    // Clear rate limiters before each test
    RateLimiter::clear('shipping:test:bulk:process');
});

describe('BatchRateLimiter', function (): void {
    it('can be instantiated via make', function (): void {
        $limiter = BatchRateLimiter::make();

        expect($limiter)->toBeInstanceOf(BatchRateLimiter::class);
    });

    it('can be configured for a carrier', function (): void {
        $limiter = BatchRateLimiter::forCarrier('jnt');

        expect($limiter)->toBeInstanceOf(BatchRateLimiter::class);
    });

    it('processes all items in batch', function (): void {
        $items = [1, 2, 3, 4, 5];

        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->maxAttempts(100)
            ->batchSize(10)
            ->execute($items, fn ($item) => $item * 2, 'process');

        expect($results)->toHaveCount(5);
        expect($results[0]['success'])->toBeTrue();
        expect($results[0]['result'])->toBe(2);
        expect($results[4]['result'])->toBe(10);
    });

    it('captures errors for failed items', function (): void {
        $items = [1, 2, 3];

        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->maxAttempts(100)
            ->execute($items, function ($item) {
                if ($item === 2) {
                    throw new RuntimeException('Item 2 failed');
                }

                return $item;
            }, 'process');

        expect($results[0]['success'])->toBeTrue();
        expect($results[1]['success'])->toBeFalse();
        expect($results[1]['error'])->toBe('Item 2 failed');
        expect($results[2]['success'])->toBeTrue();
    });

    it('can configure max attempts', function (): void {
        $limiter = BatchRateLimiter::make()->maxAttempts(20);

        expect($limiter)->toBeInstanceOf(BatchRateLimiter::class);
    });

    it('can configure decay seconds', function (): void {
        $limiter = BatchRateLimiter::make()->decaySeconds(120);

        expect($limiter)->toBeInstanceOf(BatchRateLimiter::class);
    });

    it('can configure batch delay', function (): void {
        $limiter = BatchRateLimiter::make()->batchDelay(500);

        expect($limiter)->toBeInstanceOf(BatchRateLimiter::class);
    });

    it('can configure batch size', function (): void {
        $limiter = BatchRateLimiter::make()->batchSize(10);

        expect($limiter)->toBeInstanceOf(BatchRateLimiter::class);
    });

    it('can configure key prefix', function (): void {
        $limiter = BatchRateLimiter::make()->keyPrefix('custom:prefix');

        expect($limiter)->toBeInstanceOf(BatchRateLimiter::class);
    });

    it('preserves array keys', function (): void {
        $items = ['a' => 1, 'b' => 2, 'c' => 3];

        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->maxAttempts(100)
            ->execute($items, fn ($item) => $item, 'process');

        expect($results)->toHaveKeys(['a', 'b', 'c']);
    });

    it('handles empty items gracefully', function (): void {
        $results = BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->execute([], fn ($item) => $item, 'process');

        expect($results)->toBeEmpty();
    });

    it('processes items in batches', function (): void {
        $items = range(1, 10);
        $processedOrder = [];

        BatchRateLimiter::make()
            ->keyPrefix('shipping:test')
            ->maxAttempts(100)
            ->batchSize(3)
            ->batchDelay(0)
            ->execute($items, function ($item) use (&$processedOrder) {
                $processedOrder[] = $item;

                return $item;
            }, 'process');

        expect($processedOrder)->toBe(range(1, 10));
    });
});
