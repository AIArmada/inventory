<?php

declare(strict_types=1);

use AIArmada\Chip\Data\DashboardMetrics;
use AIArmada\Chip\Data\RevenueMetrics;
use AIArmada\Chip\Data\TransactionMetrics;
use AIArmada\Chip\Services\LocalAnalyticsService;
use Illuminate\Support\Carbon;

describe('LocalAnalyticsService', function (): void {
    beforeEach(function (): void {
        $this->service = new LocalAnalyticsService();
        $this->startDate = Carbon::now()->subDays(30);
        $this->endDate = Carbon::now();
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(LocalAnalyticsService::class);
    });

    describe('method structure', function (): void {
        it('has getDashboardMetrics method', function (): void {
            expect(method_exists($this->service, 'getDashboardMetrics'))->toBeTrue();
        });

        it('getDashboardMetrics returns DashboardMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getDashboardMetrics');
            $returnType = $reflection->getReturnType();
            expect($returnType->getName())->toBe(DashboardMetrics::class);
        });

        it('has getRevenueMetrics method', function (): void {
            expect(method_exists($this->service, 'getRevenueMetrics'))->toBeTrue();
        });

        it('getRevenueMetrics returns RevenueMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getRevenueMetrics');
            $returnType = $reflection->getReturnType();
            expect($returnType->getName())->toBe(RevenueMetrics::class);
        });

        it('has getTransactionMetrics method', function (): void {
            expect(method_exists($this->service, 'getTransactionMetrics'))->toBeTrue();
        });

        it('getTransactionMetrics returns TransactionMetrics', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getTransactionMetrics');
            $returnType = $reflection->getReturnType();
            expect($returnType->getName())->toBe(TransactionMetrics::class);
        });

        it('has getPaymentMethodBreakdown method', function (): void {
            expect(method_exists($this->service, 'getPaymentMethodBreakdown'))->toBeTrue();
        });

        it('getPaymentMethodBreakdown returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getPaymentMethodBreakdown');
            $returnType = $reflection->getReturnType();
            expect($returnType->getName())->toBe('array');
        });

        it('has getFailureAnalysis method', function (): void {
            expect(method_exists($this->service, 'getFailureAnalysis'))->toBeTrue();
        });

        it('getFailureAnalysis returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getFailureAnalysis');
            $returnType = $reflection->getReturnType();
            expect($returnType->getName())->toBe('array');
        });

        it('has getRevenueTrend method', function (): void {
            expect(method_exists($this->service, 'getRevenueTrend'))->toBeTrue();
        });

        it('getRevenueTrend returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getRevenueTrend');
            $returnType = $reflection->getReturnType();
            expect($returnType->getName())->toBe('array');
        });

        it('has getAggregatedMetrics method', function (): void {
            expect(method_exists($this->service, 'getAggregatedMetrics'))->toBeTrue();
        });

        it('getAggregatedMetrics returns array', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getAggregatedMetrics');
            $returnType = $reflection->getReturnType();
            expect($returnType->getName())->toBe('array');
        });
    });

    describe('method parameters', function (): void {
        it('getDashboardMetrics accepts startDate and endDate', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getDashboardMetrics');
            $params = $reflection->getParameters();
            expect($params)->toHaveCount(2);
            expect($params[0]->getName())->toBe('startDate');
            expect($params[1]->getName())->toBe('endDate');
        });

        it('getRevenueMetrics accepts startDate and endDate', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getRevenueMetrics');
            $params = $reflection->getParameters();
            expect($params)->toHaveCount(2);
        });

        it('getRevenueTrend accepts startDate, endDate and groupBy', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getRevenueTrend');
            $params = $reflection->getParameters();
            expect($params)->toHaveCount(3);
            expect($params[2]->getName())->toBe('groupBy');
            expect($params[2]->isOptional())->toBeTrue();
        });

        it('getAggregatedMetrics accepts paymentMethod filter', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getAggregatedMetrics');
            $params = $reflection->getParameters();
            expect($params)->toHaveCount(3);
            expect($params[2]->getName())->toBe('paymentMethod');
            expect($params[2]->allowsNull())->toBeTrue();
        });
    });
});
