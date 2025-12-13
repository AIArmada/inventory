<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

use Spatie\LaravelData\Data;

/**
 * Recovery metrics DTO.
 */
class RecoveryMetrics extends Data
{
    /**
     * @param  array<string, array{attempts: int, conversions: int, revenue: int}>  $by_strategy
     */
    public function __construct(
        public int $total_abandoned,
        public int $recovery_attempts,
        public int $successful_recoveries,
        public int $recovered_revenue_cents,
        public float $recovery_rate,
        public array $by_strategy = [],
    ) {}

    public static function calculate(
        int $totalAbandoned,
        int $recoveryAttempts,
        int $successfulRecoveries,
        int $recoveredRevenueCents,
        array $byStrategy = [],
    ): self {
        $recoveryRate = $totalAbandoned > 0
            ? $successfulRecoveries / $totalAbandoned
            : 0.0;

        return new self(
            total_abandoned: $totalAbandoned,
            recovery_attempts: $recoveryAttempts,
            successful_recoveries: $successfulRecoveries,
            recovered_revenue_cents: $recoveredRevenueCents,
            recovery_rate: $recoveryRate,
            by_strategy: $byStrategy,
        );
    }
}
