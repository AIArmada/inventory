<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Support\Collection;

final class AffiliateReportService
{
    /**
     * @return array<string, mixed>
     */
    public function affiliateSummary(string $affiliateId): array
    {
        /** @var Affiliate|null $affiliate */
        $affiliate = Affiliate::query()->forOwner()->find($affiliateId);

        if (! $affiliate) {
            return [];
        }

        $conversions = AffiliateConversion::query()
            ->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->get();

        $totalCommission = (int) $conversions->sum('commission_minor');
        $totalRevenue = (int) $conversions->sum('total_minor');
        $conversionCount = $conversions->count();
        $ltv = $conversionCount > 0 ? ($totalRevenue / $conversionCount) : 0;

        $utm = $this->aggregateUtm($conversions);
        $attributionCount = (int) AffiliateAttribution::query()
            ->forOwner()
            ->where('affiliate_id', $affiliateId)
            ->count();

        $funnel = [
            'attributions' => $attributionCount,
            'conversions' => $conversionCount,
            'conversion_rate' => $conversionCount > 0 && $attributionCount > 0
                ? round(($conversionCount / $attributionCount) * 100, 2)
                : 0,
        ];

        return [
            'affiliate' => [
                'id' => $affiliate->getKey(),
                'code' => $affiliate->code,
                'name' => $affiliate->name,
            ],
            'totals' => [
                'commission_minor' => $totalCommission,
                'revenue_minor' => $totalRevenue,
                'conversions' => $conversionCount,
                'ltv_minor' => (int) $ltv,
            ],
            'funnel' => $funnel,
            'utm' => $utm,
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function aggregateUtm(Collection $conversions): array
    {
        $sources = [];
        $campaigns = [];

        foreach ($conversions as $conversion) {
            $source = $conversion->metadata['source'] ?? null;
            $campaign = $conversion->metadata['campaign'] ?? null;

            if ($source) {
                $sources[$source] = ($sources[$source] ?? 0) + 1;
            }

            if ($campaign) {
                $campaigns[$campaign] = ($campaigns[$campaign] ?? 0) + 1;
            }
        }

        return [
            'sources' => $sources,
            'campaigns' => $campaigns,
        ];
    }
}
