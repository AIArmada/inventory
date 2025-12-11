<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\RankQualificationReason;
use AIArmada\Affiliates\Events\AffiliateRankChanged;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;

final class RankQualificationService
{
    /**
     * Parameter-keyed cache for calculated metrics.
     *
     * @var array<string, array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int}>
     */
    private array $metricsCache = [];

    public function __construct(
        private readonly NetworkService $networkService,
        private readonly Dispatcher $events
    ) {}

    /**
     * Evaluate and determine the highest qualifying rank for an affiliate.
     */
    public function evaluate(Affiliate $affiliate): ?AffiliateRank
    {
        $metrics = $this->calculateMetrics($affiliate);

        return AffiliateRank::query()
            ->orderBy('level', 'asc')
            ->get()
            ->first(fn (AffiliateRank $rank) => $rank->meetsQualification(
                $affiliate,
                $metrics['personal_sales'],
                $metrics['team_sales'],
                $metrics['active_downlines']
            ));
    }

    /**
     * Process rank upgrades for all affiliates.
     */
    public function processAllRankUpgrades(): int
    {
        $upgraded = 0;

        Affiliate::query()
            ->with('rank')
            ->chunk(100, function ($affiliates) use (&$upgraded): void {
                foreach ($affiliates as $affiliate) {
                    $newRank = $this->evaluate($affiliate);

                    if ($this->shouldChangeRank($affiliate, $newRank)) {
                        $this->changeRank($affiliate, $newRank, RankQualificationReason::Qualified);
                        $upgraded++;
                    }
                }
            });

        return $upgraded;
    }

    /**
     * Evaluate and promote/demote affiliate to appropriate rank.
     */
    public function processRankChange(Affiliate $affiliate): void
    {
        $newRank = $this->evaluate($affiliate);

        if ($this->shouldChangeRank($affiliate, $newRank)) {
            $this->changeRank($affiliate, $newRank, RankQualificationReason::Qualified);
        }
    }

    /**
     * Process rank changes for a batch of affiliates.
     *
     * @param  iterable<Affiliate>  $affiliates
     */
    public function processBatch(iterable $affiliates): void
    {
        foreach ($affiliates as $affiliate) {
            $this->processRankChange($affiliate);
        }
    }

    /**
     * Manually assign a rank to an affiliate.
     */
    public function assignRank(Affiliate $affiliate, ?AffiliateRank $rank): void
    {
        $this->changeRank($affiliate, $rank, RankQualificationReason::Manual);
    }

    /**
     * Calculate qualification metrics for an affiliate.
     *
     * Results are cached for the request lifetime, keyed by affiliate ID + period.
     *
     * @return array{personal_sales: int, team_sales: int, active_downlines: int, lifetime_value: int}
     */
    public function calculateMetrics(Affiliate $affiliate, ?Carbon $from = null): array
    {
        $from ??= now()->subDays(30);
        $cacheKey = $this->buildMetricsCacheKey($affiliate, $from);

        if (isset($this->metricsCache[$cacheKey])) {
            return $this->metricsCache[$cacheKey];
        }

        $personalSales = $affiliate->conversions()
            ->where('occurred_at', '>=', $from)
            ->sum('total_minor');

        $teamSales = $this->networkService->getTeamSales($affiliate, $from);

        $activeDownlines = $this->networkService->getActiveDownlineCount($affiliate);

        $lifetimeValue = $affiliate->conversions()->sum('total_minor');

        return $this->metricsCache[$cacheKey] = [
            'personal_sales' => (int) $personalSales,
            'team_sales' => (int) $teamSales,
            'active_downlines' => $activeDownlines,
            'lifetime_value' => (int) $lifetimeValue,
        ];
    }

    /**
     * Clear the metrics cache.
     */
    public function clearCache(): void
    {
        $this->metricsCache = [];
    }

    /**
     * Build cache key for metrics lookup.
     */
    private function buildMetricsCacheKey(Affiliate $affiliate, Carbon $from): string
    {
        return $affiliate->id . ':' . $from->toDateString();
    }

    private function shouldChangeRank(Affiliate $affiliate, ?AffiliateRank $newRank): bool
    {
        if ($affiliate->rank_id === null && $newRank === null) {
            return false;
        }

        if ($affiliate->rank_id === null && $newRank !== null) {
            return true;
        }

        if ($affiliate->rank_id !== null && $newRank === null) {
            return true;
        }

        return $affiliate->rank_id !== $newRank?->id;
    }

    private function determineReason(Affiliate $affiliate, ?AffiliateRank $newRank): RankQualificationReason
    {
        if ($affiliate->rank === null) {
            return RankQualificationReason::Initial;
        }

        if ($newRank === null) {
            return RankQualificationReason::Demoted;
        }

        return $newRank->isHigherThan($affiliate->rank)
            ? RankQualificationReason::Qualified
            : RankQualificationReason::Demoted;
    }

    private function changeRank(Affiliate $affiliate, ?AffiliateRank $newRank, RankQualificationReason $reason): void
    {
        $oldRank = $affiliate->rank;

        AffiliateRankHistory::create([
            'affiliate_id' => $affiliate->id,
            'from_rank_id' => $oldRank?->id,
            'to_rank_id' => $newRank?->id,
            'reason' => $reason,
            'qualified_at' => now(),
        ]);

        $affiliate->update(['rank_id' => $newRank?->id]);

        $this->events->dispatch(new AffiliateRankChanged(
            affiliate: $affiliate,
            fromRank: $oldRank,
            toRank: $newRank,
            reason: $reason
        ));
    }
}
