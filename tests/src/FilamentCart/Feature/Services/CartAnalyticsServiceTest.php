<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\AbandonmentAnalysis;
use AIArmada\FilamentCart\Data\ConversionFunnel;
use AIArmada\FilamentCart\Data\DashboardMetrics;
use AIArmada\FilamentCart\Data\RecoveryMetrics;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Models\CartDailyMetrics;
use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('CartAnalyticsService', function (): void {
    beforeEach(function (): void {
        $this->service = new CartAnalyticsService();
    });

    describe('getDashboardMetrics', function (): void {
        it('returns DashboardMetrics DTO', function (): void {
            $from = now()->subDays(7);
            $to = now();

            $metrics = $this->service->getDashboardMetrics($from, $to);

            expect($metrics)->toBeInstanceOf(DashboardMetrics::class);
        });

        it('handles empty data gracefully', function (): void {
            $from = now()->subYears(10);
            $to = now()->subYears(9);

            $metrics = $this->service->getDashboardMetrics($from, $to);

            expect($metrics->total_carts)->toBe(0);
            expect($metrics->conversion_rate)->toBe(0.0);
        });

        it('calculates metrics with daily data', function (): void {
            // Seed some daily metrics
            CartDailyMetrics::create([
                'date' => now()->subDays(1)->toDateString(),
                'carts_created' => 100,
                'carts_active' => 50,
                'checkouts_started' => 40,
                'checkouts_completed' => 20,
                'checkouts_abandoned' => 15,
                'carts_recovered' => 5,
                'total_cart_value_cents' => 100000,
                'average_cart_value_cents' => 1000,
            ]);

            $from = now()->subDays(7);
            $to = now();

            $metrics = $this->service->getDashboardMetrics($from, $to);

            expect($metrics->total_carts)->toBeGreaterThanOrEqual(100);
        });
    });

    describe('getConversionFunnel', function (): void {
        it('returns ConversionFunnel DTO', function (): void {
            $from = now()->subDays(7);
            $to = now();

            $funnel = $this->service->getConversionFunnel($from, $to);

            expect($funnel)->toBeInstanceOf(ConversionFunnel::class);
        });

        it('handles empty data gracefully', function (): void {
            $from = now()->subYears(10);
            $to = now()->subYears(9);

            $funnel = $this->service->getConversionFunnel($from, $to);

            expect($funnel->carts_created)->toBe(0);
            expect($funnel->checkout_completed)->toBe(0);
        });

        it('calculates funnel with daily data', function (): void {
            CartDailyMetrics::create([
                'date' => now()->subDays(1)->toDateString(),
                'carts_created' => 100,
                'carts_with_items' => 80,
                'checkouts_started' => 40,
                'checkouts_completed' => 20,
            ]);

            $from = now()->subDays(7);
            $to = now();

            $funnel = $this->service->getConversionFunnel($from, $to);

            expect($funnel->carts_created)->toBeGreaterThanOrEqual(100);
        });
    });

    describe('getRecoveryMetrics', function (): void {
        it('returns RecoveryMetrics DTO', function (): void {
            $from = now()->subDays(7);
            $to = now();
            $metrics = $this->service->getRecoveryMetrics($from, $to);
            expect($metrics)->toBeInstanceOf(RecoveryMetrics::class);
        });

        it('calculates recovery metrics correctly', function (): void {
            CartDailyMetrics::create([
                'date' => now()->subDays(1)->toDateString(),
                'checkouts_abandoned' => 10,
                'recovery_emails_sent' => 20,
                'carts_recovered' => 5,
                'recovered_revenue_cents' => 50000,
            ]);

            // Create recovered carts with strategy metadata
            Cart::create([
                'identifier' => 'c1',
                'instance' => 'default',
                'subtotal' => 10000,
                'recovered_at' => now()->subDays(1),
                'metadata' => ['last_recovery_strategy' => 'discount_10'],
            ]);
            Cart::create([
                'identifier' => 'c2',
                'instance' => 'default',
                'subtotal' => 20000,
                'recovered_at' => now()->subDays(1),
                'metadata' => ['last_recovery_strategy' => 'reminder'],
            ]);

            $metrics = $this->service->getRecoveryMetrics(now()->subDays(7), now());

            expect($metrics->total_abandoned)->toBe(10);
            expect($metrics->successful_recoveries)->toBe(5);
            expect($metrics->recovered_revenue_cents)->toBe(50000);

            // Check strategy breakdown
            expect($metrics->by_strategy)->toHaveKey('discount_10');
            expect($metrics->by_strategy['discount_10']['conversions'])->toBe(1);
            expect($metrics->by_strategy['discount_10']['revenue'])->toBe(10000);

            expect($metrics->by_strategy)->toHaveKey('reminder');
            expect($metrics->by_strategy['reminder']['conversions'])->toBe(1);
        });
    });

    describe('getValueTrends', function (): void {
        it('returns array of trend data', function (): void {
            $from = now()->subDays(7);
            $to = now();
            $trends = $this->service->getValueTrends($from, $to);
            expect($trends)->toBeArray();
        });

        it('groups by day', function (): void {
            CartDailyMetrics::create([
                'date' => now()->subDays(2)->toDateString(),
                'total_cart_value_cents' => 1000,
                'carts_with_items' => 1,
            ]);
            CartDailyMetrics::create([
                'date' => now()->subDays(1)->toDateString(),
                'total_cart_value_cents' => 2000,
                'carts_with_items' => 2,
            ]);

            $trends = $this->service->getValueTrends(now()->subDays(7), now(), 'day');

            expect($trends)->toHaveCount(2);
            expect($trends[0]['value'])->toBe(1000);
            expect($trends[1]['value'])->toBe(2000);
        });
    });

    describe('getAbandonmentAnalysis', function (): void {
        it('returns AbandonmentAnalysis DTO', function (): void {
            $analysis = $this->service->getAbandonmentAnalysis(now()->subDays(7), now());
            expect($analysis)->toBeInstanceOf(AbandonmentAnalysis::class);
        });

        it('analyzes abandonment data correctly', function (): void {
            // Create abandoned carts
            // 1. Abandoned yesterday at 10:00 AM, 1 item, $10.00, Exit: Shipping
            Cart::create([
                'identifier' => 'a1',
                'instance' => 'default',
                'checkout_abandoned_at' => now()->subDays(1)->setTime(10, 0),
                'items_count' => 1,
                'subtotal' => 1000, // $10
                'metadata' => ['last_step' => 'shipping'],
            ]);

            // 2. Abandoned yesterday at 14:00 PM, 5 items, $100.00, Exit: Payment
            Cart::create([
                'identifier' => 'a2',
                'instance' => 'default',
                'checkout_abandoned_at' => now()->subDays(1)->setTime(14, 0),
                'items_count' => 5,
                'subtotal' => 10000, // $100
                'metadata' => ['last_step' => 'payment'],
            ]);

            $analysis = $this->service->getAbandonmentAnalysis(now()->subDays(7), now());

            // By Hour
            // Note: SQLite strftime '%H' returns string "10".
            // Implementation casts to int if SQLite? Or array key index?
            // The service code CASTs to INTEGER for SQLite specifically.
            // But checking array keys.
            // Let's inspect partial results if needed.

            // By Items Count
            expect($analysis->by_items_count)->toHaveKey('1 item');
            expect($analysis->by_items_count['1 item'])->toBe(1);
            expect($analysis->by_items_count)->toHaveKey('4-5 items');
            expect($analysis->by_items_count['4-5 items'])->toBe(1);

            // By Value Range
            // 1000 = $10 -> 'Under $25'
            // 10000 = $100 -> '$100-$250' ?? No.
            // Code: WHEN subtotal < 10000 THEN '$50-$100'. 10000 is NOT < 10000.
            // WHEN subtotal < 25000 THEN '$100-$250'.
            // So 10000 falls into $100-$250 range? 
            // Wait: 
            // < 2500
            // < 5000
            // < 10000
            // < 25000
            // If subtotal is exactly 10000, it fails < 10000 check, goes to < 25000 check.
            // So it is '$100-$250'.

            expect($analysis->by_cart_value_range)->toHaveKey('Under $25');
            expect($analysis->by_cart_value_range['Under $25'])->toBe(1);
            expect($analysis->by_cart_value_range)->toHaveKey('$100-$250');
            expect($analysis->by_cart_value_range['$100-$250'])->toBe(1);

            // Exit Points
            expect($analysis->common_exit_points)->toHaveKey('shipping');
            expect($analysis->common_exit_points['shipping'])->toBe(1);
            expect($analysis->common_exit_points)->toHaveKey('payment');
            expect($analysis->common_exit_points['payment'])->toBe(1);
        });
    });

    describe('getSegmentComparison', function (): void {
        it('returns Collection of segment data', function (): void {
            $from = now()->subDays(7);
            $to = now();

            $comparison = $this->service->getSegmentComparison($from, $to);

            expect($comparison)->toBeInstanceOf(Collection::class);
        });

        it('returns segment data when available', function (): void {
            CartDailyMetrics::create([
                'date' => now()->subDays(1)->toDateString(),
                'segment' => 'vip',
                'carts_created' => 50,
                'checkouts_started' => 20,
                'checkouts_completed' => 15,
                'average_cart_value_cents' => 5000,
            ]);

            $from = now()->subDays(7);
            $to = now();

            $comparison = $this->service->getSegmentComparison($from, $to);

            expect($comparison->count())->toBeGreaterThanOrEqual(1);
        });
    });
});
