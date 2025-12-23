<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OwnerScopedQueries
{
    public static function owner(): ?Model
    {
        if (! self::isEnabled()) {
            return null;
        }

        return OwnerContext::resolve();
    }

    public static function isEnabled(): bool
    {
        return (bool) config('vouchers.owner.enabled', false);
    }

    public static function includeGlobal(): bool
    {
        return (bool) config('vouchers.owner.include_global', false);
    }

    /**
     * Enforce tenant boundary owner columns for Filament form writes.
     *
     * When owner mode is enabled and an owner is resolved, we force-create records
     * for that owner (defense-in-depth against crafted requests).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enforceOwnerOnCreate(array $data): array
    {
        if (! self::isEnabled()) {
            return $data;
        }

        $owner = self::owner();

        if (! $owner instanceof Model) {
            $data['owner_type'] = null;
            $data['owner_id'] = null;

            return $data;
        }

        $data['owner_type'] = $owner->getMorphClass();
        $data['owner_id'] = (string) $owner->getKey();

        return $data;
    }

    /**
     * Enforce tenant boundary owner columns for Filament form writes.
     *
     * Updates must never allow changing ownership via request payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function enforceOwnerOnUpdate(Model $record, array $data): array
    {
        if (! self::isEnabled()) {
            return $data;
        }

        $existingOwnerType = $record->getAttribute('owner_type');
        $existingOwnerId = $record->getAttribute('owner_id');

        if ($existingOwnerType === null || $existingOwnerId === null) {
            $data['owner_type'] = null;
            $data['owner_id'] = null;

            return $data;
        }

        $data['owner_type'] = (string) $existingOwnerType;
        $data['owner_id'] = (string) $existingOwnerId;

        return $data;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeVoucherLike(Builder $query): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        $owner = self::owner();
        $includeGlobal = self::includeGlobal();

        return self::scopeOwnerColumns($query, $owner, $includeGlobal);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeOwnerColumns(Builder $query, ?Model $owner, bool $includeGlobal): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        return OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
    }

    /**
     * @return Builder<Voucher>
     */
    public static function vouchers(): Builder
    {
        /** @var Builder<Voucher> $query */
        $query = Voucher::query();

        /** @var Builder<Voucher> $scoped */
        $scoped = self::scopeVoucherLike($query);

        return $scoped;
    }

    /**
     * @return Builder<Voucher>
     */
    public static function voucherIds(): Builder
    {
        return self::vouchers()->select('id');
    }

    /**
     * @return Builder<Voucher>
     */
    public static function voucherCodes(): Builder
    {
        return self::vouchers()->select('code');
    }
}
