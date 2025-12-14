<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Support\Carbon;

/**
 * Service for managing commission maturity and release to available balance.
 */
final class CommissionMaturityService
{
    /**
     * Default maturity period in days.
     */
    private int $maturityDays;

    public function __construct()
    {
        $this->maturityDays = config('affiliates.payouts.maturity_days', 30);
    }

    /**
     * Process maturity for all pending commissions.
     */
    public function processMaturity(): int
    {
        $matured = 0;

        $conversions = AffiliateConversion::query()
            ->where('status', ConversionStatus::Qualified)
            ->where('occurred_at', '<=', now()->subDays($this->maturityDays))
            ->with('affiliate')
            ->get();

        foreach ($conversions as $conversion) {
            if ($this->matureConversion($conversion)) {
                $matured++;
            }
        }

        return $matured;
    }

    /**
     * Mature a single conversion and move commission to available balance.
     */
    public function matureConversion(AffiliateConversion $conversion): bool
    {
        if ($conversion->status !== ConversionStatus::Qualified) {
            return false;
        }

        $maturityDate = $conversion->occurred_at->addDays($this->maturityDays);

        if ($maturityDate->isFuture()) {
            return false;
        }

        $affiliate = $conversion->affiliate;
        $balance = $this->getOrCreateBalance($affiliate);

        // Move from holding to available
        $balance->holding_minor -= $conversion->commission_minor;
        $balance->available_minor += $conversion->commission_minor;
        $balance->save();

        // Update conversion status
        $conversion->update([
            'status' => ConversionStatus::Approved,
            'metadata' => array_merge($conversion->metadata ?? [], [
                'matured_at' => now()->toIso8601String(),
            ]),
        ]);

        return true;
    }

    /**
     * Get the maturity date for a conversion.
     */
    public function getMaturityDate(AffiliateConversion $conversion): Carbon
    {
        return $conversion->occurred_at->addDays($this->maturityDays);
    }

    /**
     * Check if a conversion is mature.
     */
    public function isMature(AffiliateConversion $conversion): bool
    {
        return $this->getMaturityDate($conversion)->isPast();
    }

    /**
     * Get pending maturity amount for an affiliate.
     */
    public function getPendingMaturity(Affiliate $affiliate): int
    {
        return (int) $affiliate->conversions()
            ->where('status', ConversionStatus::Qualified)
            ->sum('commission_minor');
    }

    /**
     * Get conversions maturing within a period.
     */
    public function getMaturingWithin(Affiliate $affiliate, int $days): array
    {
        $cutoffDate = now()->subDays($this->maturityDays - $days);

        return $affiliate->conversions()
            ->where('status', ConversionStatus::Qualified)
            ->where('occurred_at', '>=', $cutoffDate)
            ->get()
            ->map(fn (AffiliateConversion $c) => [
                'id' => $c->id,
                'commission_minor' => $c->commission_minor,
                'occurred_at' => $c->occurred_at->toIso8601String(),
                'matures_at' => $this->getMaturityDate($c)->toIso8601String(),
                'days_remaining' => max(0, now()->diffInDays($this->getMaturityDate($c), false)),
            ])
            ->all();
    }

    private function getOrCreateBalance(Affiliate $affiliate): AffiliateBalance
    {
        return $affiliate->balance ?? AffiliateBalance::create([
            'affiliate_id' => $affiliate->id,
            'available_minor' => 0,
            'holding_minor' => 0,
            'lifetime_earnings_minor' => 0,
            'minimum_payout_minor' => config('affiliates.payouts.minimum_amount', 5000),
            'currency' => $affiliate->currency,
        ]);
    }
}
