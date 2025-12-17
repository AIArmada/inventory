<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Services\RecoveryDispatcher;
use AIArmada\FilamentCart\Services\RecoveryScheduler;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('RecoveryScheduler', function (): void {
    beforeEach(function (): void {
        $this->scheduler = new RecoveryScheduler();
    });

    it('can be instantiated', function (): void {
        expect($this->scheduler)->toBeInstanceOf(RecoveryScheduler::class);
    });

    it('returns zero when campaign is not active', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Draft Campaign',
            'trigger_type' => 'abandoned',
            'status' => 'draft',
        ]);

        $scheduled = $this->scheduler->scheduleForCampaign($campaign);

        expect($scheduled)->toBe(0);
    });

    it('schedules attempts for eligible carts and updates campaign stats', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Active Campaign',
            'trigger_type' => 'abandoned',
            'status' => 'active',
            'trigger_delay_minutes' => 30,
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
            'offer_discount' => true,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'offer_free_shipping' => false,
            'ab_testing_enabled' => false,
            'total_targeted' => 0,
        ]);

        $cart1 = Cart::create([
            'identifier' => 'abandoned-1',
            'instance' => 'default',
            'items_count' => 2,
            'subtotal' => 20_00,
            'checkout_abandoned_at' => now()->subHours(2),
            'metadata' => ['email' => 'buyer@example.com'],
        ]);

        $cart2 = Cart::create([
            'identifier' => 'abandoned-2',
            'instance' => 'default',
            'items_count' => 1,
            'subtotal' => 15_00,
            'checkout_abandoned_at' => now()->subHours(3),
            'metadata' => ['phone' => '+10000000000'],
        ]);

        $scheduled = $this->scheduler->scheduleForCampaign($campaign->refresh());

        expect($scheduled)->toBe(2);
        expect(RecoveryAttempt::query()->where('campaign_id', $campaign->id)->count())->toBe(2);

        $attempt = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->where('cart_id', $cart1->id)
            ->firstOrFail();

        expect($attempt->status)->toBe('scheduled');
        expect($attempt->discount_code)->not->toBeNull();
        expect($attempt->discount_value_cents)->toBe(200);

        $campaign->refresh();
        expect($campaign->total_targeted)->toBe(2);
        expect($campaign->last_run_at)->not->toBeNull();

        $cart2Attempt = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->where('cart_id', $cart2->id)
            ->firstOrFail();

        expect($cart2Attempt->recipient_phone)->toBe('+10000000000');
    });

    it('processes scheduled attempts and returns stats', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Active Campaign',
            'trigger_type' => 'abandoned',
            'status' => 'active',
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        $cart = Cart::create([
            'identifier' => 'abandoned-1',
            'instance' => 'default',
            'items_count' => 1,
            'subtotal' => 20_00,
            'checkout_abandoned_at' => now()->subHours(2),
            'metadata' => ['email' => 'buyer@example.com'],
        ]);

        $attempt = RecoveryAttempt::create([
            'campaign_id' => $campaign->id,
            'cart_id' => $cart->id,
            'channel' => 'email',
            'status' => 'scheduled',
            'attempt_number' => 1,
            'scheduled_for' => now()->subMinute(),
        ]);

        $dispatcher = Mockery::mock(RecoveryDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->with(Mockery::on(fn ($arg) => $arg->id === $attempt->id));
        $this->app->instance(RecoveryDispatcher::class, $dispatcher);

        $result = $this->scheduler->processScheduledAttempts();

        expect($result)->toBeArray();
        expect($result)->toHaveKeys(['processed', 'failed']);
        expect($result['processed'])->toBe(1);
        expect($result['failed'])->toBe(0);

        $attempt->refresh();
        expect($attempt->status)->toBe('queued');
        expect($attempt->queued_at)->not->toBeNull();
    });

    it('marks attempts as failed if dispatch errors', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Active Campaign',
            'trigger_type' => 'abandoned',
            'status' => 'active',
            'max_attempts' => 3,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        $cart = Cart::create([
            'identifier' => 'abandoned-2',
            'instance' => 'default',
            'items_count' => 1,
            'subtotal' => 20_00,
            'checkout_abandoned_at' => now()->subHours(2),
            'metadata' => ['email' => 'buyer@example.com'],
        ]);

        $attempt = RecoveryAttempt::create([
            'campaign_id' => $campaign->id,
            'cart_id' => $cart->id,
            'channel' => 'email',
            'status' => 'scheduled',
            'attempt_number' => 1,
            'scheduled_for' => now()->subMinute(),
        ]);

        $dispatcher = Mockery::mock(RecoveryDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('dispatch failed'));
        $this->app->instance(RecoveryDispatcher::class, $dispatcher);

        $result = $this->scheduler->processScheduledAttempts();

        expect($result['processed'])->toBe(0);
        expect($result['failed'])->toBe(1);

        $attempt->refresh();
        expect($attempt->status)->toBe('failed');
        expect($attempt->failure_reason)->toBe('dispatch failed');
    });

    it('schedules the next attempt when cart is not recovered and max attempts not reached', function (): void {
        $campaign = RecoveryCampaign::create([
            'name' => 'Active Campaign',
            'trigger_type' => 'abandoned',
            'status' => 'active',
            'max_attempts' => 2,
            'attempt_interval_hours' => 24,
            'strategy' => 'email',
        ]);

        $cart = Cart::create([
            'identifier' => 'abandoned-3',
            'instance' => 'default',
            'items_count' => 1,
            'subtotal' => 20_00,
            'checkout_abandoned_at' => now()->subHours(2),
            'metadata' => ['email' => 'buyer@example.com'],
        ]);

        $previousAttempt = RecoveryAttempt::create([
            'campaign_id' => $campaign->id,
            'cart_id' => $cart->id,
            'channel' => 'email',
            'status' => 'failed',
            'attempt_number' => 1,
            'scheduled_for' => now()->subDay(),
        ]);

        $nextAttempt = $this->scheduler->scheduleNextAttempt($previousAttempt->refresh());

        expect($nextAttempt)->not->toBeNull();
        expect($nextAttempt?->attempt_number)->toBe(2);
        expect($nextAttempt?->scheduled_for)->not->toBeNull();
    });

    it('cancels attempts for cart and returns count', function (): void {
        $cancelled = $this->scheduler->cancelAttemptsForCart('non-existent-cart');

        expect($cancelled)->toBe(0);
    });
});
