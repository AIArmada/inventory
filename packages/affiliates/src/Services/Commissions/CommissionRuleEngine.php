<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services\Commissions;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use Illuminate\Support\Collection;

final class CommissionRuleEngine
{
    /**
     * Parameter-keyed cache for applicable rules.
     *
     * @var array<string, Collection<int, AffiliateCommissionRule>>
     */
    private array $rulesCache = [];

    /**
     * Calculate the commission for a conversion.
     *
     * @param  array<string, mixed>  $context
     */
    public function calculate(
        Affiliate $affiliate,
        int $orderAmountMinor,
        array $context = []
    ): CommissionCalculationResult {
        $context = array_merge($context, [
            'affiliate_id' => $affiliate->id,
            'order_amount_minor' => $orderAmountMinor,
        ]);

        // Get applicable rules ordered by priority
        $rules = $this->getApplicableRules($affiliate, $context);

        // Calculate base commission from highest priority matching rule
        $baseCommission = $this->calculateBaseCommission($rules, $orderAmountMinor, $context);

        // Apply volume tier if applicable
        $volumeBonus = $this->calculateVolumeBonus($affiliate, $orderAmountMinor, $context);

        // Apply promotions
        $promotionBonus = $this->calculatePromotionBonus($affiliate, $orderAmountMinor, $context);

        // Apply caps
        $finalCommission = $this->applyCaps(
            $baseCommission + $volumeBonus + $promotionBonus,
            $affiliate,
            $context
        );

        return new CommissionCalculationResult(
            baseCommissionMinor: $baseCommission,
            volumeBonusMinor: $volumeBonus,
            promotionBonusMinor: $promotionBonus,
            finalCommissionMinor: $finalCommission,
            appliedRules: $rules->pluck('id')->all(),
            metadata: [
                'order_amount_minor' => $orderAmountMinor,
                'context' => $context,
            ]
        );
    }

    /**
     * Get all applicable rules for the context.
     *
     * Results are cached for the request lifetime, keyed by affiliate + context.
     *
     * @return Collection<int, AffiliateCommissionRule>
     */
    public function getApplicableRules(Affiliate $affiliate, array $context): Collection
    {
        $cacheKey = $this->buildRulesCacheKey($affiliate, $context);

        if (isset($this->rulesCache[$cacheKey])) {
            return $this->rulesCache[$cacheKey];
        }

        return $this->rulesCache[$cacheKey] = AffiliateCommissionRule::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (AffiliateCommissionRule $rule) => $rule->matches($context));
    }

    /**
     * Clear the rules cache.
     */
    public function clearCache(): void
    {
        $this->rulesCache = [];
    }

    /**
     * Build cache key for rules lookup.
     *
     * @param  array<string, mixed>  $context
     */
    private function buildRulesCacheKey(Affiliate $affiliate, array $context): string
    {
        return md5($affiliate->id . ':' . serialize($context));
    }

    /**
     * @param  Collection<int, AffiliateCommissionRule>  $rules
     */
    private function calculateBaseCommission(Collection $rules, int $orderAmountMinor, array $context): int
    {
        // Use the highest priority matching rule
        $rule = $rules->first();

        if (! $rule) {
            // Fall back to default config rate
            $defaultRate = config('affiliates.commissions.default_rate', 10);

            return (int) round($orderAmountMinor * $defaultRate / 100);
        }

        return $rule->calculateCommission($orderAmountMinor);
    }

    private function calculateVolumeBonus(Affiliate $affiliate, int $orderAmountMinor, array $context): int
    {
        $programId = $context['program_id'] ?? null;

        // Get affiliate's volume for the period
        $volumeQuery = $affiliate->conversions();

        if ($programId) {
            $volumeQuery->whereHas('attribution', function ($q) use ($programId): void {
                $q->where('program_id', $programId);
            });
        }

        $periodVolume = (int) $volumeQuery
            ->where('occurred_at', '>=', now()->startOfMonth())
            ->sum('total_minor');

        // Find applicable volume tier
        $tier = AffiliateVolumeTier::query()
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->where('min_volume_minor', '<=', $periodVolume)
            ->where(function ($q) use ($periodVolume): void {
                $q->whereNull('max_volume_minor')
                    ->orWhere('max_volume_minor', '>=', $periodVolume);
            })
            ->orderBy('min_volume_minor', 'desc')
            ->first();

        if (! $tier) {
            return 0;
        }

        // Calculate bonus based on volume tier rate
        $tierCommission = (int) round($orderAmountMinor * $tier->commission_rate_basis_points / 10000);
        $defaultRate = config('affiliates.commissions.default_rate', 10);
        $defaultCommission = (int) round($orderAmountMinor * $defaultRate / 100);

        // Return the difference as bonus
        return max(0, $tierCommission - $defaultCommission);
    }

    private function calculatePromotionBonus(Affiliate $affiliate, int $baseCommission, array $context): int
    {
        $programId = $context['program_id'] ?? null;

        $promotions = AffiliateCommissionPromotion::query()
            ->active()
            ->when($programId, fn ($q) => $q->where('program_id', $programId))
            ->get()
            ->filter(fn (AffiliateCommissionPromotion $promo) => $promo->appliesToAffiliate($affiliate));

        $totalBonus = 0;

        foreach ($promotions as $promotion) {
            $bonus = $promotion->calculateBonus($baseCommission);
            $totalBonus += $bonus;
            $promotion->incrementUsage();
        }

        return $totalBonus;
    }

    private function applyCaps(int $commission, Affiliate $affiliate, array $context): int
    {
        $minCommission = config('affiliates.commissions.minimum_minor', 0);
        $maxCommission = config('affiliates.commissions.maximum_minor');

        if ($commission < $minCommission) {
            return $minCommission;
        }

        if ($maxCommission !== null && $commission > $maxCommission) {
            return $maxCommission;
        }

        return $commission;
    }
}
