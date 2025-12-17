<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Enums\PayoutStatus;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new payout from affiliate conversions.
 */
final class CreatePayout
{
    use AsAction;

    /**
     * Create a payout from the given conversion IDs.
     *
     * @param  array<int, string>  $conversionIds
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $conversionIds, array $attributes = []): AffiliatePayout
    {
        return DB::transaction(function () use ($conversionIds, $attributes): AffiliatePayout {
            /** @var Collection<int, AffiliateConversion> $conversions */
            $conversions = AffiliateConversion::query()
                ->forOwner()
                ->whereIn('id', $conversionIds)
                ->whereNull('affiliate_payout_id')
                ->get();

            $total = (int) $conversions->sum('commission_minor');
            $currency = $attributes['currency'] ?? config('affiliates.payouts.currency', 'USD');
            $reference = $attributes['reference'] ?? $this->generateReference();

            // Handle status - accept either enum or string
            $status = $attributes['status'] ?? PayoutStatus::Pending;
            if (is_string($status)) {
                $status = PayoutStatus::tryFrom($status) ?? PayoutStatus::Pending;
            }

            $payout = AffiliatePayout::create([
                'reference' => $reference,
                'status' => $status,
                'total_minor' => $total,
                'conversion_count' => $conversions->count(),
                'currency' => $currency,
                'metadata' => $attributes['metadata'] ?? null,
                'owner_type' => $attributes['owner_type'] ?? null,
                'owner_id' => $attributes['owner_id'] ?? null,
                'scheduled_at' => $attributes['scheduled_at'] ?? null,
                'paid_at' => $attributes['paid_at'] ?? null,
            ]);

            if ($conversions->isNotEmpty()) {
                AffiliateConversion::query()
                    ->forOwner()
                    ->whereIn('id', $conversions->pluck('id')->all())
                    ->update(['affiliate_payout_id' => $payout->getKey()]);
            }

            return $payout;
        });
    }

    private function generateReference(): string
    {
        $prefix = (string) config('affiliates.payouts.reference_prefix', 'PO-');

        return $prefix . Str::upper(Str::random(10));
    }
}
