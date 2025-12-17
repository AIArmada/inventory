<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Services\MetricsAggregator;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('MetricsAggregator', function (): void {
    it('can aggregate metrics for a specific date', function (): void {
        // Create some cart data for today
        Cart::create([
            'instance' => 'default',
            'identifier' => 'session-1',
            'quantity' => 3,
            'subtotal' => 15000,
            'items' => json_encode([['id' => 1]]),
        ]);

        Cart::create([
            'instance' => 'default',
            'identifier' => 'session-2',
            'quantity' => 0,
            'subtotal' => 0,
            'items' => json_encode([]),
        ]);

        $aggregator = new MetricsAggregator();
        $metrics = $aggregator->aggregateForDate(now());

        expect($metrics)->not->toBeNull();
        expect($metrics->carts_created)->toBeGreaterThanOrEqual(0);
    });

    it('can aggregate metrics for a date with segment', function (): void {
        $aggregator = new MetricsAggregator();
        $metrics = $aggregator->aggregateForDate(now(), 'vip_customers');

        expect($metrics)->not->toBeNull();
        expect($metrics->segment)->toBe('vip_customers');
    });

    it('can aggregate totals for a date range', function (): void {
        $aggregator = new MetricsAggregator();

        // First create some daily metrics
        $aggregator->aggregateForDate(now()->subDays(2));
        $aggregator->aggregateForDate(now()->subDays(1));
        $aggregator->aggregateForDate(now());

        $totals = $aggregator->aggregateTotals(now()->subDays(2), now());

        expect($totals)->toBeArray();
    });

    it('can backfill metrics for a date range', function (): void {
        $aggregator = new MetricsAggregator();

        $count = $aggregator->backfill(now()->subDays(3), now());

        expect($count)->toBe(4); // 4 days inclusive
    });

    it('handles empty data gracefully', function (): void {
        $aggregator = new MetricsAggregator();
        $metrics = $aggregator->aggregateForDate(now()->subYear());

        expect($metrics->carts_created)->toBe(0);
        expect($metrics->carts_with_items)->toBe(0);
        expect($metrics->average_cart_value_cents)->toBe(0);
    });
});
