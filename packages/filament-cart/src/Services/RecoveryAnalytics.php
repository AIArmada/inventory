<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Services;

use AIArmada\FilamentCart\Data\CampaignMetrics;
use AIArmada\FilamentCart\Data\RecoveryInsight;
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Models\RecoveryCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for recovery campaign analytics.
 */
class RecoveryAnalytics
{
    /**
     * Get metrics for a specific campaign.
     */
    public function getCampaignMetrics(RecoveryCampaign $campaign): CampaignMetrics
    {
        // By channel
        $byChannel = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('
                channel,
                COUNT(*) as attempts,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks,
                SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
            ')
            ->groupBy('channel')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->channel => [
                    'attempts' => $row->attempts,
                    'opens' => $row->opens,
                    'clicks' => $row->clicks,
                    'conversions' => $row->conversions,
                ],
            ])
            ->toArray();

        // By attempt number
        $byAttemptNumber = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('
                attempt_number,
                COUNT(*) as attempts,
                SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
            ')
            ->groupBy('attempt_number')
            ->get()
            ->mapWithKeys(fn ($row) => [
                "Attempt {$row->attempt_number}" => [
                    'attempts' => $row->attempts,
                    'conversions' => $row->conversions,
                    'rate' => $row->attempts > 0 ? $row->conversions / $row->attempts : 0.0,
                ],
            ])
            ->toArray();

        return CampaignMetrics::calculate(
            totalTargeted: $campaign->total_targeted,
            totalSent: $campaign->total_sent,
            totalOpened: $campaign->total_opened,
            totalClicked: $campaign->total_clicked,
            totalRecovered: $campaign->total_recovered,
            recoveredRevenueCents: $campaign->recovered_revenue_cents,
            byChannel: $byChannel,
            byAttemptNumber: $byAttemptNumber,
        );
    }

    /**
     * Get A/B test results for a campaign.
     *
     * @return array{control: array<string, mixed>, variant: array<string, mixed>, winner: string|null, confidence: float}
     */
    public function getAbTestResults(RecoveryCampaign $campaign): array
    {
        if (! $campaign->ab_testing_enabled) {
            return [
                'control' => [],
                'variant' => [],
                'winner' => null,
                'confidence' => 0.0,
            ];
        }

        $control = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->where('is_control', true)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks,
                SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
            ')
            ->first();

        $variant = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->where('is_variant', true)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicks,
                SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
            ')
            ->first();

        $controlRate = $control->sent > 0 ? $control->conversions / $control->sent : 0;
        $variantRate = $variant->sent > 0 ? $variant->conversions / $variant->sent : 0;

        // Simple winner determination
        $winner = null;
        $confidence = 0.0;

        if ($control->sent >= 100 && $variant->sent >= 100) {
            $diff = abs($controlRate - $variantRate);
            $pooledRate = ($control->conversions + $variant->conversions) / ($control->sent + $variant->sent);

            if ($pooledRate > 0) {
                // Z-score approximation
                $se = sqrt($pooledRate * (1 - $pooledRate) * (1 / $control->sent + 1 / $variant->sent));
                $z = $se > 0 ? $diff / $se : 0;

                // Convert to approximate confidence (simplified)
                $confidence = min(0.99, 0.5 + ($z * 0.1));

                if ($confidence >= 0.95) {
                    $winner = $controlRate > $variantRate ? 'control' : 'variant';
                }
            }
        }

        return [
            'control' => [
                'total' => $control->total,
                'sent' => $control->sent,
                'opens' => $control->opens,
                'clicks' => $control->clicks,
                'conversions' => $control->conversions,
                'conversion_rate' => round($controlRate * 100, 2),
            ],
            'variant' => [
                'total' => $variant->total,
                'sent' => $variant->sent,
                'opens' => $variant->opens,
                'clicks' => $variant->clicks,
                'conversions' => $variant->conversions,
                'conversion_rate' => round($variantRate * 100, 2),
            ],
            'winner' => $winner,
            'confidence' => round($confidence * 100, 1),
        ];
    }

    /**
     * Get strategy comparison across all campaigns.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getStrategyComparison(Carbon $from, Carbon $to): Collection
    {
        return RecoveryCampaign::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('total_sent', '>', 0)
            ->selectRaw('
                strategy,
                COUNT(*) as campaigns,
                SUM(total_sent) as total_sent,
                SUM(total_opened) as total_opened,
                SUM(total_clicked) as total_clicked,
                SUM(total_recovered) as total_recovered,
                SUM(recovered_revenue_cents) as total_revenue
            ')
            ->groupBy('strategy')
            ->get()
            ->map(fn ($row) => [
                'strategy' => $row->strategy,
                'campaigns' => $row->campaigns,
                'total_sent' => $row->total_sent,
                'total_opened' => $row->total_opened,
                'total_clicked' => $row->total_clicked,
                'total_recovered' => $row->total_recovered,
                'total_revenue' => $row->total_revenue,
                'open_rate' => round(($row->total_opened / $row->total_sent) * 100, 2),
                'click_rate' => round(($row->total_clicked / $row->total_sent) * 100, 2),
                'conversion_rate' => round(($row->total_recovered / $row->total_sent) * 100, 2),
                'avg_value' => $row->total_recovered > 0
                    ? (int) ($row->total_revenue / $row->total_recovered)
                    : 0,
            ]);
    }

    /**
     * Generate AI-driven insights for a campaign.
     *
     * @return Collection<int, RecoveryInsight>
     */
    public function generateInsights(RecoveryCampaign $campaign): Collection
    {
        $insights = collect();

        // Timing insight
        $timingInsight = $this->analyzeTimingPerformance($campaign);
        if ($timingInsight) {
            $insights->push($timingInsight);
        }

        // Strategy insight
        $strategyInsight = $this->analyzeStrategyPerformance($campaign);
        if ($strategyInsight) {
            $insights->push($strategyInsight);
        }

        // Discount insight
        if ($campaign->offer_discount) {
            $discountInsight = $this->analyzeDiscountPerformance($campaign);
            if ($discountInsight) {
                $insights->push($discountInsight);
            }
        }

        // Targeting insight
        $targetingInsight = $this->analyzeTargetingPerformance($campaign);
        if ($targetingInsight) {
            $insights->push($targetingInsight);
        }

        return $insights->sortByDesc('confidence');
    }

    /**
     * Analyze timing performance.
     */
    private function analyzeTimingPerformance(RecoveryCampaign $campaign): ?RecoveryInsight
    {
        // Analyze conversion by hour sent
        $byHour = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('sent_at')
            ->selectRaw('
                HOUR(sent_at) as hour,
                COUNT(*) as sent,
                SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
            ')
            ->groupBy('hour')
            ->having('sent', '>=', 10)
            ->get();

        if ($byHour->isEmpty()) {
            return null;
        }

        $bestHour = $byHour->sortByDesc(fn ($row) => $row->sent > 0 ? $row->conversions / $row->sent : 0)->first();
        $currentRate = $campaign->getConversionRate();
        $bestRate = $bestHour->sent > 0 ? $bestHour->conversions / $bestHour->sent : 0;

        if ($bestRate > $currentRate * 1.2) {
            return RecoveryInsight::timing(
                recommendation: "Consider sending recovery emails around {$bestHour->hour}:00 for better conversion rates.",
                optimalDelayMinutes: 60,
                expectedLift: $bestRate - $currentRate,
                confidence: min(0.9, $byHour->sum('sent') / 100),
            );
        }

        return null;
    }

    /**
     * Analyze strategy performance.
     */
    private function analyzeStrategyPerformance(RecoveryCampaign $campaign): ?RecoveryInsight
    {
        $comparison = $this->getStrategyComparison(
            Carbon::now()->subMonths(3),
            Carbon::now(),
        );

        $currentStrategy = $comparison->firstWhere('strategy', $campaign->strategy);
        $bestStrategy = $comparison->sortByDesc('conversion_rate')->first();

        if (! $currentStrategy || ! $bestStrategy) {
            return null;
        }

        if ($bestStrategy['strategy'] !== $campaign->strategy &&
            $bestStrategy['conversion_rate'] > $currentStrategy['conversion_rate'] * 1.3) {
            return RecoveryInsight::strategy(
                recommendation: "Consider using {$bestStrategy['strategy']} strategy which has shown {$bestStrategy['conversion_rate']}% conversion rate.",
                suggestedStrategy: $bestStrategy['strategy'],
                expectedConversionRate: $bestStrategy['conversion_rate'] / 100,
                confidence: min(0.85, $bestStrategy['total_sent'] / 500),
            );
        }

        return null;
    }

    /**
     * Analyze discount performance.
     */
    private function analyzeDiscountPerformance(RecoveryCampaign $campaign): ?RecoveryInsight
    {
        // Compare campaigns with different discount levels
        $byDiscount = RecoveryCampaign::query()
            ->where('offer_discount', true)
            ->where('total_sent', '>=', 100)
            ->selectRaw('
                discount_value,
                discount_type,
                AVG(total_recovered / NULLIF(total_sent, 0)) as avg_conversion_rate
            ')
            ->groupBy('discount_value', 'discount_type')
            ->having('avg_conversion_rate', '>', 0)
            ->get();

        if ($byDiscount->count() < 2) {
            return null;
        }

        $best = $byDiscount->sortByDesc('avg_conversion_rate')->first();
        $current = $byDiscount->first(fn ($row) => $row->discount_value === $campaign->discount_value);

        if ($best && (! $current || $best->avg_conversion_rate > ($current->avg_conversion_rate ?? 0) * 1.2)) {
            return RecoveryInsight::discount(
                recommendation: "A {$best->discount_value}% discount has shown better conversion rates in similar campaigns.",
                suggestedDiscountPercent: (int) $best->discount_value,
                expectedLift: $best->avg_conversion_rate - ($current->avg_conversion_rate ?? $campaign->getConversionRate()),
                confidence: 0.7,
            );
        }

        return null;
    }

    /**
     * Analyze targeting performance.
     */
    private function analyzeTargetingPerformance(RecoveryCampaign $campaign): ?RecoveryInsight
    {
        // Analyze by cart value range
        $byValue = RecoveryAttempt::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw("
                CASE
                    WHEN cart_value_cents < 5000 THEN 'low'
                    WHEN cart_value_cents < 15000 THEN 'medium'
                    ELSE 'high'
                END as value_range,
                COUNT(*) as sent,
                SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
            ")
            ->groupBy('value_range')
            ->having('sent', '>=', 20)
            ->get();

        if ($byValue->isEmpty()) {
            return null;
        }

        $best = $byValue->sortByDesc(fn ($row) => $row->sent > 0 ? $row->conversions / $row->sent : 0)->first();
        $bestRate = $best->sent > 0 ? $best->conversions / $best->sent : 0;

        if ($bestRate > $campaign->getConversionRate() * 1.5) {
            $segment = match ($best->value_range) {
                'low' => 'lower value carts (under $50)',
                'medium' => 'medium value carts ($50-$150)',
                'high' => 'high value carts (over $150)',
            };

            return RecoveryInsight::targeting(
                recommendation: "Focusing on {$segment} shows significantly higher conversion rates.",
                segmentToFocus: $best->value_range,
                segmentConversionRate: $bestRate,
                confidence: min(0.9, $byValue->sum('sent') / 200),
            );
        }

        return null;
    }
}
