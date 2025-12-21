<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Support\Integrations;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentVouchers\Models\Voucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource;
use Illuminate\Database\Eloquent\Model;

final class VoucherBridge
{
    private bool $available;

    public function __construct()
    {
        $this->available = class_exists(Voucher::class) && class_exists(VoucherResource::class);
    }

    public function warm(): void
    {
        // placeholder for runtime hooks
    }

    public function isAvailable(): bool
    {
        return $this->available && (bool) config('filament-affiliates.integrations.filament_vouchers', true);
    }

    public function resolveUrl(?string $code): ?string
    {
        if (! $this->isAvailable() || ! $code) {
            return null;
        }

        $voucherQuery = VoucherResource::getEloquentQuery()->where('code', $code);

        if ((bool) config('affiliates.owner.enabled', false)) {
            /** @var Model|null $owner */
            $owner = OwnerContext::resolve();
            $voucherQuery->forOwner($owner, false);
        }

        /** @var Voucher|null $voucher */
        $voucher = $voucherQuery->first();

        if (! $voucher) {
            return null;
        }

        if (method_exists(VoucherResource::class, 'canView') && ! VoucherResource::canView($voucher)) {
            return null;
        }

        return VoucherResource::getUrl('view', ['record' => $voucher]);
    }
}
