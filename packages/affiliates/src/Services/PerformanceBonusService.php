<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for calculating and awarding performance bonuses to top affiliates.
 */
final class PerformanceBonusService
{
    /**
     * Calculate performance bonuses for a given period.
     *
     * @return array<string, array{
     *     affiliate_id: string,
     *     affiliate_name: string,
     *     bonus_type: string,
     *     amount_minor: int,
     *     reason: string,
     *     metrics: array<string, mixed>
     * }>
     */
    public function calculateBonuses(
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $bonuses = [];

        // Top Performer Bonus
        $topPerformerBonuses = $this->calculateTopPerformerBonuses($from, $to);
        foreach ($topPerformerBonuses as $bonus) {
            $bonuses[$bonus['affiliate_id'] . '_top_performer'] = $bonus;
        }

        // Recruitment Bonus
        $recruitmentBonuses = $this->calculateRecruitmentBonuses($from, $to);
        foreach ($recruitmentBonuses as $bonus) {
            $bonuses[$bonus['affiliate_id'] . '_recruitment'] = $bonus;
        }

        // Consistency Bonus
        $consistencyBonuses = $this->calculateConsistencyBonuses($from, $to);
        foreach ($consistencyBonuses as $bonus) {
            $bonuses[$bonus['affiliate_id'] . '_consistency'] = $bonus;
        }

        // Growth Bonus
        $growthBonuses = $this->calculateGrowthBonuses($from, $to);
        foreach ($growthBonuses as $bonus) {
            $bonuses[$bonus['affiliate_id'] . '_growth'] = $bonus;
        }

        return $bonuses;
    }

    /**
     * Award bonuses to affiliate balances.
     *
     * @param  array<string, array{affiliate_id: string, affiliate_name: string, bonus_type: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>  $bonuses
     * @return int Number of bonuses awarded
     */
    public function awardBonuses(array $bonuses): int
    {
        $awarded = 0;

        foreach ($bonuses as $bonus) {
            $affiliate = Affiliate::find($bonus['affiliate_id']);

            if (! $affiliate || $affiliate->status !== AffiliateStatus::Active) {
                continue;
            }

            $balance = AffiliateBalance::firstOrCreate(
                [
                    'affiliate_id' => $affiliate->id,
                    'currency' => $affiliate->currency ?? config('affiliates.currency.default', 'USD'),
                ],
                [
                    'holding_minor' => 0,
                    'available_minor' => 0,
                    'lifetime_earnings_minor' => 0,
                    'minimum_payout_minor' => config('affiliates.payouts.minimum_amount', 5000),
                ]
            );

            // Award bonus directly to available (no holding period for bonuses)
            $balance->increment('available_minor', $bonus['amount_minor']);
            $balance->increment('lifetime_earnings_minor', $bonus['amount_minor']);

            // Record as a bonus conversion
            AffiliateConversion::create([
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->code,
                'order_reference' => 'BONUS-' . now()->format('Ymd') . '-' . mb_strtoupper(mb_substr(md5($bonus['affiliate_id'] . $bonus['bonus_type']), 0, 8)),
                'subtotal_minor' => 0,
                'total_minor' => 0,
                'commission_minor' => $bonus['amount_minor'],
                'status' => ConversionStatus::Approved,
                'occurred_at' => now(),
                'metadata' => [
                    'type' => 'performance_bonus',
                    'bonus_type' => $bonus['bonus_type'],
                    'reason' => $bonus['reason'],
                    'metrics' => $bonus['metrics'],
                ],
            ]);

            $awarded++;
        }

        return $awarded;
    }

    /**
     * Get leaderboard for a given period.
     *
     * @return Collection<int, array{
     *     rank: int,
     *     affiliate_id: string,
     *     affiliate_name: string,
     *     affiliate_code: string,
     *     total_revenue: int,
     *     total_conversions: int,
     *     total_commissions: int,
     *     avg_order_value: float
     * }>
     */
    public function getLeaderboard(
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $limit = 10
    ): Collection {
        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        $conversionsTable = (new AffiliateConversion)->getTable();
        $affiliatesTable = (new Affiliate)->getTable();

        return DB::table($conversionsTable)
            ->join($affiliatesTable, "{$conversionsTable}.affiliate_id", '=', "{$affiliatesTable}.id")
            ->select([
                "{$affiliatesTable}.id as affiliate_id",
                "{$affiliatesTable}.name as affiliate_name",
                "{$affiliatesTable}.code as affiliate_code",
                DB::raw("SUM({$conversionsTable}.total_minor) as total_revenue"),
                DB::raw("COUNT({$conversionsTable}.id) as total_conversions"),
                DB::raw("SUM({$conversionsTable}.commission_minor) as total_commissions"),
                DB::raw("AVG({$conversionsTable}.total_minor) as avg_order_value"),
            ])
            ->whereBetween("{$conversionsTable}.occurred_at", [$from, $to])
            ->where("{$conversionsTable}.status", ConversionStatus::Approved->value)
            ->where("{$affiliatesTable}.status", AffiliateStatus::Active->value)
            ->groupBy("{$affiliatesTable}.id", "{$affiliatesTable}.name", "{$affiliatesTable}.code")
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(function ($row, $index) {
                return [
                    'rank' => $index + 1,
                    'affiliate_id' => $row->affiliate_id,
                    'affiliate_name' => $row->affiliate_name,
                    'affiliate_code' => $row->affiliate_code,
                    'total_revenue' => (int) $row->total_revenue,
                    'total_conversions' => (int) $row->total_conversions,
                    'total_commissions' => (int) $row->total_commissions,
                    'avg_order_value' => round((float) $row->avg_order_value, 2),
                ];
            });
    }

    /**
     * Calculate top performer bonuses (top 3 by revenue).
     *
     * @return array<int, array{affiliate_id: string, affiliate_name: string, bonus_type: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateTopPerformerBonuses(Carbon $from, Carbon $to): array
    {
        $config = config('affiliates.bonuses.top_performer', [
            'enabled' => true,
            'positions' => [
                1 => 50000, // $500 for 1st place
                2 => 25000, // $250 for 2nd place
                3 => 10000, // $100 for 3rd place
            ],
            'min_revenue' => 100000, // Minimum $1000 revenue to qualify
        ]);

        if (! ($config['enabled'] ?? true)) {
            return [];
        }

        $leaderboard = $this->getLeaderboard($from, $to, 3);
        $bonuses = [];

        foreach ($leaderboard as $entry) {
            if ($entry['total_revenue'] < ($config['min_revenue'] ?? 100000)) {
                continue;
            }

            $position = $entry['rank'];
            $bonusAmount = $config['positions'][$position] ?? 0;

            if ($bonusAmount > 0) {
                $bonuses[] = [
                    'affiliate_id' => $entry['affiliate_id'],
                    'affiliate_name' => $entry['affiliate_name'],
                    'bonus_type' => 'top_performer',
                    'amount_minor' => $bonusAmount,
                    'reason' => "Top Performer Bonus - #{$position} for " . $from->format('F Y'),
                    'metrics' => [
                        'position' => $position,
                        'total_revenue' => $entry['total_revenue'],
                        'total_conversions' => $entry['total_conversions'],
                        'period' => $from->format('Y-m'),
                    ],
                ];
            }
        }

        return $bonuses;
    }

    /**
     * Calculate recruitment bonuses for affiliates who recruited new active members.
     *
     * @return array<int, array{affiliate_id: string, affiliate_name: string, bonus_type: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateRecruitmentBonuses(Carbon $from, Carbon $to): array
    {
        $config = config('affiliates.bonuses.recruitment', [
            'enabled' => true,
            'bonus_per_recruit' => 2500, // $25 per active recruit
            'min_recruits' => 3,
            'max_bonus' => 25000, // Max $250
        ]);

        if (! ($config['enabled'] ?? true)) {
            return [];
        }

        $bonuses = [];

        // Get affiliates who recruited during the period
        $recruiters = Affiliate::query()
            ->where('status', AffiliateStatus::Active)
            ->whereHas('children', function ($query) use ($from, $to): void {
                $query->whereBetween('created_at', [$from, $to])
                    ->where('status', AffiliateStatus::Active);
            })
            ->withCount([
                'children' => function ($query) use ($from, $to): void {
                    $query->whereBetween('created_at', [$from, $to])
                        ->where('status', AffiliateStatus::Active);
                },
            ])
            ->having('children_count', '>=', $config['min_recruits'] ?? 3)
            ->get();

        foreach ($recruiters as $recruiter) {
            $recruitCount = $recruiter->children_count;
            $bonusAmount = min(
                $recruitCount * ($config['bonus_per_recruit'] ?? 2500),
                $config['max_bonus'] ?? 25000
            );

            $bonuses[] = [
                'affiliate_id' => $recruiter->id,
                'affiliate_name' => $recruiter->name,
                'bonus_type' => 'recruitment',
                'amount_minor' => $bonusAmount,
                'reason' => "Recruitment Bonus - {$recruitCount} new active recruits in " . $from->format('F Y'),
                'metrics' => [
                    'recruit_count' => $recruitCount,
                    'period' => $from->format('Y-m'),
                ],
            ];
        }

        return $bonuses;
    }

    /**
     * Calculate consistency bonuses for affiliates with sales every week.
     *
     * @return array<int, array{affiliate_id: string, affiliate_name: string, bonus_type: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateConsistencyBonuses(Carbon $from, Carbon $to): array
    {
        $config = config('affiliates.bonuses.consistency', [
            'enabled' => true,
            'bonus_amount' => 5000, // $50 bonus
            'min_weeks' => 4,
            'min_conversions_per_week' => 1,
        ]);

        if (! ($config['enabled'] ?? true)) {
            return [];
        }

        $bonuses = [];
        $minWeeks = $config['min_weeks'] ?? 4;
        $minConversionsPerWeek = $config['min_conversions_per_week'] ?? 1;

        $affiliates = Affiliate::where('status', AffiliateStatus::Active)->get();

        foreach ($affiliates as $affiliate) {
            $weeksWithSales = 0;
            $currentWeek = $from->copy()->startOfWeek();

            while ($currentWeek->lte($to)) {
                $weekEnd = $currentWeek->copy()->endOfWeek();

                $conversionsThisWeek = $affiliate->conversions()
                    ->whereBetween('occurred_at', [$currentWeek, $weekEnd])
                    ->where('status', ConversionStatus::Approved)
                    ->count();

                if ($conversionsThisWeek >= $minConversionsPerWeek) {
                    $weeksWithSales++;
                }

                $currentWeek->addWeek();
            }

            if ($weeksWithSales >= $minWeeks) {
                $bonuses[] = [
                    'affiliate_id' => $affiliate->id,
                    'affiliate_name' => $affiliate->name,
                    'bonus_type' => 'consistency',
                    'amount_minor' => $config['bonus_amount'] ?? 5000,
                    'reason' => "Consistency Bonus - Sales in {$weeksWithSales} consecutive weeks",
                    'metrics' => [
                        'weeks_with_sales' => $weeksWithSales,
                        'period' => $from->format('Y-m'),
                    ],
                ];
            }
        }

        return $bonuses;
    }

    /**
     * Calculate growth bonuses for affiliates who significantly increased performance.
     *
     * @return array<int, array{affiliate_id: string, affiliate_name: string, bonus_type: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculateGrowthBonuses(Carbon $from, Carbon $to): array
    {
        $config = config('affiliates.bonuses.growth', [
            'enabled' => true,
            'bonus_amount' => 7500, // $75 bonus
            'min_growth_percentage' => 50, // 50% growth required
            'min_previous_revenue' => 50000, // At least $500 previous month
        ]);

        if (! ($config['enabled'] ?? true)) {
            return [];
        }

        $bonuses = [];

        // Calculate previous period
        $prevFrom = $from->copy()->subMonth()->startOfMonth();
        $prevTo = $from->copy()->subMonth()->endOfMonth();

        $affiliates = Affiliate::where('status', AffiliateStatus::Active)->get();

        foreach ($affiliates as $affiliate) {
            $currentRevenue = $affiliate->conversions()
                ->whereBetween('occurred_at', [$from, $to])
                ->where('status', ConversionStatus::Approved)
                ->sum('total_minor');

            $previousRevenue = $affiliate->conversions()
                ->whereBetween('occurred_at', [$prevFrom, $prevTo])
                ->where('status', ConversionStatus::Approved)
                ->sum('total_minor');

            // Must have minimum previous revenue to qualify
            if ($previousRevenue < ($config['min_previous_revenue'] ?? 50000)) {
                continue;
            }

            $growthPercentage = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;

            if ($growthPercentage >= ($config['min_growth_percentage'] ?? 50)) {
                $bonuses[] = [
                    'affiliate_id' => $affiliate->id,
                    'affiliate_name' => $affiliate->name,
                    'bonus_type' => 'growth',
                    'amount_minor' => $config['bonus_amount'] ?? 7500,
                    'reason' => 'Growth Bonus - ' . round($growthPercentage, 1) . '% growth vs previous month',
                    'metrics' => [
                        'current_revenue' => $currentRevenue,
                        'previous_revenue' => $previousRevenue,
                        'growth_percentage' => round($growthPercentage, 2),
                        'period' => $from->format('Y-m'),
                    ],
                ];
            }
        }

        return $bonuses;
    }
}
