<?php

declare(strict_types=1);

use AIArmada\Shipping\Services\RetryService;

// ============================================
// RetryService Tests
// ============================================

describe('RetryService', function (): void {
    it('executes callback successfully on first try', function (): void {
        $result = RetryService::make()
            ->execute(fn () => 'success');

        expect($result)->toBe('success');
    });

    it('retries on transient failure and succeeds', function (): void {
        $attempts = 0;

        $result = RetryService::make()
            ->attempts(3)
            ->delay(10)
            ->execute(function () use (&$attempts) {
                $attempts++;
                if ($attempts < 3) {
                    throw new RuntimeException('timeout error');
                }

                return 'success after retries';
            });

        expect($result)->toBe('success after retries');
        expect($attempts)->toBe(3);
    });

    it('throws exception after max retries exhausted', function (): void {
        $attempts = 0;

        RetryService::make()
            ->attempts(2)
            ->delay(10)
            ->execute(function () use (&$attempts): void {
                $attempts++;

                throw new RuntimeException('timeout error');
            });
    })->throws(RuntimeException::class, 'timeout error');

    it('does not retry on non-transient exceptions', function (): void {
        $attempts = 0;

        try {
            RetryService::make()
                ->attempts(3)
                ->delay(10)
                ->execute(function () use (&$attempts): void {
                    $attempts++;

                    throw new InvalidArgumentException('Invalid input');
                });
        } catch (InvalidArgumentException) {
            // Expected
        }

        expect($attempts)->toBe(1);
    });

    it('retries on specified exception types', function (): void {
        $attempts = 0;

        try {
            RetryService::make()
                ->attempts(3)
                ->delay(10)
                ->execute(
                    function () use (&$attempts): void {
                        $attempts++;

                        throw new InvalidArgumentException('test');
                    },
                    [InvalidArgumentException::class]
                );
        } catch (InvalidArgumentException) {
            // Expected
        }

        expect($attempts)->toBe(3);
    });

    it('can configure attempts', function (): void {
        $retry = RetryService::make()->attempts(5);

        expect($retry)->toBeInstanceOf(RetryService::class);
    });

    it('can configure delay', function (): void {
        $retry = RetryService::make()->delay(500);

        expect($retry)->toBeInstanceOf(RetryService::class);
    });

    it('can configure backoff multiplier', function (): void {
        $retry = RetryService::make()->backoff(3.0);

        expect($retry)->toBeInstanceOf(RetryService::class);
    });

    it('can disable jitter', function (): void {
        $retry = RetryService::make()->withJitter(false);

        expect($retry)->toBeInstanceOf(RetryService::class);
    });

    it('identifies timeout as retryable', function (): void {
        $attempts = 0;

        try {
            RetryService::make()
                ->attempts(2)
                ->delay(10)
                ->execute(function () use (&$attempts): void {
                    $attempts++;

                    throw new RuntimeException('Connection timed out');
                });
        } catch (RuntimeException) {
            // Expected
        }

        expect($attempts)->toBe(2);
    });

    it('identifies 503 as retryable', function (): void {
        $attempts = 0;

        try {
            RetryService::make()
                ->attempts(2)
                ->delay(10)
                ->execute(function () use (&$attempts): void {
                    $attempts++;

                    throw new RuntimeException('503 Service Unavailable');
                });
        } catch (RuntimeException) {
            // Expected
        }

        expect($attempts)->toBe(2);
    });

    it('identifies rate limit as retryable', function (): void {
        $attempts = 0;

        try {
            RetryService::make()
                ->attempts(2)
                ->delay(10)
                ->execute(function () use (&$attempts): void {
                    $attempts++;

                    throw new RuntimeException('Rate limit exceeded');
                });
        } catch (RuntimeException) {
            // Expected
        }

        expect($attempts)->toBe(2);
    });
});
