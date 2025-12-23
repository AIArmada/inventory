<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Exceptions\ManualRedemptionNotAllowedException;
use AIArmada\Vouchers\Exceptions\VoucherNotFoundException;
use AIArmada\Vouchers\Exceptions\VoucherUsageLimitException;
use AIArmada\Vouchers\Models\Voucher as VoucherModel;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\Models\VoucherWallet;
use Akaunting\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function __construct(
        protected VoucherValidator $validator
    ) {}

    public function find(string $code): ?VoucherData
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->first();

        return $voucher ? VoucherData::fromModel($voucher) : null;
    }

    public function findOrFail(string $code): VoucherData
    {
        $voucher = $this->find($code);

        if (! $voucher) {
            throw VoucherNotFoundException::withCode($code);
        }

        return $voucher;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): VoucherData
    {
        /** @var string $code */
        $code = $data['code'];
        $data['code'] = $this->normalizeCode($code);
        $data['status'] ??= VoucherStatus::Active;

        if (
            config('vouchers.owner.enabled', false)
            && config('vouchers.owner.auto_assign_on_create', true)
            && ! isset($data['owner_type'], $data['owner_id'])
        ) {
            $owner = $this->resolveOwner();

            if ($owner) {
                $data['owner_type'] = $owner->getMorphClass();
                $data['owner_id'] = $owner->getKey();
            }
        }

        $voucher = VoucherModel::create($data);

        return VoucherData::fromModel($voucher);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $code, array $data): VoucherData
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        if (isset($data['code'])) {
            /** @var string $newCode */
            $newCode = $data['code'];
            $data['code'] = $this->normalizeCode($newCode);
        }

        $voucher->update($data);

        /** @var VoucherModel $freshVoucher */
        $freshVoucher = $voucher->fresh();

        return VoucherData::fromModel($freshVoucher);
    }

    public function delete(string $code): bool
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->first();

        return $voucher !== null && $voucher->delete();
    }

    public function validate(string $code, mixed $cart): VoucherValidationResult
    {
        return $this->validator->validate($code, $cart);
    }

    public function isValid(string $code): bool
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->first();

        /** @var VoucherModel|null $voucher */
        if (! $voucher) {
            return false;
        }

        return $voucher->isActive()
            && $voucher->hasStarted()
            && ! $voucher->isExpired()
            && $voucher->hasUsageLimitRemaining();
    }

    public function canBeUsedBy(string $code, ?Model $user = null): bool
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return false;
        }

        if (! $voucher->usage_limit_per_user || ! $user) {
            return true;
        }

        $usageCount = VoucherUsage::where('voucher_id', $voucher->id)
            ->where('redeemed_by_type', $user->getMorphClass())
            ->where('redeemed_by_id', $user->getKey())
            ->count();

        return $usageCount < $voucher->usage_limit_per_user;
    }

    public function getRemainingUses(string $code): int
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return 0;
        }

        return $voucher->getRemainingUses() ?? PHP_INT_MAX;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordUsage(
        string $code,
        Money $discountAmount,
        ?string $channel = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null,
        ?VoucherModel $voucherModel = null
    ): void {
        $voucher = $voucherModel ?? $this->query()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        DB::transaction(function () use (
            $voucher,
            $discountAmount,
            $channel,
            $metadata,
            $redeemedBy,
            $notes
        ): void {
            /** @var VoucherModel $lockedVoucher */
            $lockedVoucher = VoucherModel::query()
                ->whereKey($voucher->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedVoucher->usage_limit !== null) {
                $currentUses = VoucherUsage::where('voucher_id', $lockedVoucher->id)->count();
                if ($currentUses >= $lockedVoucher->usage_limit) {
                    throw VoucherUsageLimitException::globalLimit($lockedVoucher->code);
                }
            } else {
                $currentUses = null;
            }

            if ($redeemedBy !== null && $lockedVoucher->usage_limit_per_user) {
                $currentUserUses = VoucherUsage::where('voucher_id', $lockedVoucher->id)
                    ->where('redeemed_by_type', $redeemedBy->getMorphClass())
                    ->where('redeemed_by_id', $redeemedBy->getKey())
                    ->count();

                if ($currentUserUses >= $lockedVoucher->usage_limit_per_user) {
                    throw VoucherUsageLimitException::userLimit($lockedVoucher->code);
                }
            }

            $payload = [
                'voucher_id' => $lockedVoucher->id,
                'discount_amount' => $discountAmount->getAmount(),
                'currency' => $discountAmount->getCurrency()->getCurrency(),
                'channel' => $channel,
                'metadata' => $metadata,
                'target_definition' => $lockedVoucher->target_definition,
                'notes' => $notes,
                'used_at' => now(),
            ];

            if ($redeemedBy) {
                $payload['redeemed_by_type'] = $redeemedBy->getMorphClass();
                $payload['redeemed_by_id'] = $redeemedBy->getKey();
            }

            VoucherUsage::create($payload);

            if ($lockedVoucher->usage_limit !== null && $currentUses !== null && ($currentUses + 1) >= $lockedVoucher->usage_limit) {
                $lockedVoucher->update(['status' => VoucherStatus::Depleted]);
            }
        });
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function redeemManually(
        string $code,
        Money $discountAmount,
        ?string $reference = null,
        ?array $metadata = null,
        ?Model $redeemedBy = null,
        ?string $notes = null
    ): void {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        if (
            config('vouchers.redemption.manual_requires_flag', true)
            && ! $voucher->allowsManualRedemption()
        ) {
            throw ManualRedemptionNotAllowedException::forVoucher($voucher->code);
        }

        /** @var string $channel */
        $channel = config('vouchers.redemption.manual_channel', 'manual');

        $this->recordUsage(
            code: $code,
            discountAmount: $discountAmount,
            channel: $channel,
            metadata: array_merge($metadata ?? [], ['reference' => $reference]),
            redeemedBy: $redeemedBy,
            notes: $notes,
            voucherModel: $voucher
        );
    }

    /**
     * @return EloquentCollection<int, VoucherUsage>
     */
    public function getUsageHistory(string $code): EloquentCollection
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->first();

        if (! $voucher) {
            return new EloquentCollection;
        }

        /** @var EloquentCollection<int, VoucherUsage> $result */
        $result = $voucher->usages()
            ->latest('used_at')
            ->get();

        return $result;
    }

    /**
     * Add a voucher to the owner's wallet.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function addToWallet(string $code, Model $holder, ?array $metadata = null): VoucherWallet
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        return VoucherWallet::create([
            'voucher_id' => $voucher->id,
            'holder_type' => $holder->getMorphClass(),
            'holder_id' => $holder->getKey(),
            'owner_type' => $voucher->owner_type,
            'owner_id' => $voucher->owner_id,
            'is_claimed' => true,
            'claimed_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Remove a voucher from the owner's wallet (if not redeemed).
     */
    public function removeFromWallet(string $code, Model $holder): bool
    {
        $voucher = $this->query()
            ->where('code', $this->normalizeCode($code))
            ->firstOrFail();

        /** @var VoucherModel $voucher */
        return VoucherWallet::where('voucher_id', $voucher->id)
            ->where('holder_type', $holder->getMorphClass())
            ->where('holder_id', $holder->getKey())
            ->where('is_redeemed', false)
            ->delete() > 0;
    }

    protected function normalizeCode(string $code): string
    {
        if (config('vouchers.code.auto_uppercase', true)) {
            return mb_strtoupper(mb_trim($code));
        }

        return mb_trim($code);
    }

    /**
     * @return Builder<VoucherModel>
     */
    protected function query(): Builder
    {
        return VoucherModel::query()->forOwner(
            $this->resolveOwner(),
            $this->shouldIncludeGlobal()
        );
    }

    protected function resolveOwner(): ?Model
    {
        if (! config('vouchers.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    protected function shouldIncludeGlobal(): bool
    {
        return (bool) config('vouchers.owner.include_global', false);
    }
}
