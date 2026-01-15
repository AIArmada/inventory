<?php

declare(strict_types=1);

use AIArmada\Chip\Commands\AggregateMetricsCommand;
use AIArmada\Chip\Commands\CleanWebhooksCommand;
use AIArmada\Chip\Commands\RetryWebhooksCommand;
use AIArmada\Chip\Services\MetricsAggregator;
use AIArmada\Chip\Webhooks\WebhookRetryManager;

describe('AggregateMetricsCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new AggregateMetricsCommand;

        expect($command->getName())->toBe('chip:aggregate-metrics');
    });

    it('has description', function (): void {
        $command = new AggregateMetricsCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('CleanWebhooksCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new CleanWebhooksCommand;

        expect($command->getName())->toBe('chip:clean-webhooks');
    });

    it('has description', function (): void {
        $command = new CleanWebhooksCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('RetryWebhooksCommand', function (): void {
    it('has correct signature', function (): void {
        $command = new RetryWebhooksCommand;

        expect($command->getName())->toBe('chip:retry-webhooks');
    });

    it('has description', function (): void {
        $command = new RetryWebhooksCommand;

        expect($command->getDescription())->not->toBeEmpty();
    });
});

describe('AggregateMetricsCommand execution', function (): void {
    it('aggregates metrics for yesterday by default', function (): void {
        $aggregator = Mockery::mock(MetricsAggregator::class);
        $aggregator->shouldReceive('aggregateForDate')
            ->once()
            ->withArgs(fn ($date) => $date->isYesterday());

        $this->artisan('chip:aggregate-metrics')
            ->assertSuccessful();
    })->skip('Requires service binding');

    it('aggregates metrics for specific date', function (): void {
        $aggregator = Mockery::mock(MetricsAggregator::class);
        $aggregator->shouldReceive('aggregateForDate')
            ->once()
            ->withArgs(fn ($date) => $date->toDateString() === '2024-01-15');

        $this->artisan('chip:aggregate-metrics', ['--date' => '2024-01-15'])
            ->assertSuccessful();
    })->skip('Requires service binding');
});

describe('CleanWebhooksCommand execution', function (): void {
    it('shows message when no webhooks to clean', function (): void {
        $this->artisan('chip:clean-webhooks', ['--dry-run' => true])
            ->assertSuccessful();
    })->skip('Requires database');
});

describe('RetryWebhooksCommand execution', function (): void {
    it('shows message when no webhooks to retry', function (): void {
        $manager = Mockery::mock(WebhookRetryManager::class);
        $manager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(collect([]));

        $this->app->instance(WebhookRetryManager::class, $manager);

        $this->artisan('chip:retry-webhooks')
            ->assertSuccessful();
    })->skip('Requires service binding');
});
