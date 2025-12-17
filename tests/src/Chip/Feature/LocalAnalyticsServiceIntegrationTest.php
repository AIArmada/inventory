<?php

declare(strict_types=1);

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use AIArmada\Chip\Models\DailyMetric;
use AIArmada\Chip\Services\LocalAnalyticsService;
use Illuminate\Support\Carbon;

describe('LocalAnalyticsService', function (): void {
    beforeEach(function (): void {
        $this->service = new LocalAnalyticsService;
        $this->startDate = Carbon::now()->subDays(30);
        $this->endDate = Carbon::now();
    });

    describe('instantiation', function (): void {
        it('can be instantiated', function (): void {
            expect($this->service)->toBeInstanceOf(LocalAnalyticsService::class);
        });
    });

    describe('method signatures', function (): void {
        it('getDashboardMetrics returns DashboardMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getDashboardMetrics');
            expect($reflection->getReturnType()->getName())->toBe(DashboardMetrics::class);
        });

        it('getRevenueMetrics returns RevenueMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getRevenueMetrics');
            expect($reflection->getReturnType()->getName())->toBe(RevenueMetrics::class);
        });

        it('getTransactionMetrics returns TransactionMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getTransactionMetrics');
            expect($reflection->getReturnType()->getName())->toBe(TransactionMetrics::class);
        });

        it('getPaymentMethodBreakdown returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getPaymentMethodBreakdown');
            expect($reflection->getReturnType()->getName())->toBe('array');
        });

        it('getFailureAnalysis returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getFailureAnalysis');
            expect($reflection->getReturnType()->getName())->toBe('array');
        });

        it('getRevenueTrend returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getRevenueTrend');
            expect($reflection->getReturnType()->getName())->toBe('array');
        });

        it('getAggregatedMetrics returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getAggregatedMetrics');
            expect($reflection->getReturnType()->getName())->toBe('array');
        });
    });

    describe('getAggregatedMetrics with DailyMetric data', function (): void {
        it('returns aggregated daily metrics', function (): void {
            DailyMetric::create([
                'date' => now()->subDays(5)->toDateString(),
                'payment_method' => null,
                'total_attempts' => 100,
                'successful_count' => 95,
                'failed_count' => 5,
                'revenue_minor' => 500000,
                'success_rate' => 95.0,
            ]);

            $result = $this->service->getAggregatedMetrics($this->startDate, $this->endDate);

            expect($result)->toBeArray();
            expect(count($result))->toBeGreaterThanOrEqual(1);
        });

        it('filters by payment method', function (): void {
            DailyMetric::create([
                'date' => now()->subDays(3)->toDateString(),
                'payment_method' => 'fpx',
                'total_attempts' => 50,
                'successful_count' => 48,
                'failed_count' => 2,
                'revenue_minor' => 250000,
                'success_rate' => 96.0,
            ]);

            DailyMetric::create([
                'date' => now()->subDays(3)->toDateString(),
                'payment_method' => 'card',
                'total_attempts' => 30,
                'successful_count' => 28,
                'failed_count' => 2,
                'revenue_minor' => 150000,
                'success_rate' => 93.33,
            ]);

            $result = $this->service->getAggregatedMetrics($this->startDate, $this->endDate, 'fpx');

            expect($result)->toBeArray();
            expect(count($result))->toBe(1);
            // Check for either camelCase or snake_case property
            $firstResult = $result[0];
            if (is_object($firstResult)) {
                $paymentMethod = $firstResult->paymentMethod ?? $firstResult->payment_method ?? null;
                expect($paymentMethod)->toBe('fpx');
            } elseif (is_array($firstResult)) {
                $paymentMethod = $firstResult['paymentMethod'] ?? $firstResult['payment_method'] ?? null;
                expect($paymentMethod)->toBe('fpx');
            }
        });

        it('returns empty array when no metrics', function (): void {
            $result = $this->service->getAggregatedMetrics(
                Carbon::now()->subDays(100),
                Carbon::now()->subDays(90)
            );

            expect($result)->toBeArray();
            expect($result)->toBeEmpty();
        });
    });
});
