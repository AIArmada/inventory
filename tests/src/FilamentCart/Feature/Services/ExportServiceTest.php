<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\ConversionFunnel;
use AIArmada\FilamentCart\Data\DashboardMetrics;
use AIArmada\FilamentCart\Data\RecoveryMetrics;
use AIArmada\FilamentCart\Data\AbandonmentAnalysis;
use AIArmada\FilamentCart\Models\CartDailyMetrics;
use AIArmada\FilamentCart\Services\CartAnalyticsService;
use AIArmada\FilamentCart\Services\ExportService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('ExportService', function (): void {
    beforeEach(function (): void {
        $this->analyticsService = Mockery::mock(CartAnalyticsService::class);
        $this->exportService = new ExportService($this->analyticsService);
    });

    it('exports metrics to CSV', function (): void {
        CartDailyMetrics::create([
            'date' => '2025-01-14',
            'carts_created' => 10,
            'checkouts_started' => 5,
            'checkouts_completed' => 2,
            'total_cart_value_cents' => 1000,
        ]);

        CartDailyMetrics::create([
            'date' => '2025-01-15',
            'carts_created' => 20,
            'checkouts_started' => 10,
            'checkouts_completed' => 5,
            'total_cart_value_cents' => 2000,
        ]);

        $csv = $this->exportService->exportMetricsToCsv(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-01-31')
        );

        $lines = explode("\n", trim($csv));

        expect($lines)->toHaveCount(3); // Header + 2 rows
        expect($lines[0])->toContain('Date,Carts Created');
        expect($lines[1])->toContain('2025-01-14,10');
        expect($lines[2])->toContain('2025-01-15,20');
    });

    it('exports to JSON', function (): void {
        $this->analyticsService->shouldReceive('getDashboardMetrics')->andReturn(new DashboardMetrics(
            total_carts: 100,
            active_carts: 50,
            abandoned_carts: 30,
            recovered_carts: 10,
            total_value_cents: 10000,
            average_cart_value_cents: 500,
            conversion_rate: 0.1,
            abandonment_rate: 0.3,
            recovery_rate: 0.2
        ));

        $this->analyticsService->shouldReceive('getConversionFunnel')->andReturn(ConversionFunnel::calculate(
            cartsCreated: 100,
            itemsAdded: 80,
            checkoutStarted: 40,
            checkoutCompleted: 10
        ));

        $this->analyticsService->shouldReceive('getRecoveryMetrics')->andReturn(new RecoveryMetrics(
            total_abandoned: 30,
            recovery_attempts: 20,
            successful_recoveries: 10,
            recovered_revenue_cents: 5000,
            recovery_rate: 0.33,
            by_strategy: []
        ));

        $this->analyticsService->shouldReceive('getValueTrends')->andReturn([]);

        // Mock getAbandonmentAnalysis using a real object or mock if allowed
        // But AbandonmentAnalysis is just a DTO, so we can mock/instantiate it if we have access
        // Assuming we mock the service return
        $abandonmentAnalysis = Mockery::mock(AIArmada\FilamentCart\Data\AbandonmentAnalysis::class);
        $abandonmentAnalysis->shouldReceive('toArray')->andReturn([]);
        $this->analyticsService->shouldReceive('getAbandonmentAnalysis')->andReturn($abandonmentAnalysis);

        $json = $this->exportService->exportToJson(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-01-31')
        );

        expect($json['period']['from'])->toBe('2025-01-01');
        expect($json['dashboard'])->toBeArray();
        expect($json['conversion_funnel'])->toBeArray();
    });

    it('exports to XLSX', function (): void {
        CartDailyMetrics::create([
            'date' => '2025-01-14',
            'carts_created' => 10,
            'carts_active' => 8,
            'carts_with_items' => 6,
            'checkouts_started' => 4,
            'checkouts_completed' => 2,
            'checkouts_abandoned' => 2,
            'total_cart_value_cents' => 123_45,
            'average_cart_value_cents' => 61_72,
            'carts_recovered' => 1,
            'recovered_revenue_cents' => 50_00,
        ]);

        $this->analyticsService->shouldReceive('getDashboardMetrics')->andReturn(new DashboardMetrics(
            total_carts: 100,
            active_carts: 50,
            abandoned_carts: 30,
            recovered_carts: 10,
            total_value_cents: 10000,
            average_cart_value_cents: 500,
            conversion_rate: 0.1,
            abandonment_rate: 0.3,
            recovery_rate: 0.2
        ));

        $this->analyticsService->shouldReceive('getConversionFunnel')->andReturn(ConversionFunnel::calculate(
            cartsCreated: 100,
            itemsAdded: 80,
            checkoutStarted: 40,
            checkoutCompleted: 10
        ));

        $this->analyticsService->shouldReceive('getRecoveryMetrics')->andReturn(new RecoveryMetrics(
            total_abandoned: 30,
            recovery_attempts: 20,
            successful_recoveries: 10,
            recovered_revenue_cents: 5000,
            recovery_rate: 0.33,
            by_strategy: [
                'email' => [
                    'attempts' => 10,
                    'conversions' => 3,
                    'revenue' => 1000,
                ],
            ]
        ));

        $this->analyticsService->shouldReceive('getAbandonmentAnalysis')->andReturn(AbandonmentAnalysis::fromData(
            byHour: [10 => 5],
            byDayOfWeek: [1 => 5],
            byCartValueRange: ['$0-50' => 5],
            byItemsCount: ['1-3' => 5],
            commonExitPoints: ['checkout' => 5],
            totalAbandonments: 5,
        ));

        $filePath = $this->exportService->exportToXlsx(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-01-31')
        );

        expect($filePath)->toBeString();
        expect(file_exists($filePath))->toBeTrue();
        expect(filesize($filePath))->toBeGreaterThan(0);

        unlink($filePath);
        expect(file_exists($filePath))->toBeFalse();
    });
});
