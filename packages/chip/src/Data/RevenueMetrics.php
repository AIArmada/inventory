<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use Spatie\LaravelData\Data;

/**
 * Revenue metrics from local data.
 */
final class RevenueMetrics extends Data
{
    public function __construct(
        public readonly int $grossRevenue,
        public readonly int $refunds,
        public readonly int $netRevenue,
        public readonly int $transactionCount,
        public readonly float $averageTransaction,
        public readonly float $growthRate,
        public readonly string $currency = 'MYR',
    ) {}

    /**
     * Get formatted gross revenue.
     */
    public function grossRevenueFormatted(): string
    {
        return number_format($this->grossRevenue / 100, 2) . ' ' . $this->currency;
    }

    /**
     * Get formatted net revenue.
     */
    public function netRevenueFormatted(): string
    {
        return number_format($this->netRevenue / 100, 2) . ' ' . $this->currency;
    }

    /**
     * Get formatted average transaction.
     */
    public function averageTransactionFormatted(): string
    {
        return number_format($this->averageTransaction / 100, 2) . ' ' . $this->currency;
    }

    /**
     * Check if growth is positive.
     */
    public function hasPositiveGrowth(): bool
    {
        return $this->growthRate > 0;
    }
}
