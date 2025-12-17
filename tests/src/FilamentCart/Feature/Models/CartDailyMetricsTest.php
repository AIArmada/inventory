<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\CartDailyMetrics;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('CartDailyMetrics', function (): void {
    it('can be created with required attributes', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'carts_created' => 100,
            'carts_active' => 50,
            'carts_with_items' => 40,
            'checkouts_started' => 30,
            'checkouts_completed' => 15,
            'checkouts_abandoned' => 10,
        ]);

        expect($metrics)->toBeInstanceOf(CartDailyMetrics::class);
        expect($metrics->id)->not->toBeNull();
        expect($metrics->carts_created)->toBe(100);
    });

    it('returns table name from config', function (): void {
        $metrics = new CartDailyMetrics();
        $tableName = $metrics->getTable();

        expect($tableName)->toContain('daily_metrics');
    });

    it('calculates conversion rate correctly', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'checkouts_started' => 100,
            'checkouts_completed' => 25,
        ]);

        expect($metrics->getConversionRate())->toBe(0.25);
    });

    it('returns zero conversion rate when no checkouts started', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'checkouts_started' => 0,
            'checkouts_completed' => 0,
        ]);

        expect($metrics->getConversionRate())->toBe(0.0);
    });

    it('calculates abandonment rate correctly', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'checkouts_started' => 100,
            'checkouts_abandoned' => 60,
        ]);

        expect($metrics->getAbandonmentRate())->toBe(0.6);
    });

    it('returns zero abandonment rate when no checkouts started', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'checkouts_started' => 0,
            'checkouts_abandoned' => 0,
        ]);

        expect($metrics->getAbandonmentRate())->toBe(0.0);
    });

    it('calculates recovery rate correctly', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'checkouts_abandoned' => 100,
            'carts_recovered' => 15,
        ]);

        expect($metrics->getRecoveryRate())->toBe(0.15);
    });

    it('returns zero recovery rate when no abandonments', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'checkouts_abandoned' => 0,
            'carts_recovered' => 0,
        ]);

        expect($metrics->getRecoveryRate())->toBe(0.0);
    });

    it('can be created with segment', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'segment' => 'vip_customers',
            'carts_created' => 50,
        ]);

        expect($metrics->segment)->toBe('vip_customers');
    });

    it('casts date correctly', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => '2025-01-15',
            'carts_created' => 10,
        ]);

        expect($metrics->date)->toBeInstanceOf(Carbon::class);
        expect($metrics->date->toDateString())->toBe('2025-01-15');
    });

    it('casts numeric fields as integers', function (): void {
        $metrics = CartDailyMetrics::create([
            'date' => now()->toDateString(),
            'carts_created' => '100',
            'total_cart_value_cents' => '50000',
        ]);

        expect($metrics->carts_created)->toBe(100);
        expect($metrics->total_cart_value_cents)->toBe(50000);
    });
});
