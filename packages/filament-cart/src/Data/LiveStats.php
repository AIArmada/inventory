<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Live statistics DTO for real-time monitoring.
 */
class LiveStats extends Data
{
    public function __construct(
        public int $active_carts,
        public int $carts_with_items,
        public int $checkouts_in_progress,
        public int $recent_abandonments,
        public int $pending_alerts,
        public int $total_value_cents,
        public int $high_value_carts,
        public int $fraud_signals,
        public Carbon $updated_at,
    ) {}

    /**
     * Create default empty stats.
     */
    public static function defaults(): self
    {
        return new self(
            active_carts: 0,
            carts_with_items: 0,
            checkouts_in_progress: 0,
            recent_abandonments: 0,
            pending_alerts: 0,
            total_value_cents: 0,
            high_value_carts: 0,
            fraud_signals: 0,
            updated_at: now(),
        );
    }

    /**
     * Get total value in dollars.
     */
    public function getTotalValueDollars(): float
    {
        return $this->total_value_cents / 100;
    }

    /**
     * Get formatted total value.
     */
    public function getFormattedTotalValue(): string
    {
        return '$' . number_format($this->getTotalValueDollars(), 2);
    }

    /**
     * Check if there are any alerts requiring attention.
     */
    public function hasUrgentAlerts(): bool
    {
        return $this->pending_alerts > 0 || $this->fraud_signals > 0;
    }
}
