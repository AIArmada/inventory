<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\RecoveryMetrics;

describe('RecoveryMetrics', function (): void {
    it('can be created with constructor', function (): void {
        $metrics = new RecoveryMetrics(
            total_abandoned: 100,
            recovery_attempts: 80,
            successful_recoveries: 20,
            recovered_revenue_cents: 500000,
            recovery_rate: 0.2,
            by_strategy: [
                'email' => ['attempts' => 60, 'conversions' => 15, 'revenue' => 375000],
                'sms' => ['attempts' => 20, 'conversions' => 5, 'revenue' => 125000],
            ],
        );

        expect($metrics->total_abandoned)->toBe(100);
        expect($metrics->recovery_attempts)->toBe(80);
        expect($metrics->successful_recoveries)->toBe(20);
        expect($metrics->recovered_revenue_cents)->toBe(500000);
        expect($metrics->recovery_rate)->toBe(0.2);
        expect($metrics->by_strategy)->toHaveKeys(['email', 'sms']);
    });

    it('can be calculated via factory method', function (): void {
        $metrics = RecoveryMetrics::calculate(
            totalAbandoned: 100,
            recoveryAttempts: 80,
            successfulRecoveries: 25,
            recoveredRevenueCents: 750000,
            byStrategy: ['email' => ['attempts' => 80, 'conversions' => 25, 'revenue' => 750000]],
        );

        expect($metrics->total_abandoned)->toBe(100);
        expect($metrics->recovery_attempts)->toBe(80);
        expect($metrics->successful_recoveries)->toBe(25);
        expect($metrics->recovered_revenue_cents)->toBe(750000);
        expect($metrics->recovery_rate)->toBe(0.25); // 25 / 100
    });

    it('calculates recovery rate correctly', function (): void {
        $metrics = RecoveryMetrics::calculate(
            totalAbandoned: 200,
            recoveryAttempts: 150,
            successfulRecoveries: 50,
            recoveredRevenueCents: 1000000,
        );

        expect($metrics->recovery_rate)->toBe(0.25); // 50 / 200
    });

    it('handles zero abandoned carts gracefully', function (): void {
        $metrics = RecoveryMetrics::calculate(
            totalAbandoned: 0,
            recoveryAttempts: 0,
            successfulRecoveries: 0,
            recoveredRevenueCents: 0,
        );

        expect($metrics->recovery_rate)->toBe(0.0);
    });

    it('defaults by_strategy to empty array', function (): void {
        $metrics = RecoveryMetrics::calculate(
            totalAbandoned: 50,
            recoveryAttempts: 30,
            successfulRecoveries: 10,
            recoveredRevenueCents: 200000,
        );

        expect($metrics->by_strategy)->toBeArray();
        expect($metrics->by_strategy)->toBeEmpty();
    });

    it('can be converted to array', function (): void {
        $metrics = RecoveryMetrics::calculate(
            totalAbandoned: 100,
            recoveryAttempts: 80,
            successfulRecoveries: 20,
            recoveredRevenueCents: 500000,
        );

        $array = $metrics->toArray();

        expect($array)->toBeArray();
        expect($array['total_abandoned'])->toBe(100);
        expect($array['recovery_rate'])->toBe(0.2);
    });
});
