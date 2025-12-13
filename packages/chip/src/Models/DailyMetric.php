<?php

declare(strict_types=1);

namespace AIArmada\Chip\Models;

use Illuminate\Support\Carbon;

/**
 * Pre-aggregated daily metrics from local data.
 *
 * @property string $id
 * @property Carbon $date
 * @property string|null $payment_method
 * @property int $total_attempts
 * @property int $successful_count
 * @property int $failed_count
 * @property int $refunded_count
 * @property int $revenue_minor
 * @property int $refunds_minor
 * @property float $success_rate
 * @property float $avg_transaction_minor
 * @property array<string, int>|null $failure_breakdown
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DailyMetric extends ChipModel
{
    public $timestamps = true;

    /**
     * Get net revenue (revenue - refunds).
     */
    public function getNetRevenueAttribute(): int
    {
        return $this->revenue_minor - $this->refunds_minor;
    }

    /**
     * Get formatted revenue.
     */
    public function getRevenueFormattedAttribute(): string
    {
        return number_format($this->revenue_minor / 100, 2) . ' MYR';
    }

    /**
     * Get formatted net revenue.
     */
    public function getNetRevenueFormattedAttribute(): string
    {
        return number_format($this->net_revenue / 100, 2) . ' MYR';
    }

    /**
     * Get formatted average transaction.
     */
    public function getAvgTransactionFormattedAttribute(): string
    {
        return number_format($this->avg_transaction_minor / 100, 2) . ' MYR';
    }

    /**
     * Check if this is a totals row (null payment method).
     */
    public function isTotals(): bool
    {
        return $this->payment_method === null;
    }

    protected static function tableSuffix(): string
    {
        return 'daily_metrics';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'success_rate' => 'float',
            'avg_transaction_minor' => 'float',
            'failure_breakdown' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
