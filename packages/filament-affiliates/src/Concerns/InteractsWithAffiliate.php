<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Concerns;

use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithAffiliate
{
    protected ?Affiliate $affiliate = null;

    /**
     * Get the current user's affiliate.
     */
    public function getAffiliate(): ?Affiliate
    {
        if ($this->affiliate !== null) {
            return $this->affiliate;
        }

        $owner = $this->getAffiliateOwner();

        if (! $owner) {
            return null;
        }

        $this->affiliate = Affiliate::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->first();

        return $this->affiliate;
    }

    /**
     * Get the affiliate owner (typically the authenticated user).
     */
    public function getAffiliateOwner(): ?Model
    {
        return auth()->user();
    }

    /**
     * Check if the current user has an affiliate account.
     */
    public function hasAffiliate(): bool
    {
        return $this->getAffiliate() !== null;
    }

    /**
     * Get conversions for the affiliate.
     *
     * @return Collection<int, \AIArmada\Affiliates\Models\AffiliateConversion>
     */
    public function getConversions(int $limit = 10): Collection
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return new Collection;
        }

        return $affiliate->conversions()
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get payouts for the affiliate.
     *
     * @return Collection<int, \AIArmada\Affiliates\Models\AffiliatePayout>
     */
    public function getPayouts(int $limit = 10): Collection
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return new Collection;
        }

        return $affiliate->payouts()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get total earnings for the affiliate.
     */
    public function getTotalEarnings(): int
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return 0;
        }

        return (int) $affiliate->conversions()
            ->where('status', 'approved')
            ->sum('commission_minor');
    }

    /**
     * Get pending earnings for the affiliate.
     */
    public function getPendingEarnings(): int
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return 0;
        }

        return (int) $affiliate->conversions()
            ->where('status', 'pending')
            ->sum('commission_minor');
    }

    /**
     * Get total clicks/visits for the affiliate.
     */
    public function getTotalClicks(): int
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return 0;
        }

        return (int) $affiliate->attributions()->count();
    }

    /**
     * Get total conversions count for the affiliate.
     */
    public function getTotalConversions(): int
    {
        $affiliate = $this->getAffiliate();

        if (! $affiliate) {
            return 0;
        }

        return (int) $affiliate->conversions()->count();
    }

    /**
     * Format amount for display.
     *
     * Uses the affiliate's currency or falls back to the default currency.
     * Formats with 2 decimal places which is standard for most currencies.
     */
    public function formatAmount(int $amount, ?string $currency = null): string
    {
        $affiliate = $this->getAffiliate();
        $currency = $currency ?? $affiliate?->currency ?? config('affiliates.currency.default', 'USD');

        // Determine decimal places based on currency (most use 2, some use 0)
        $zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'IDR', 'CLP', 'PYG', 'UGX', 'RWF'];
        $decimals = in_array(strtoupper($currency), $zeroDecimalCurrencies, true) ? 0 : 2;

        $divisor = $decimals === 0 ? 1 : 100;

        return $currency.' '.number_format($amount / $divisor, $decimals);
    }
}
