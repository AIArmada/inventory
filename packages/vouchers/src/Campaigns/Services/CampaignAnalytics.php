<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Campaigns\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Vouchers\Campaigns\Enums\CampaignEventType;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use AIArmada\Vouchers\Campaigns\Models\CampaignEvent;
use AIArmada\Vouchers\Campaigns\Models\CampaignVariant;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CampaignAnalytics
{
    /**
     * Get campaign funnel metrics.
     *
     * @return array{
     *   impressions: int,
     *   applications: int,
     *   conversions: int,
     *   abandonments: int,
     *   application_rate: float,
     *   conversion_rate: float,
     *   abandonment_rate: float,
     *   overall_conversion_rate: float
     * }
     */
    public function getFunnelMetrics(Campaign $campaign, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = $campaign->events();

        if ($from !== null && $to !== null) {
            $query->occurredBetween($from, $to);
        }

        $impressions = $query->clone()->ofType(CampaignEventType::Impression)->count();
        $applications = $query->clone()->ofType(CampaignEventType::Application)->count();
        $conversions = $query->clone()->ofType(CampaignEventType::Conversion)->count();
        $abandonments = $query->clone()->ofType(CampaignEventType::Abandonment)->count();

        return [
            'impressions' => $impressions,
            'applications' => $applications,
            'conversions' => $conversions,
            'abandonments' => $abandonments,
            'application_rate' => $impressions > 0 ? round(($applications / $impressions) * 100, 2) : 0.0,
            'conversion_rate' => $applications > 0 ? round(($conversions / $applications) * 100, 2) : 0.0,
            'abandonment_rate' => $applications > 0 ? round(($abandonments / $applications) * 100, 2) : 0.0,
            'overall_conversion_rate' => $impressions > 0 ? round(($conversions / $impressions) * 100, 2) : 0.0,
        ];
    }

    /**
     * Get revenue metrics.
     *
     * @return array{
     *   total_revenue_cents: int,
     *   total_discount_cents: int,
     *   net_revenue_cents: int,
     *   average_order_value_cents: float|null,
     *   average_discount_cents: float|null,
     *   roi_percentage: float|null
     * }
     */
    public function getRevenueMetrics(Campaign $campaign, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = $campaign->events()->ofType(CampaignEventType::Conversion);

        if ($from !== null && $to !== null) {
            $query->occurredBetween($from, $to);
        }

        $conversionCount = $query->clone()->count();
        $totalRevenue = (int) $query->clone()->sum('value_cents');
        $totalDiscount = (int) $query->clone()->sum('discount_cents');
        $netRevenue = $totalRevenue - $totalDiscount;

        $aov = $conversionCount > 0 ? $totalRevenue / $conversionCount : null;
        $avgDiscount = $conversionCount > 0 ? $totalDiscount / $conversionCount : null;
        $roi = $totalDiscount > 0 ? (($netRevenue / $totalDiscount) * 100) : null;

        return [
            'total_revenue_cents' => $totalRevenue,
            'total_discount_cents' => $totalDiscount,
            'net_revenue_cents' => $netRevenue,
            'average_order_value_cents' => $aov !== null ? round($aov, 2) : null,
            'average_discount_cents' => $avgDiscount !== null ? round($avgDiscount, 2) : null,
            'roi_percentage' => $roi !== null ? round($roi, 2) : null,
        ];
    }

    /**
     * Get time series data for campaign metrics.
     *
     * @return array<string, array{impressions: int, applications: int, conversions: int, revenue_cents: int}>
     */
    public function getTimeSeries(
        Campaign $campaign,
        Carbon $from,
        Carbon $to,
        string $granularity = 'day'
    ): array {
        $format = match ($granularity) {
            'hour' => 'Y-m-d H:00',
            'day' => 'Y-m-d',
            'week' => 'Y-W',
            'month' => 'Y-m',
            default => 'Y-m-d',
        };

        // Initialize all periods with zeros
        $period = CarbonPeriod::create($from, "1 {$granularity}", $to);
        $data = [];
        foreach ($period as $date) {
            $data[$date->format($format)] = [
                'impressions' => 0,
                'applications' => 0,
                'conversions' => 0,
                'revenue_cents' => 0,
            ];
        }

        // Query events and group in PHP for cross-DB compatibility
        $tableName = (new CampaignEvent)->getTable();

        $eventsQuery = DB::table($tableName)
            ->select([
                'occurred_at',
                'event_type',
                'value_cents',
            ])
            ->where("{$tableName}.campaign_id", $campaign->id)
            ->whereBetween('occurred_at', [$from, $to]);

        $this->applyOwnerScopeToEventsQuery($eventsQuery, $tableName);

        $events = $eventsQuery->get();

        foreach ($events as $event) {
            $occurredAt = Carbon::parse($event->occurred_at);
            $periodKey = $occurredAt->format($format);

            if (! isset($data[$periodKey])) {
                continue;
            }

            $type = CampaignEventType::tryFrom((string) $event->event_type);
            if ($type === null) {
                continue;
            }

            match ($type) {
                CampaignEventType::Impression => $data[$periodKey]['impressions']++,
                CampaignEventType::Application => $data[$periodKey]['applications']++,
                CampaignEventType::Conversion => [
                    $data[$periodKey]['conversions']++,
                    $data[$periodKey]['revenue_cents'] += (int) ($event->value_cents ?? 0),
                ],
                default => null,
            };
        }

        return $data;
    }

    /**
     * Get channel performance breakdown.
     *
     * @return array<string, array{impressions: int, applications: int, conversions: int, revenue_cents: int, conversion_rate: float}>
     */
    public function getChannelPerformance(Campaign $campaign, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $tableName = (new CampaignEvent)->getTable();

        $query = DB::table($tableName)
            ->select([
                'channel',
                'event_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(value_cents), 0) as total_value'),
            ])
            ->where("{$tableName}.campaign_id", $campaign->id)
            ->whereNotNull('channel')
            ->groupBy('channel', 'event_type');

        if ($from !== null && $to !== null) {
            $query->whereBetween('occurred_at', [$from, $to]);
        }

        $this->applyOwnerScopeToEventsQuery($query, $tableName);

        $results = $query->get();

        $channels = [];
        foreach ($results as $row) {
            $channel = $row->channel ?? 'unknown';
            if (! isset($channels[$channel])) {
                $channels[$channel] = [
                    'impressions' => 0,
                    'applications' => 0,
                    'conversions' => 0,
                    'revenue_cents' => 0,
                    'conversion_rate' => 0.0,
                ];
            }

            $type = CampaignEventType::tryFrom($row->event_type);
            if ($type === null) {
                continue;
            }

            match ($type) {
                CampaignEventType::Impression => $channels[$channel]['impressions'] = (int) $row->count,
                CampaignEventType::Application => $channels[$channel]['applications'] = (int) $row->count,
                CampaignEventType::Conversion => [
                    $channels[$channel]['conversions'] = (int) $row->count,
                    $channels[$channel]['revenue_cents'] = (int) $row->total_value,
                ],
                default => null,
            };
        }

        // Calculate conversion rates
        foreach ($channels as $channel => $metrics) {
            if ($metrics['applications'] > 0) {
                $channels[$channel]['conversion_rate'] = round(
                    ($metrics['conversions'] / $metrics['applications']) * 100,
                    2
                );
            }
        }

        return $channels;
    }

    private function applyOwnerScopeToEventsQuery($query, string $eventsTable): void
    {
        if (! (bool) config('vouchers.owner.enabled', false)) {
            return;
        }

        $campaignsTable = (new Campaign)->getTable();
        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('vouchers.owner.include_global', false);

        $query->join($campaignsTable, "{$campaignsTable}.id", '=', "{$eventsTable}.campaign_id");

        OwnerQuery::applyToQueryBuilder($query, $owner, $includeGlobal, "{$campaignsTable}.owner_type", "{$campaignsTable}.owner_id");
    }

    /**
     * Get A/B test results with statistical analysis.
     *
     * @return array{
     *   variants: array<string, array<string, mixed>>,
     *   winner: string|null,
     *   is_significant: bool,
     *   confidence: float|null,
     *   recommendation: string
     * }
     */
    public function getABTestResults(Campaign $campaign): array
    {
        if (! $campaign->ab_testing_enabled) {
            return [
                'variants' => [],
                'winner' => null,
                'is_significant' => false,
                'confidence' => null,
                'recommendation' => 'A/B testing is not enabled for this campaign.',
            ];
        }

        /** @var Collection<int, CampaignVariant> $variants */
        $variants = $campaign->variants()->orderBy('variant_code')->get();

        if ($variants->isEmpty()) {
            return [
                'variants' => [],
                'winner' => null,
                'is_significant' => false,
                'confidence' => null,
                'recommendation' => 'No variants configured for this campaign.',
            ];
        }

        $control = $variants->firstWhere('is_control', true);

        $variantData = [];
        $bestVariant = null;
        $bestConversionRate = 0.0;
        $isSignificant = false;
        $confidence = null;

        foreach ($variants as $variant) {
            $stats = $control !== null && ! $variant->is_control
                ? $variant->calculateSignificance($control)
                : null;

            $comparison = $control !== null && ! $variant->is_control
                ? $variant->compareToVariant($control)
                : null;

            $variantData[$variant->variant_code] = [
                'name' => $variant->name,
                'is_control' => $variant->is_control,
                'traffic_percentage' => $variant->traffic_percentage,
                'impressions' => $variant->impressions,
                'applications' => $variant->applications,
                'conversions' => $variant->conversions,
                'revenue_cents' => $variant->revenue_cents,
                'discount_cents' => $variant->discount_cents,
                'conversion_rate' => $variant->conversion_rate,
                'application_rate' => $variant->application_rate,
                'average_order_value' => $variant->average_order_value,
                'statistical_significance' => $stats,
                'comparison_to_control' => $comparison,
            ];

            if ($variant->conversion_rate > $bestConversionRate) {
                $bestConversionRate = $variant->conversion_rate;
                $bestVariant = $variant;
            }

            if ($stats !== null && $stats['significant']) {
                $isSignificant = true;
                $confidence = max($confidence ?? 0, 1 - $stats['p_value']);
            }
        }

        $recommendation = $this->generateRecommendation($variants, $control, $bestVariant, $isSignificant);

        return [
            'variants' => $variantData,
            'winner' => $campaign->ab_winner_variant ?? ($isSignificant ? $bestVariant?->variant_code : null),
            'is_significant' => $isSignificant,
            'confidence' => $confidence !== null ? round($confidence * 100, 2) : null,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Get campaign performance summary.
     *
     * @return array<string, mixed>
     */
    public function getPerformanceSummary(Campaign $campaign): array
    {
        $funnel = $this->getFunnelMetrics($campaign);
        $revenue = $this->getRevenueMetrics($campaign);

        $now = Carbon::now();

        $daysSinceStart = $campaign->starts_at !== null
            ? (int) $campaign->starts_at->diffInDays($now)
            : 0;

        $daysRemaining = $campaign->ends_at !== null
            ? (int) max(0, $now->diffInDays($campaign->ends_at))
            : null;

        $dailyBurnRate = $daysSinceStart > 0
            ? $campaign->spent_cents / $daysSinceStart
            : 0;

        $projectedSpend = $daysRemaining !== null
            ? $campaign->spent_cents + ($dailyBurnRate * $daysRemaining)
            : null;

        return [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'status' => $campaign->status->value,
            'is_active' => $campaign->isActive(),
            'funnel' => $funnel,
            'revenue' => $revenue,
            'budget' => [
                'total_cents' => $campaign->budget_cents,
                'spent_cents' => $campaign->spent_cents,
                'remaining_cents' => $campaign->remaining_budget,
                'utilization_percentage' => $campaign->budget_utilization,
                'daily_burn_rate_cents' => round($dailyBurnRate, 2),
                'projected_spend_cents' => $projectedSpend !== null ? round($projectedSpend, 2) : null,
            ],
            'redemptions' => [
                'max' => $campaign->max_redemptions,
                'current' => $campaign->current_redemptions,
                'remaining' => $campaign->remaining_redemptions,
            ],
            'timeline' => [
                'starts_at' => $campaign->starts_at?->toIso8601String(),
                'ends_at' => $campaign->ends_at?->toIso8601String(),
                'days_since_start' => $daysSinceStart,
                'days_remaining' => $daysRemaining,
            ],
        ];
    }

    /**
     * Compare multiple campaigns.
     *
     * @param  Collection<int, Campaign>  $campaigns
     * @return array<string, array<string, mixed>>
     */
    public function compareCampaigns(Collection $campaigns): array
    {
        $comparison = [];

        foreach ($campaigns as $campaign) {
            $funnel = $this->getFunnelMetrics($campaign);
            $revenue = $this->getRevenueMetrics($campaign);

            $comparison[$campaign->id] = [
                'name' => $campaign->name,
                'type' => $campaign->type->value,
                'status' => $campaign->status->value,
                'impressions' => $funnel['impressions'],
                'conversions' => $funnel['conversions'],
                'conversion_rate' => $funnel['conversion_rate'],
                'revenue_cents' => $revenue['total_revenue_cents'],
                'discount_cents' => $revenue['total_discount_cents'],
                'roi_percentage' => $revenue['roi_percentage'],
                'budget_utilization' => $campaign->budget_utilization,
            ];
        }

        return $comparison;
    }

    /**
     * Generate recommendation based on A/B test results.
     *
     * @param  Collection<int, CampaignVariant>  $variants
     */
    private function generateRecommendation(
        Collection $variants,
        ?CampaignVariant $control,
        ?CampaignVariant $bestVariant,
        bool $isSignificant
    ): string {
        $minSampleSize = 100;
        $totalApplications = $variants->sum('applications');

        if ($totalApplications < $minSampleSize) {
            $needed = $minSampleSize - $totalApplications;

            return "Need approximately {$needed} more applications to reach statistical significance.";
        }

        if (! $isSignificant) {
            return 'No statistically significant difference between variants. Continue running the test.';
        }

        if ($bestVariant === null) {
            return 'Unable to determine a winner. Review variant configuration.';
        }

        if ($bestVariant->is_control) {
            return 'Control variant is performing best. Consider keeping current approach or testing new variants.';
        }

        $lift = $control !== null
            ? round($bestVariant->compareToVariant($control)['conversion_lift'], 1)
            : 0;

        return "Variant {$bestVariant->variant_code} ({$bestVariant->name}) shows a {$lift}% improvement. Consider declaring it the winner.";
    }
}
