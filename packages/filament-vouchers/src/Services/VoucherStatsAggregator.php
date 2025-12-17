<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Services;

use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use Illuminate\Database\Eloquent\Builder;

final class VoucherStatsAggregator
{
    /**
     * @return array{
     *     total: int,
     *     active: int,
     *     upcoming: int,
     *     expired: int,
     *     manual_redemptions: int,
     *     total_discount_minor: int,
     * }
     */
    public function overview(): array
    {
        return [
            'total' => $this->vouchers()->count(),
            'active' => $this->vouchers()->where('status', VoucherStatus::Active)->count(),
            'upcoming' => $this->vouchers()
                ->where('starts_at', '>', now())
                ->count(),
            'expired' => $this->vouchers()->where('status', VoucherStatus::Expired)->count(),
            'manual_redemptions' => $this->usages()->where('channel', VoucherUsage::CHANNEL_MANUAL)->count(),
            'total_discount_minor' => $this->sumDiscountMinor(),
        ];
    }

    /**
     * @return Builder<Voucher>
     */
    private function vouchers(): Builder
    {
        /** @var Builder<Voucher> $query */
        $query = Voucher::query();

        /** @var Builder<Voucher> $scoped */
        $scoped = OwnerScopedQueries::scopeVoucherLike($query);

        return $scoped;
    }

    /**
     * @return Builder<VoucherUsage>
     */
    private function usages(): Builder
    {
        /** @var Builder<VoucherUsage> $query */
        $query = VoucherUsage::query();

        if (! OwnerScopedQueries::isEnabled()) {
            return $query;
        }

        return $query->whereIn('voucher_id', OwnerScopedQueries::voucherIds());
    }

    private function sumDiscountMinor(): int
    {
        // discount_amount is already stored as integer cents
        $sum = $this->usages()->sum('discount_amount');

        return (int) $sum;
    }
}
