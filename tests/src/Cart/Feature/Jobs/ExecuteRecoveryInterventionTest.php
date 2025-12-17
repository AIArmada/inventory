<?php

declare(strict_types=1);

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Jobs\ExecuteRecoveryIntervention;
use Illuminate\Support\Facades\Queue;

describe('ExecuteRecoveryIntervention Job Integration', function (): void {
    beforeEach(function (): void {
        $this->cartManager = app(CartManagerInterface::class);
        Queue::fake();
    });

    describe('construction', function (): void {
        it('creates job with all parameters', function (): void {
            $job = new ExecuteRecoveryIntervention(
                cartId: 'cart-123',
                strategyId: 'email_reminder',
                strategy: ['type' => 'email', 'delayMinutes' => 30],
                prediction: ['probability' => 0.8, 'riskLevel' => 'high']
            );

            expect($job->cartId)->toBe('cart-123');
            expect($job->strategyId)->toBe('email_reminder');
            expect($job->strategy)->toBe(['type' => 'email', 'delayMinutes' => 30]);
            expect($job->prediction)->toBe(['probability' => 0.8, 'riskLevel' => 'high']);
        });
    });

    describe('tags', function (): void {
        it('returns appropriate tags', function (): void {
            $job = new ExecuteRecoveryIntervention(
                cartId: 'cart-456',
                strategyId: 'push_notification',
                strategy: [],
                prediction: []
            );

            $tags = $job->tags();

            expect($tags)->toContain('cart-recovery');
            expect($tags)->toContain('cart:cart-456');
            expect($tags)->toContain('strategy:push_notification');
        });
    });

    describe('dispatch', function (): void {
        it('can be dispatched to queue', function (): void {
            ExecuteRecoveryIntervention::dispatch(
                'cart-789',
                'sms_reminder',
                ['type' => 'sms'],
                ['probability' => 0.7]
            );

            Queue::assertPushed(ExecuteRecoveryIntervention::class);
        });

        it('can be dispatched with delay', function (): void {
            ExecuteRecoveryIntervention::dispatch(
                'cart-delayed',
                'email_reminder',
                ['type' => 'email', 'delayMinutes' => 60],
                ['probability' => 0.6]
            )->delay(now()->addMinutes(60));

            Queue::assertPushed(ExecuteRecoveryIntervention::class);
        });
    });
});
