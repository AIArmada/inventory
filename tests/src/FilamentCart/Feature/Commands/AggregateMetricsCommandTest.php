<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Commands\AggregateMetricsCommand;
use AIArmada\FilamentCart\Services\MetricsAggregator;

describe('AggregateMetricsCommand', function (): void {
    it('runs successfully with default options', function (): void {
        $mock = Mockery::mock(MetricsAggregator::class);
        $mock->shouldReceive('aggregateForDate')->andReturn();

        $this->app->instance(MetricsAggregator::class, $mock);

        $this->artisan('cart:aggregate-metrics')
            ->assertSuccessful();
    });

    it('accepts date option for single date aggregation', function (): void {
        $mock = Mockery::mock(MetricsAggregator::class);
        $mock->shouldReceive('aggregateForDate')->once()->andReturn();

        $this->app->instance(MetricsAggregator::class, $mock);

        $this->artisan('cart:aggregate-metrics', ['--date' => '2025-01-10'])
            ->assertSuccessful();
    });

    it('accepts from and to options for date range', function (): void {
        $mock = Mockery::mock(MetricsAggregator::class);
        $mock->shouldReceive('aggregateForDate')->times(3)->andReturn();

        $this->app->instance(MetricsAggregator::class, $mock);

        $this->artisan('cart:aggregate-metrics', [
            '--from' => '2025-01-10',
            '--to' => '2025-01-12',
        ])->assertSuccessful();
    });

    it('accepts segment option for segmented aggregation', function (): void {
        $mock = Mockery::mock(MetricsAggregator::class);
        // At least 2 calls per day (global + segment)
        $mock->shouldReceive('aggregateForDate')->andReturn();

        $this->app->instance(MetricsAggregator::class, $mock);

        $this->artisan('cart:aggregate-metrics', [
            '--date' => '2025-01-10',
            '--segment' => ['high_value'],
        ])->assertSuccessful();
    });

    it('accepts days option for backfill', function (): void {
        $mock = Mockery::mock(MetricsAggregator::class);
        $mock->shouldReceive('aggregateForDate')->times(3)->andReturn();

        $this->app->instance(MetricsAggregator::class, $mock);

        $this->artisan('cart:aggregate-metrics', ['--days' => '3'])
            ->assertSuccessful();
    });
});
