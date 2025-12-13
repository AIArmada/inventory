<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Spatie\LaravelData\Data;

/**
 * Aggregated dashboard metrics.
 */
final class DashboardMetrics extends Data
{
    public function __construct(
        public readonly RevenueMetrics $revenue,
        public readonly TransactionMetrics $transactions,
        /** @var array<int, array{method: string, attempts: int, successful: int, success_rate: float, revenue: int}> */
        public readonly array $paymentMethods,
        /** @var array<int, array{reason: string, count: int, lost_revenue: int}> */
        public readonly array $failures,
    ) {}
}
