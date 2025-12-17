<?php

declare(strict_types=1);

use AIArmada\Cart\Security\CartRateLimiter;
use AIArmada\Cart\Security\CartRateLimitResult;
use Illuminate\Support\Facades\RateLimiter;

describe('CartRateLimiter', function (): void {
    beforeEach(function (): void {
        RateLimiter::clear('cart:add_item:test-user:minute');
        RateLimiter::clear('cart:add_item:test-user:hour');
    });

    it('can be instantiated with default limits', function (): void {
        $limiter = new CartRateLimiter;

        expect($limiter)->toBeInstanceOf(CartRateLimiter::class);
    });

    it('returns available keys with getLimits', function (): void {
        $limiter = new CartRateLimiter;
        $limits = $limiter->getLimits();

        expect($limits)->toBeArray()
            ->and($limits)->toHaveKey('add_item')
            ->and($limits)->toHaveKey('default')
            ->and($limits['add_item'])->toHaveKey('perMinute')
            ->and($limits['add_item'])->toHaveKey('perHour');
    });

    describe('when enabled', function (): void {
        it('allows operations within limit', function (): void {
            $limiter = new CartRateLimiter;

            $result = $limiter->check('test-user', 'add_item');

            expect($result)->toBeInstanceOf(CartRateLimitResult::class)
                ->and($result->allowed)->toBeTrue();
        });

        it('returns remaining attempts', function (): void {
            $limiter = new CartRateLimiter;

            $result = $limiter->check('test-user', 'add_item');

            expect($result->remainingMinute)->toBeLessThanOrEqual(60)
                ->and($result->remainingHour)->toBeLessThanOrEqual(500);
        });

        it('gets remaining attempts for operation', function (): void {
            $limiter = new CartRateLimiter;

            $remaining = $limiter->remaining('test-user', 'add_item');

            expect($remaining)->toHaveKey('minute')
                ->and($remaining)->toHaveKey('hour');
        });

        it('clears rate limit for specific operation', function (): void {
            $limiter = new CartRateLimiter;

            // Make some attempts
            $limiter->check('test-user', 'add_item');
            $limiter->check('test-user', 'add_item');

            // Clear
            $limiter->clear('test-user', 'add_item');

            // Remaining should be reset
            $remaining = $limiter->remaining('test-user', 'add_item');
            expect($remaining['minute'])->toBe(60);
        });

        it('clears all rate limits for identifier', function (): void {
            $limiter = new CartRateLimiter;

            // Make attempts on different operations
            $limiter->check('test-user', 'add_item');
            $limiter->check('test-user', 'remove_item');

            // Clear all
            $limiter->clearAll('test-user');

            // All should be reset
            $remaining = $limiter->remaining('test-user', 'add_item');
            expect($remaining['minute'])->toBe(60);
        });

        it('checks multiple operations', function (): void {
            $limiter = new CartRateLimiter;

            $result = $limiter->checkMultiple('test-user', ['add_item', 'update_item']);

            expect($result->allowed)->toBeTrue()
                ->and($result->operation)->toBe('batch');
        });

        it('uses default limits for unknown operation', function (): void {
            $limiter = new CartRateLimiter;

            $result = $limiter->check('test-user', 'unknown_operation');

            expect($result->allowed)->toBeTrue();
        });
    });

    describe('when disabled', function (): void {
        it('always allows operations', function (): void {
            $limiter = new CartRateLimiter(null, 'cart', false);

            $result = $limiter->check('test-user', 'add_item');

            expect($result->allowed)->toBeTrue()
                ->and($result->remainingMinute)->toBe(PHP_INT_MAX);
        });

        it('returns max remaining when disabled', function (): void {
            $limiter = new CartRateLimiter(null, 'cart', false);

            $remaining = $limiter->remaining('test-user', 'add_item');

            expect($remaining['minute'])->toBe(PHP_INT_MAX)
                ->and($remaining['hour'])->toBe(PHP_INT_MAX);
        });

        it('always allows batch operations', function (): void {
            $limiter = new CartRateLimiter(null, 'cart', false);

            $result = $limiter->checkMultiple('test-user', ['add_item', 'remove_item']);

            expect($result->allowed)->toBeTrue();
        });
    });

    describe('trust multiplier', function (): void {
        it('increases limits with trust multiplier', function (): void {
            $limiter = new CartRateLimiter;
            $trustedLimiter = $limiter->withTrustMultiplier(2.0);

            $limits = $trustedLimiter->getLimits();

            expect($limits['add_item']['perMinute'])->toBe(120) // 60 * 2
                ->and($limits['add_item']['perHour'])->toBe(1000); // 500 * 2
        });

        it('creates new instance with adjusted limits', function (): void {
            $limiter = new CartRateLimiter;
            $trustedLimiter = $limiter->withTrustMultiplier(1.5);

            expect($trustedLimiter)->not->toBe($limiter)
                ->and($limiter->getLimits()['add_item']['perMinute'])->toBe(60);
        });
    });

    describe('custom limits', function (): void {
        it('accepts custom limits in constructor', function (): void {
            $customLimits = [
                'add_item' => ['perMinute' => 10, 'perHour' => 50],
                'default' => ['perMinute' => 5, 'perHour' => 25],
            ];

            $limiter = new CartRateLimiter($customLimits);
            $limits = $limiter->getLimits();

            expect($limits['add_item']['perMinute'])->toBe(10)
                ->and($limits['add_item']['perHour'])->toBe(50);
        });
    });
});

describe('CartRateLimitResult', function (): void {
    it('creates allowed result', function (): void {
        $result = CartRateLimitResult::allowed('add_item', 55, 495);

        expect($result->allowed)->toBeTrue()
            ->and($result->operation)->toBe('add_item')
            ->and($result->remainingMinute)->toBe(55)
            ->and($result->remainingHour)->toBe(495);
    });

    it('creates exceeded result', function (): void {
        $result = CartRateLimitResult::exceeded('add_item', 'minute', 30, 60);

        expect($result->allowed)->toBeFalse()
            ->and($result->operation)->toBe('add_item')
            ->and($result->window)->toBe('minute')
            ->and($result->retryAfter)->toBe(30)
            ->and($result->limit)->toBe(60);
    });
});
