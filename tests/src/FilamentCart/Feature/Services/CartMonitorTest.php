<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\AlertLog;
use AIArmada\FilamentCart\Models\AlertRule;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\CartMonitor;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('CartMonitor', function (): void {
    it('can be instantiated', function (): void {
        $monitor = new CartMonitor();
        expect($monitor)->toBeInstanceOf(CartMonitor::class);
    });

    it('returns live stats based on snapshots and alerts', function (): void {
        config([
            'filament-cart.monitoring.abandonment_detection_minutes' => 30,
            'filament-cart.ai.high_value_threshold_cents' => 10000,
        ]);

        $activeCart = Cart::create([
            'identifier' => 'cart-active',
            'instance' => 'default',
            'items_count' => 2,
            'total' => 5_000,
            'last_activity_at' => now(),
        ]);

        Cart::create([
            'identifier' => 'cart-checkout',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 3_000,
            'checkout_started_at' => now()->subMinutes(5),
            'last_activity_at' => now()->subMinutes(5),
        ]);

        Cart::create([
            'identifier' => 'cart-stale',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 2_500,
            'last_activity_at' => now()->subMinutes(60),
        ]);

        Cart::create([
            'identifier' => 'cart-high-value',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 15_000,
            'last_activity_at' => now(),
        ]);

        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'abandonment',
            'conditions' => [],
            'notify_email' => false,
            'notify_slack' => false,
            'notify_webhook' => false,
            'notify_database' => true,
            'cooldown_minutes' => 60,
            'severity' => 'warning',
            'priority' => 1,
            'is_active' => true,
        ]);

        AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'abandonment',
            'severity' => 'warning',
            'title' => 'Abandonment detected',
            'message' => 'Cart looks abandoned.',
            'event_data' => [],
            'channels_notified' => ['database'],
            'cart_id' => $activeCart->id,
            'session_id' => $activeCart->identifier,
            'is_read' => false,
        ]);

        AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'fraud',
            'severity' => 'critical',
            'title' => 'Fraud detected',
            'message' => 'Suspicious activity.',
            'event_data' => [],
            'channels_notified' => ['database'],
            'cart_id' => $activeCart->id,
            'session_id' => $activeCart->identifier,
            'is_read' => false,
        ]);

        $monitor = new CartMonitor();
        $stats = $monitor->getLiveStats();

        expect($stats->active_carts)->toBe(4);
        expect($stats->carts_with_items)->toBe(4);
        expect($stats->checkouts_in_progress)->toBe(1);
        expect($stats->recent_abandonments)->toBe(1);
        expect($stats->pending_alerts)->toBeGreaterThanOrEqual(2);
        expect($stats->high_value_carts)->toBe(1);
        expect($stats->fraud_signals)->toBe(1);
    });

    it('detects abandonments without prior alerts', function (): void {
        config(['filament-cart.monitoring.abandonment_detection_minutes' => 30]);

        $staleCartWithAlert = Cart::create([
            'identifier' => 'cart-stale-alerted',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 2_500,
            'last_activity_at' => now()->subMinutes(60),
        ]);

        $staleCartWithoutAlert = Cart::create([
            'identifier' => 'cart-stale-unalerted',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 3_500,
            'last_activity_at' => now()->subMinutes(60),
        ]);

        $rule = AlertRule::create([
            'name' => 'Test Rule',
            'event_type' => 'abandonment',
            'conditions' => [],
            'notify_email' => false,
            'notify_slack' => false,
            'notify_webhook' => false,
            'notify_database' => true,
            'cooldown_minutes' => 60,
            'severity' => 'warning',
            'priority' => 1,
            'is_active' => true,
        ]);

        AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'abandonment',
            'severity' => 'warning',
            'title' => 'Already alerted',
            'message' => 'Cart looks abandoned.',
            'event_data' => [],
            'channels_notified' => ['database'],
            'cart_id' => $staleCartWithAlert->id,
            'session_id' => $staleCartWithAlert->identifier,
            'is_read' => false,
        ]);

        $monitor = new CartMonitor();
        $detected = $monitor->detectAbandonments();

        expect($detected)->toHaveCount(1);
        expect($detected->first()->id)->toBe($staleCartWithoutAlert->id);
    });

    it('returns recent activity with derived status and value aliases', function (): void {
        $active = Cart::create([
            'identifier' => 'activity-active',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 10_00,
            'last_activity_at' => now(),
        ]);

        $checkout = Cart::create([
            'identifier' => 'activity-checkout',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 20_00,
            'checkout_started_at' => now()->subMinutes(5),
            'last_activity_at' => now()->subMinutes(5),
        ]);

        $abandoned = Cart::create([
            'identifier' => 'activity-abandoned',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 30_00,
            'checkout_abandoned_at' => now()->subMinutes(45),
            'last_activity_at' => now()->subMinutes(45),
        ]);

        $monitor = new CartMonitor();
        $activity = $monitor->getRecentActivity(limit: 10);

        $bySessionId = $activity->keyBy('session_id');

        expect($bySessionId[$active->identifier]->status)->toBe('active');
        expect($bySessionId[$checkout->identifier]->status)->toBe('checkout');
        expect($bySessionId[$abandoned->identifier]->status)->toBe('abandoned');
        expect((int) $bySessionId[$abandoned->identifier]->total_cents)->toBe(30_00);
    });

    it('detects fraud signals and excludes already-alerted carts', function (): void {
        $rule = AlertRule::create([
            'name' => 'Fraud Rule',
            'event_type' => 'fraud',
            'conditions' => [],
            'notify_email' => false,
            'notify_slack' => false,
            'notify_webhook' => false,
            'notify_database' => true,
            'cooldown_minutes' => 60,
            'severity' => 'critical',
            'priority' => 1,
            'is_active' => true,
        ]);

        $highValueNew = Cart::create([
            'identifier' => 'fraud-highvalue-new',
            'instance' => 'default',
            'items_count' => 1,
            'total' => 60_000,
            'last_activity_at' => now(),
        ]);
        $highValueNew->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();

        $bulkHighQty = Cart::create([
            'identifier' => 'fraud-bulk',
            'instance' => 'default',
            'items_count' => 12,
            'total' => 150_000,
            'last_activity_at' => now(),
        ]);

        AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'fraud',
            'severity' => 'critical',
            'title' => 'Already alerted',
            'message' => 'Excluded by whereNotExists.',
            'event_data' => [],
            'channels_notified' => ['database'],
            'cart_id' => $highValueNew->id,
            'session_id' => $highValueNew->identifier,
            'is_read' => false,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $monitor = new CartMonitor();
        $signals = $monitor->detectFraudSignals();

        expect($signals->pluck('id')->all())->toContain($bulkHighQty->id);
        expect($signals->pluck('id')->all())->not->toContain($highValueNew->id);
    });

    it('detects recovery opportunities based on value and inactivity window', function (): void {
        $eligible = Cart::create([
            'identifier' => 'recovery-eligible',
            'instance' => 'default',
            'items_count' => 2,
            'total' => 25_00,
            'last_activity_at' => now()->subHour(),
        ]);

        Cart::create([
            'identifier' => 'recovery-too-recent',
            'instance' => 'default',
            'items_count' => 2,
            'total' => 25_00,
            'last_activity_at' => now()->subMinutes(10),
        ]);

        Cart::create([
            'identifier' => 'recovery-too-old',
            'instance' => 'default',
            'items_count' => 2,
            'total' => 25_00,
            'last_activity_at' => now()->subHours(3),
        ]);

        $monitor = new CartMonitor();
        $opportunities = $monitor->detectRecoveryOpportunities();

        expect($opportunities->pluck('id')->all())->toContain($eligible->id);
    });
});
