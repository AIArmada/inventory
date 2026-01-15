<?php

declare(strict_types=1);

use AIArmada\Chip\Commands\AggregateMetricsCommand;
use AIArmada\Chip\Commands\CleanWebhooksCommand;
use AIArmada\Chip\Commands\RetryWebhooksCommand;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Services\MetricsAggregator;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Carbon\CarbonImmutable;

describe('AggregateMetricsCommand', function (): void {
    it('aggregates for yesterday by default', function (): void {
        $aggregator = Mockery::mock(MetricsAggregator::class);
        $aggregator->shouldReceive('aggregateForDate')
            ->once()
            ->with(Mockery::on(fn ($date) => $date instanceof CarbonImmutable && $date->isYesterday()));

        $this->app->instance(MetricsAggregator::class, $aggregator);

        $this->artisan(AggregateMetricsCommand::class)
            ->expectsOutput('Aggregating metrics for ' . CarbonImmutable::yesterday()->toDateString() . ' (yesterday)...')
            ->expectsOutput('Done.')
            ->assertSuccessful();
    });

    it('aggregates for specific date with --date option', function (): void {
        $specificDate = CarbonImmutable::parse('2025-01-15');

        $aggregator = Mockery::mock(MetricsAggregator::class);
        $aggregator->shouldReceive('aggregateForDate')
            ->once()
            ->with(Mockery::on(fn ($date) => $date instanceof CarbonImmutable && $date->toDateString() === '2025-01-15'));

        $this->app->instance(MetricsAggregator::class, $aggregator);

        $this->artisan(AggregateMetricsCommand::class, ['--date' => '2025-01-15'])
            ->expectsOutput('Aggregating metrics for 2025-01-15...')
            ->expectsOutput('Done.')
            ->assertSuccessful();
    });

    it('backfills date range with --from and --to options', function (): void {
        $aggregator = Mockery::mock(MetricsAggregator::class);
        $aggregator->shouldReceive('backfill')
            ->once()
            ->with(
                Mockery::on(fn ($from) => $from instanceof CarbonImmutable && $from->toDateString() === '2025-01-01'),
                Mockery::on(fn ($to) => $to instanceof CarbonImmutable && $to->toDateString() === '2025-01-10')
            )
            ->andReturn(10);

        $this->app->instance(MetricsAggregator::class, $aggregator);

        $this->artisan(AggregateMetricsCommand::class, [
            '--from' => '2025-01-01',
            '--to' => '2025-01-10',
        ])
            ->expectsOutput('Backfilling metrics from 2025-01-01 to 2025-01-10...')
            ->expectsOutput('Aggregated metrics for 10 day(s).')
            ->assertSuccessful();
    });
});

describe('CleanWebhooksCommand', function (): void {
    it('shows message when no webhooks found', function (): void {
        $this->artisan(CleanWebhooksCommand::class)
            ->expectsOutput('No webhooks found matching the criteria.')
            ->assertSuccessful();
    });

    it('shows count and does not delete in dry-run mode', function (): void {
        // Create old webhook
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'processed',
            'created_at' => now()->subDays(60),
            'created_on' => now()->subDays(60)->timestamp,
            'updated_on' => now()->subDays(60)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        $this->artisan(CleanWebhooksCommand::class, ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 webhook(s) older than 30 days')
            ->expectsOutput('Dry run mode - no webhooks will be deleted.')
            ->assertSuccessful();

        expect(Webhook::count())->toBe(1); // Still exists
    });

    it('deletes webhooks when confirmed', function (): void {
        // Create old webhook
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'processed',
            'created_at' => now()->subDays(60),
            'created_on' => now()->subDays(60)->timestamp,
            'updated_on' => now()->subDays(60)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        $this->artisan(CleanWebhooksCommand::class, ['--days' => 30, '--status' => 'processed'])
            ->expectsOutputToContain('Found 1 webhook(s) older than 30 days')
            ->expectsConfirmation('Are you sure you want to delete 1 webhook records?', 'yes')
            ->expectsOutput('Successfully deleted 1 webhook record(s).')
            ->assertSuccessful();

        expect(Webhook::count())->toBe(0);
    });

    it('cancels when user declines confirmation', function (): void {
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'processed',
            'created_at' => now()->subDays(60),
            'created_on' => now()->subDays(60)->timestamp,
            'updated_on' => now()->subDays(60)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        $this->artisan(CleanWebhooksCommand::class)
            ->expectsConfirmation('Are you sure you want to delete 1 webhook records?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        expect(Webhook::count())->toBe(1); // Still exists
    });

    it('respects custom days option', function (): void {
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'processed',
            'created_at' => now()->subDays(10), // Only 10 days old
            'created_on' => now()->subDays(10)->timestamp,
            'updated_on' => now()->subDays(10)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        // Default 30 days should not find it
        $this->artisan(CleanWebhooksCommand::class, ['--days' => 30])
            ->expectsOutput('No webhooks found matching the criteria.')
            ->assertSuccessful();

        // 5 days should find it
        $this->artisan(CleanWebhooksCommand::class, ['--days' => 5, '--dry-run' => true])
            ->expectsOutputToContain('Found 1 webhook(s)')
            ->assertSuccessful();
    });

    it('respects status all option', function (): void {
        Webhook::create([
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'events' => ['purchase.paid'],
            'payload' => ['test' => 'data'],
            'status' => 'failed',
            'created_at' => now()->subDays(60),
            'created_on' => now()->subDays(60)->timestamp,
            'updated_on' => now()->subDays(60)->timestamp,
            'callback' => 'http://example.com/webhook',
        ]);

        // Default status 'processed' should not find 'failed'
        $this->artisan(CleanWebhooksCommand::class, ['--status' => 'processed'])
            ->expectsOutput('No webhooks found matching the criteria.')
            ->assertSuccessful();

        // status 'all' should find it
        $this->artisan(CleanWebhooksCommand::class, ['--status' => 'all', '--dry-run' => true])
            ->expectsOutputToContain('Found 1 webhook(s)')
            ->assertSuccessful();
    });
});

describe('RetryWebhooksCommand', function (): void {
    it('shows message when no webhooks are eligible', function (): void {
        $retryManager = Mockery::mock(WebhookRetryManager::class);
        $retryManager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(new EloquentCollection);

        $this->app->instance(WebhookRetryManager::class, $retryManager);

        $this->artisan(RetryWebhooksCommand::class)
            ->expectsOutput('No webhooks are eligible for retry.')
            ->assertSuccessful();
    });

    it('shows table in dry-run mode', function (): void {
        $mockWebhook = new Webhook([
            'id' => 'webhook-123',
            'title' => 'Test Webhook',
            'event' => 'purchase.paid',
            'retry_count' => 2,
            'last_error' => 'Connection timeout',
            'payload' => [],
            'created_on' => time(),
            'updated_on' => time(),
            'callback' => 'http://example.com/webhook',
            'events' => ['purchase.paid'],
        ]);

        $retryManager = Mockery::mock(WebhookRetryManager::class);
        $retryManager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(new EloquentCollection([$mockWebhook]));

        $this->app->instance(WebhookRetryManager::class, $retryManager);

        $this->artisan(RetryWebhooksCommand::class, ['--dry-run' => true])
            ->expectsOutputToContain('Found 1 webhook(s) eligible for retry')
            ->expectsOutput('Dry run mode - no webhooks will be processed.')
            ->assertSuccessful();
    });

    it('respects limit option', function (): void {
        $webhooks = collect([
            new Webhook(['id' => 'w1', 'title' => 'Test Webhook', 'events' => ['purchase.paid'], 'event' => 'purchase.paid', 'retry_count' => 1, 'payload' => [], 'created_on' => time(), 'updated_on' => time(), 'callback' => 'http://example.com/webhook']),
            new Webhook(['id' => 'w2', 'title' => 'Test Webhook', 'events' => ['purchase.paid'], 'event' => 'purchase.paid', 'retry_count' => 1, 'payload' => [], 'created_on' => time(), 'updated_on' => time(), 'callback' => 'http://example.com/webhook']),
            new Webhook(['id' => 'w3', 'title' => 'Test Webhook', 'events' => ['purchase.paid'], 'event' => 'purchase.paid', 'retry_count' => 1, 'payload' => [], 'created_on' => time(), 'updated_on' => time(), 'callback' => 'http://example.com/webhook']),
        ]);

        $retryManager = Mockery::mock(WebhookRetryManager::class);
        $retryManager->shouldReceive('getRetryableWebhooks')
            ->once()
            ->andReturn(new EloquentCollection($webhooks->all()));

        $this->app->instance(WebhookRetryManager::class, $retryManager);

        // With limit 2, should only show 2
        $this->artisan(RetryWebhooksCommand::class, ['--limit' => 2, '--dry-run' => true])
            ->expectsOutputToContain('Found 2 webhook(s) eligible for retry')
            ->assertSuccessful();
    });
});
