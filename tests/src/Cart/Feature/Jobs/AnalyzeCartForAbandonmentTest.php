<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Jobs\AnalyzeCartForAbandonment;
use Illuminate\Support\Facades\Queue;

describe('AnalyzeCartForAbandonment Job Integration', function (): void {
    beforeEach(function (): void {
        $this->cartManager = app(CartManagerInterface::class);
        Queue::fake();
    });

    describe('construction', function (): void {
        it('creates job with cart ID', function (): void {
            $job = new AnalyzeCartForAbandonment(cartId: 'test-cart-123');

            expect($job->cartId)->toBe('test-cart-123');
            expect($job->batchSize)->toBe(100);
        });

        it('creates job with custom batch size', function (): void {
            $job = new AnalyzeCartForAbandonment(batchSize: 50);

            expect($job->cartId)->toBeNull();
            expect($job->batchSize)->toBe(50);
        });

        it('creates job for batch processing when no cart ID', function (): void {
            $job = new AnalyzeCartForAbandonment;

            expect($job->cartId)->toBeNull();
        });
    });

    describe('tags', function (): void {
        it('returns specific cart tag when cart ID provided', function (): void {
            $job = new AnalyzeCartForAbandonment(cartId: 'my-cart-456');

            $tags = $job->tags();

            expect($tags)->toContain('cart-abandonment');
            expect($tags)->toContain('cart:my-cart-456');
        });

        it('returns batch tag when no cart ID', function (): void {
            $job = new AnalyzeCartForAbandonment;

            $tags = $job->tags();

            expect($tags)->toContain('cart-abandonment');
            expect($tags)->toContain('batch');
        });
    });

    describe('dispatch', function (): void {
        it('can be dispatched to queue', function (): void {
            AnalyzeCartForAbandonment::dispatch();

            Queue::assertPushed(AnalyzeCartForAbandonment::class);
        });

        it('can be dispatched with cart ID', function (): void {
            AnalyzeCartForAbandonment::dispatch('specific-cart-789');

            Queue::assertPushed(
                AnalyzeCartForAbandonment::class,
                fn($job) =>
                $job->cartId === 'specific-cart-789'
            );
        });
    });
});
