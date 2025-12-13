<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Spatie\LaravelData\Data;

/**
 * Webhook processing health metrics.
 */
final class WebhookHealth extends Data
{
    public function __construct(
        public readonly int $total,
        public readonly int $processed,
        public readonly int $failed,
        public readonly int $pending,
        public readonly float $successRate,
        public readonly float $avgProcessingTimeMs,
        public readonly bool $isHealthy,
    ) {}

    /**
     * Create from statistics.
     */
    public static function fromStats(
        int $total,
        int $processed,
        int $failed,
        int $pending,
        float $avgProcessingTimeMs = 0,
    ): self {
        $successRate = $total > 0
            ? round($processed / $total * 100, 2)
            : 100.0;

        // Healthy if success rate > 95% and no excessive pending
        $isHealthy = $successRate >= 95 && $pending < 100;

        return new self(
            total: $total,
            processed: $processed,
            failed: $failed,
            pending: $pending,
            successRate: $successRate,
            avgProcessingTimeMs: $avgProcessingTimeMs,
            isHealthy: $isHealthy,
        );
    }

    /**
     * Get failure rate percentage.
     */
    public function failureRate(): float
    {
        return $this->total > 0
            ? round($this->failed / $this->total * 100, 2)
            : 0.0;
    }
}
