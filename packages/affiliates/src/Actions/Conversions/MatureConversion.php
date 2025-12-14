<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Enums\ConversionStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Mature a single conversion and move commission to available balance.
 */
final class MatureConversion
{
    use AsAction;

    private int $maturityDays;

    public function __construct()
    {
        $this->maturityDays = config('affiliates.payouts.maturity_days', 30);
    }

    /**
     * Process maturity for a single conversion.
     */
    public function handle(AffiliateConversion $conversion): bool
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
