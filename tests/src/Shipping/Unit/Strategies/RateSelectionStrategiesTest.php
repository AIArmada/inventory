<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Strategies\BalancedRateStrategy;
use AIArmada\Shipping\Strategies\CheapestRateStrategy;
use AIArmada\Shipping\Strategies\FastestRateStrategy;
use AIArmada\Shipping\Strategies\PreferredCarrierStrategy;

// ============================================
// CheapestRateStrategy Tests
// ============================================

describe('CheapestRateStrategy', function (): void {
    it('selects the cheapest rate from collection', function (): void {
        $strategy = new CheapestRateStrategy();

        $rates = collect([
            new RateQuoteData(carrier: 'carrier_a', service: 'express', rate: 1500, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'carrier_b', service: 'standard', rate: 800, currency: 'MYR', estimatedDays: 3),
            new RateQuoteData(carrier: 'carrier_c', service: 'economy', rate: 1200, currency: 'MYR', estimatedDays: 5),
        ]);

        $selected = $strategy->select($rates);

        expect($selected->carrier)->toBe('carrier_b');
        expect($selected->rate)->toBe(800);
    });

    it('returns null for empty collection', function (): void {
        $strategy = new CheapestRateStrategy();

        $selected = $strategy->select(collect());

        expect($selected)->toBeNull();
    });

    it('returns correct strategy name', function (): void {
        $strategy = new CheapestRateStrategy();

        expect($strategy->getStrategyName())->toBe('cheapest');
    });
});

// ============================================
// FastestRateStrategy Tests
// ============================================

describe('FastestRateStrategy', function (): void {
    it('selects the fastest rate from collection', function (): void {
        $strategy = new FastestRateStrategy();

        $rates = collect([
            new RateQuoteData(carrier: 'carrier_a', service: 'express', rate: 1500, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'carrier_b', service: 'standard', rate: 800, currency: 'MYR', estimatedDays: 3),
            new RateQuoteData(carrier: 'carrier_c', service: 'economy', rate: 600, currency: 'MYR', estimatedDays: 5),
        ]);

        $selected = $strategy->select($rates);

        expect($selected->carrier)->toBe('carrier_a');
        expect($selected->estimatedDays)->toBe(1);
    });

    it('returns null for empty collection', function (): void {
        $strategy = new FastestRateStrategy();

        $selected = $strategy->select(collect());

        expect($selected)->toBeNull();
    });

    it('returns correct strategy name', function (): void {
        $strategy = new FastestRateStrategy();

        expect($strategy->getStrategyName())->toBe('fastest');
    });
});

// ============================================
// PreferredCarrierStrategy Tests
// ============================================

describe('PreferredCarrierStrategy', function (): void {
    it('selects preferred carrier when available', function (): void {
        $strategy = new PreferredCarrierStrategy([
            'carrier_b' => 1, // Highest priority
            'carrier_a' => 2,
            'carrier_c' => 3,
        ]);

        $rates = collect([
            new RateQuoteData(carrier: 'carrier_a', service: 'express', rate: 1500, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'carrier_b', service: 'standard', rate: 1200, currency: 'MYR', estimatedDays: 3),
            new RateQuoteData(carrier: 'carrier_c', service: 'economy', rate: 600, currency: 'MYR', estimatedDays: 5),
        ]);

        $selected = $strategy->select($rates);

        expect($selected->carrier)->toBe('carrier_b');
    });

    it('falls back to second priority if first unavailable', function (): void {
        $strategy = new PreferredCarrierStrategy([
            'carrier_x' => 1, // Not available
            'carrier_a' => 2,
        ]);

        $rates = collect([
            new RateQuoteData(carrier: 'carrier_a', service: 'express', rate: 1500, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'carrier_b', service: 'standard', rate: 800, currency: 'MYR', estimatedDays: 3),
        ]);

        $selected = $strategy->select($rates);

        expect($selected->carrier)->toBe('carrier_a');
    });

    it('falls back to cheapest when no preferred carriers available', function (): void {
        $strategy = new PreferredCarrierStrategy([
            'carrier_x' => 1,
            'carrier_y' => 2,
        ]);

        $rates = collect([
            new RateQuoteData(carrier: 'carrier_a', service: 'express', rate: 1500, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'carrier_b', service: 'standard', rate: 800, currency: 'MYR', estimatedDays: 3),
        ]);

        $selected = $strategy->select($rates);

        expect($selected->carrier)->toBe('carrier_b'); // Cheapest fallback
    });

    it('falls back to cheapest when no priorities configured', function (): void {
        $strategy = new PreferredCarrierStrategy([]);

        $rates = collect([
            new RateQuoteData(carrier: 'carrier_a', service: 'express', rate: 1500, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'carrier_b', service: 'standard', rate: 800, currency: 'MYR', estimatedDays: 3),
        ]);

        $selected = $strategy->select($rates);

        expect($selected->carrier)->toBe('carrier_b');
    });

    it('returns correct strategy name', function (): void {
        $strategy = new PreferredCarrierStrategy([]);

        expect($strategy->getStrategyName())->toBe('preferred');
    });
});

// ============================================
// BalancedRateStrategy Tests
// ============================================

describe('BalancedRateStrategy', function (): void {
    it('balances between speed and cost', function (): void {
        $strategy = new BalancedRateStrategy(speedWeight: 0.5, costWeight: 0.5);

        $rates = collect([
            new RateQuoteData(carrier: 'fast_expensive', service: 'express', rate: 2000, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'balanced', service: 'standard', rate: 1000, currency: 'MYR', estimatedDays: 2),
            new RateQuoteData(carrier: 'slow_cheap', service: 'economy', rate: 500, currency: 'MYR', estimatedDays: 7),
        ]);

        $selected = $strategy->select($rates);

        // With 50/50 weight, the balanced option should win
        expect($selected->carrier)->toBe('balanced');
    });

    it('prefers speed when speed weight is higher', function (): void {
        $strategy = new BalancedRateStrategy(speedWeight: 0.8, costWeight: 0.2);

        $rates = collect([
            new RateQuoteData(carrier: 'fast_expensive', service: 'express', rate: 2000, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'balanced', service: 'standard', rate: 1000, currency: 'MYR', estimatedDays: 3),
            new RateQuoteData(carrier: 'slow_cheap', service: 'economy', rate: 500, currency: 'MYR', estimatedDays: 7),
        ]);

        $selected = $strategy->select($rates);

        expect($selected->carrier)->toBe('fast_expensive');
    });

    it('prefers cost when cost weight is higher', function (): void {
        $strategy = new BalancedRateStrategy(speedWeight: 0.2, costWeight: 0.8);

        $rates = collect([
            new RateQuoteData(carrier: 'fast_expensive', service: 'express', rate: 2000, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'balanced', service: 'standard', rate: 1000, currency: 'MYR', estimatedDays: 3),
            new RateQuoteData(carrier: 'slow_cheap', service: 'economy', rate: 500, currency: 'MYR', estimatedDays: 7),
        ]);

        $selected = $strategy->select($rates);

        expect($selected->carrier)->toBe('slow_cheap');
    });

    it('returns null for empty collection', function (): void {
        $strategy = new BalancedRateStrategy();

        $selected = $strategy->select(collect());

        expect($selected)->toBeNull();
    });

    it('returns correct strategy name', function (): void {
        $strategy = new BalancedRateStrategy();

        expect($strategy->getStrategyName())->toBe('balanced');
    });

    it('can override weights via options', function (): void {
        $strategy = new BalancedRateStrategy(speedWeight: 0.5, costWeight: 0.5);

        $rates = collect([
            new RateQuoteData(carrier: 'fast', service: 'express', rate: 2000, currency: 'MYR', estimatedDays: 1),
            new RateQuoteData(carrier: 'slow', service: 'economy', rate: 500, currency: 'MYR', estimatedDays: 7),
        ]);

        // Override via options to prefer speed
        $selected = $strategy->select($rates, ['speed_weight' => 0.9, 'cost_weight' => 0.1]);

        expect($selected->carrier)->toBe('fast');
    });
});
