<?php

declare(strict_types=1);

use AIArmada\Chip\Services\LocalAnalyticsService;
use AIArmada\Chip\Services\MetricsAggregator;
use Carbon\CarbonImmutable;

describe('LocalAnalyticsService', function (): void {
    it('can be instantiated', function (): void {
        $service = new LocalAnalyticsService;
        expect($service)->toBeInstanceOf(LocalAnalyticsService::class);
    });
});

describe('MetricsAggregator', function (): void {
    afterEach(function (): void {
        Mockery::close();
    });

    it('can be instantiated', function (): void {
        $aggregator = new MetricsAggregator;
        expect($aggregator)->toBeInstanceOf(MetricsAggregator::class);
    });

    it('can calculate backfill days', function (): void {
        $aggregator = Mockery::mock(MetricsAggregator::class)->makePartial();
        $aggregator->shouldReceive('aggregateForDate')
            ->times(7);

        $startDate = CarbonImmutable::parse('2024-01-01');
        $endDate = CarbonImmutable::parse('2024-01-07');

        $days = $aggregator->backfill($startDate, $endDate);

        expect($days)->toBe(7);
    });
});
