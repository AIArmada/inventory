<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

use Spatie\LaravelData\Data;

/**
 * Dashboard metrics DTO.
 */
class DashboardMetrics extends Data
{
    public function __construct(
        public int $total_carts,
        public int $active_carts,
        public int $abandoned_carts,
        public int $recovered_carts,
        public int $total_value_cents,
        public float $conversion_rate,
        public float $abandonment_rate,
        public float $recovery_rate,
        public int $average_cart_value_cents,
        public ?float $conversion_rate_change = null,
        public ?float $abandonment_rate_change = null,
        public ?float $recovery_rate_change = null,
    ) {}
}
