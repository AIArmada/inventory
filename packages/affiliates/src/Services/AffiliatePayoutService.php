<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AffiliatePayoutService
{
    public function __construct(private readonly WebhookDispatcher $webhooks) {}

    /**
     * @param  array<int, string>  $conversionIds
     * @param  array<string, mixed>  $attributes
     */
    public function createPayout(array $conversionIds, array $attributes = []): AffiliatePayout
    {
        return DB::transaction(function () use ($conversionIds, $attributes): AffiliatePayout {
            /** @var Collection<int, AffiliateConversion> $conversions */
            $conversions = AffiliateConversion::query()
                ->whereIn('id', $conversionIds)
                ->whereNull('affiliate_payout_id')
                ->get();

            $total = (int) $conversions->sum('commission_minor');
            $currency = $attributes['currency'] ?? config('affiliates.payouts.currency', 'USD');
            $reference = $attributes['reference'] ?? $this->generateReference();

            $payout = AffiliatePayout::create([
                'reference' => $reference,
                'status' => $attributes['status'] ?? 'pending',
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
                    ->whereIn('id', $conversions->pluck('id')->all())
                    ->update(['affiliate_payout_id' => $payout->getKey()]);
            }

            return $payout;
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function updateStatus(AffiliatePayout $payout, string $status, ?string $notes = null, array $metadata = []): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $status, $notes, $metadata): AffiliatePayout {
            $from = $payout->status;

            $payout->status = $status;

            if ($status === 'paid' && $payout->paid_at === null) {
                $payout->paid_at = now();
            }

            $payout->save();

            AffiliatePayoutEvent::create([
                'affiliate_payout_id' => $payout->getKey(),
                'from_status' => $from,
                'to_status' => $status,
                'metadata' => $metadata ?: null,
                'notes' => $notes,
            ]);

            $fresh = $payout->refresh();

            $this->webhooks->dispatch('payout', [
                'id' => $fresh->getKey(),
                'reference' => $fresh->reference,
                'status' => $fresh->status,
                'total_minor' => $fresh->total_minor,
                'currency' => $fresh->currency,
            ]);

            return $fresh;
        });
    }

    private function generateReference(): string
    {
        $prefix = (string) config('affiliates.payouts.reference_prefix', 'PO-');

        return $prefix.Str::upper(Str::random(10));
    }
}
