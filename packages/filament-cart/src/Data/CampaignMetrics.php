<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

use Spatie\LaravelData\Data;

/**
 * Campaign performance metrics DTO.
 */
class CampaignMetrics extends Data
{
    public function __construct(
        public int $total_targeted,
        public int $total_sent,
        public int $total_opened,
        public int $total_clicked,
        public int $total_recovered,
        public int $recovered_revenue_cents,
        public float $open_rate,
        public float $click_rate,
        public float $conversion_rate,
        public int $average_recovered_value_cents,
        public float $roi,
        /** @var array<string, array{attempts: int, opens: int, clicks: int, conversions: int}> */
        public array $by_channel,
        /** @var array<string, array{attempts: int, conversions: int, rate: float}> */
        public array $by_attempt_number,
    ) {}

    /**
     * @param  array<string, array{attempts: int, opens: int, clicks: int, conversions: int}>  $byChannel
     * @param  array<string, array{attempts: int, conversions: int, rate: float}>  $byAttemptNumber
     */
    public static function calculate(
        int $totalTargeted,
        int $totalSent,
        int $totalOpened,
        int $totalClicked,
        int $totalRecovered,
        int $recoveredRevenueCents,
        array $byChannel = [],
        array $byAttemptNumber = [],
    ): self {
        $openRate = $totalSent > 0 ? $totalOpened / $totalSent : 0.0;
        $clickRate = $totalSent > 0 ? $totalClicked / $totalSent : 0.0;
        $conversionRate = $totalSent > 0 ? $totalRecovered / $totalSent : 0.0;
        $avgRecoveredValue = $totalRecovered > 0 ? (int) ($recoveredRevenueCents / $totalRecovered) : 0;

        // Simple ROI calculation (assumes cost per email is negligible)
        // In real scenario, would subtract campaign costs
        $roi = $totalSent > 0 ? $recoveredRevenueCents / 100 : 0.0;

        return new self(
            total_targeted: $totalTargeted,
            total_sent: $totalSent,
            total_opened: $totalOpened,
            total_clicked: $totalClicked,
            total_recovered: $totalRecovered,
            recovered_revenue_cents: $recoveredRevenueCents,
            open_rate: $openRate,
            click_rate: $clickRate,
            conversion_rate: $conversionRate,
            average_recovered_value_cents: $avgRecoveredValue,
            roi: $roi,
            by_channel: $byChannel,
            by_attempt_number: $byAttemptNumber,
        );
    }
}
