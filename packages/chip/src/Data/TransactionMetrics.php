<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Spatie\LaravelData\Data;

/**
 * Transaction metrics from local data.
 */
final class TransactionMetrics extends Data
{
    public function __construct(
        public readonly int $total,
        public readonly int $successful,
        public readonly int $failed,
        public readonly int $pending,
        public readonly int $refunded,
        public readonly float $successRate,
    ) {}

    /**
     * Check if success rate is healthy (above 95%).
     */
    public function isHealthy(): bool
    {
        return $this->successRate >= 95;
    }

    /**
     * Get failure rate.
     */
    public function failureRate(): float
    {
        return $this->total > 0
            ? round($this->failed / $this->total * 100, 2)
            : 0.0;
    }
}
