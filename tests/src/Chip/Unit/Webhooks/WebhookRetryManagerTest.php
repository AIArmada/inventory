<?php

declare(strict_types=1);

use AIArmada\Chip\Data\EnrichedWebhookPayload;
use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Webhooks\WebhookEnricher;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use AIArmada\Chip\Webhooks\WebhookRouter;

describe('WebhookRetryManager', function (): void {
    beforeEach(function (): void {
        $this->enricher = Mockery::mock(WebhookEnricher::class);
        $this->router = Mockery::mock(WebhookRouter::class);
        $this->manager = new WebhookRetryManager($this->enricher, $this->router);
    });

    describe('shouldRetry', function (): void {
        it('returns false for non-failed webhooks', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'processed', // Not failed
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->shouldRetry($webhook))->toBeFalse();
        });

        it('returns true for failed webhooks with retries remaining', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 2, // Less than max (5)
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->shouldRetry($webhook))->toBeTrue();
        });

        it('returns false for failed webhooks with max retries reached', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 5, // Max retries
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->shouldRetry($webhook))->toBeFalse();
        });
    });

    describe('getNextRetryDelay', function (): void {
        it('returns correct delay for first retry', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 0, // Next will be retry 1
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->getNextRetryDelay($webhook))->toBe(60); // 1 minute
        });

        it('returns correct delay for second retry', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 1, // Next will be retry 2
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->getNextRetryDelay($webhook))->toBe(300); // 5 minutes
        });

        it('returns last delay for attempts beyond schedule', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 10, // Way beyond schedule
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->getNextRetryDelay($webhook))->toBe(14400); // 4 hours (max)
        });
    });

    describe('retry', function (): void {
        it('processes retry successfully and marks webhook as processed', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-123', 'type' => 'purchase'],
                'status' => 'failed',
                'retry_count' => 1,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            // Use real EnrichedWebhookPayload since it's final
            $enrichedPayload = new EnrichedWebhookPayload(
                event: 'purchase.paid',
                rawPayload: ['id' => 'purchase-123', 'type' => 'purchase'],
            );
            $successResult = WebhookResult::handled('Success');

            $this->enricher->shouldReceive('enrich')
                ->once()
                ->with('purchase.paid', Mockery::type('array'))
                ->andReturn($enrichedPayload);

            $this->router->shouldReceive('route')
                ->once()
                ->with('purchase.paid', $enrichedPayload)
                ->andReturn($successResult);

            $result = $this->manager->retry($webhook);

            expect($result->isHandled())->toBeTrue();

            $webhook->refresh();
            expect($webhook->retry_count)->toBe(2);
            expect($webhook->status)->toBe('processed');
            expect($webhook->processed_at)->not->toBeNull();
        });

        it('handles retry failure and updates last_error', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-123', 'type' => 'purchase'],
                'status' => 'failed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            // Use real EnrichedWebhookPayload since it's final
            $enrichedPayload = new EnrichedWebhookPayload(
                event: 'purchase.paid',
                rawPayload: ['id' => 'purchase-123', 'type' => 'purchase'],
            );
            $failedResult = WebhookResult::failed('Handler error');

            $this->enricher->shouldReceive('enrich')
                ->once()
                ->andReturn($enrichedPayload);

            $this->router->shouldReceive('route')
                ->once()
                ->andReturn($failedResult);

            $result = $this->manager->retry($webhook);

            expect($result->isFailed())->toBeTrue();

            $webhook->refresh();
            expect($webhook->last_error)->toBe('Handler error');
            expect($webhook->status)->toBe('failed'); // Still failed
        });

        it('handles exception during retry', function (): void {
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['id' => 'purchase-123', 'type' => 'purchase'],
                'status' => 'failed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            $this->enricher->shouldReceive('enrich')
                ->once()
                ->andThrow(new \Exception('Enrichment failed'));

            $result = $this->manager->retry($webhook);

            expect($result->isFailed())->toBeTrue();
            expect($result->message)->toBe('Enrichment failed');

            $webhook->refresh();
            expect($webhook->last_error)->toBe('Enrichment failed');
        });
    });

    describe('setBackoffSchedule', function (): void {
        it('allows setting custom backoff schedule', function (): void {
            $customSchedule = [
                1 => 30,
                2 => 120,
                3 => 600,
            ];

            $result = $this->manager->setBackoffSchedule($customSchedule);

            expect($result)->toBe($this->manager); // Fluent interface

            // Test the new schedule is applied
            $webhook = Webhook::create([
                'title' => 'Test Webhook',
                'event' => 'purchase.paid',
                'events' => ['purchase.paid'],
                'payload' => ['test' => 'data'],
                'status' => 'failed',
                'retry_count' => 0,
                'created_on' => time(),
                'updated_on' => time(),
                'callback' => 'http://example.com/webhook',
            ]);

            expect($this->manager->getNextRetryDelay($webhook))->toBe(30);
        });
    });
});
