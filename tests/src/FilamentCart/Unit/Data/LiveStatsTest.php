<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\LiveStats;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('LiveStats', function (): void {
    it('can be created with constructor', function (): void {
        $stats = new LiveStats(
            active_carts: 50,
            carts_with_items: 40,
            checkouts_in_progress: 10,
            recent_abandonments: 5,
            pending_alerts: 2,
            total_value_cents: 150000,
            high_value_carts: 3,
            fraud_signals: 1,
            updated_at: now(),
        );

        expect($stats->active_carts)->toBe(50);
        expect($stats->carts_with_items)->toBe(40);
        expect($stats->checkouts_in_progress)->toBe(10);
        expect($stats->recent_abandonments)->toBe(5);
        expect($stats->pending_alerts)->toBe(2);
        expect($stats->total_value_cents)->toBe(150000);
        expect($stats->high_value_carts)->toBe(3);
        expect($stats->fraud_signals)->toBe(1);
    });

    it('can be created with defaults factory', function (): void {
        $stats = LiveStats::defaults();

        expect($stats->active_carts)->toBe(0);
        expect($stats->carts_with_items)->toBe(0);
        expect($stats->checkouts_in_progress)->toBe(0);
        expect($stats->recent_abandonments)->toBe(0);
        expect($stats->pending_alerts)->toBe(0);
        expect($stats->total_value_cents)->toBe(0);
        expect($stats->high_value_carts)->toBe(0);
        expect($stats->fraud_signals)->toBe(0);
        expect($stats->updated_at)->toBeInstanceOf(Carbon::class);
    });

    it('calculates total value in dollars', function (): void {
        $stats = new LiveStats(
            active_carts: 10,
            carts_with_items: 5,
            checkouts_in_progress: 2,
            recent_abandonments: 1,
            pending_alerts: 0,
            total_value_cents: 150050, // $1500.50
            high_value_carts: 1,
            fraud_signals: 0,
            updated_at: now(),
        );

        expect($stats->getTotalValueDollars())->toBe(1500.50);
    });

    it('formats total value correctly', function (): void {
        $stats = new LiveStats(
            active_carts: 10,
            carts_with_items: 5,
            checkouts_in_progress: 2,
            recent_abandonments: 1,
            pending_alerts: 0,
            total_value_cents: 150050,
            high_value_carts: 1,
            fraud_signals: 0,
            updated_at: now(),
        );

        expect($stats->getFormattedTotalValue())->toBe('$1,500.50');
    });

    it('detects urgent alerts from pending alerts', function (): void {
        $stats = new LiveStats(
            active_carts: 10,
            carts_with_items: 5,
            checkouts_in_progress: 2,
            recent_abandonments: 1,
            pending_alerts: 3, // Has pending alerts
            total_value_cents: 100000,
            high_value_carts: 1,
            fraud_signals: 0,
            updated_at: now(),
        );

        expect($stats->hasUrgentAlerts())->toBeTrue();
    });

    it('detects urgent alerts from fraud signals', function (): void {
        $stats = new LiveStats(
            active_carts: 10,
            carts_with_items: 5,
            checkouts_in_progress: 2,
            recent_abandonments: 1,
            pending_alerts: 0,
            total_value_cents: 100000,
            high_value_carts: 1,
            fraud_signals: 2, // Has fraud signals
            updated_at: now(),
        );

        expect($stats->hasUrgentAlerts())->toBeTrue();
    });

    it('returns false for urgent alerts when none', function (): void {
        $stats = new LiveStats(
            active_carts: 10,
            carts_with_items: 5,
            checkouts_in_progress: 2,
            recent_abandonments: 1,
            pending_alerts: 0,
            total_value_cents: 100000,
            high_value_carts: 1,
            fraud_signals: 0,
            updated_at: now(),
        );

        expect($stats->hasUrgentAlerts())->toBeFalse();
    });
});
