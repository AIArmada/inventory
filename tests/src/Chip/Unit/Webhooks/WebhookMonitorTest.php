<?php

declare(strict_types=1);

use AIArmada\Chip\Data\WebhookHealth;
use AIArmada\Chip\Webhooks\WebhookMonitor;

describe('WebhookMonitor', function (): void {
    it('can be instantiated', function (): void {
        $monitor = new WebhookMonitor();

        expect($monitor)->toBeInstanceOf(WebhookMonitor::class);
    });

    it('has getHealth method', function (): void {
        $monitor = new WebhookMonitor();

        expect(method_exists($monitor, 'getHealth'))->toBeTrue();
    });

    it('has getEventDistribution method', function (): void {
        $monitor = new WebhookMonitor();

        expect(method_exists($monitor, 'getEventDistribution'))->toBeTrue();
    });

    it('has getFailureBreakdown method', function (): void {
        $monitor = new WebhookMonitor();

        expect(method_exists($monitor, 'getFailureBreakdown'))->toBeTrue();
    });

    it('has getHourlyVolume method', function (): void {
        $monitor = new WebhookMonitor();

        expect(method_exists($monitor, 'getHourlyVolume'))->toBeTrue();
    });

    it('has getPendingWebhooks method', function (): void {
        $monitor = new WebhookMonitor();

        expect(method_exists($monitor, 'getPendingWebhooks'))->toBeTrue();
    });

    it('has getRecentFailures method', function (): void {
        $monitor = new WebhookMonitor();

        expect(method_exists($monitor, 'getRecentFailures'))->toBeTrue();
    });
});

describe('WebhookHealth DTO', function (): void {
    it('can be created from stats', function (): void {
        $health = WebhookHealth::fromStats(
            total: 100,
            processed: 90,
            failed: 5,
            pending: 5,
            avgProcessingTimeMs: 150.5,
        );

        expect($health)->toBeInstanceOf(WebhookHealth::class);
        expect($health->total)->toBe(100);
        expect($health->processed)->toBe(90);
        expect($health->failed)->toBe(5);
        expect($health->pending)->toBe(5);
    });

    it('calculates success rate correctly', function (): void {
        $health = WebhookHealth::fromStats(
            total: 100,
            processed: 90,
            failed: 10,
            pending: 0,
            avgProcessingTimeMs: 100.0,
        );

        // Success rate should be 90%
        expect($health->successRate)->toBe(90.0);
    });

    it('handles zero total gracefully', function (): void {
        $health = WebhookHealth::fromStats(
            total: 0,
            processed: 0,
            failed: 0,
            pending: 0,
            avgProcessingTimeMs: 0.0,
        );

        expect($health->total)->toBe(0);
        // When there are no webhooks, success rate is 100% (no failures)
        expect($health->successRate)->toBe(100.0);
    });

    it('determines healthy status correctly', function (): void {
        // Healthy: >95% success rate
        $healthyStats = WebhookHealth::fromStats(
            total: 100,
            processed: 98,
            failed: 2,
            pending: 0,
            avgProcessingTimeMs: 50.0,
        );

        expect($healthyStats->isHealthy)->toBeTrue();

        // Unhealthy: <95% success rate
        $unhealthyStats = WebhookHealth::fromStats(
            total: 100,
            processed: 80,
            failed: 20,
            pending: 0,
            avgProcessingTimeMs: 50.0,
        );

        expect($unhealthyStats->isHealthy)->toBeFalse();
    });
});
