<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
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

        /** @var OwnerResolverInterface $resolver */
        $resolver = app(OwnerResolverInterface::class);

        return $resolver->resolve();
    }

    public static function isEnabled(): bool
    {
        return (bool) config('vouchers.owner.enabled', false);
    }

    public static function includeGlobal(): bool
    {
        return (bool) config('vouchers.owner.include_global', true);
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

        /** @var TModel $model */
        $model = $query->getModel();

        if (method_exists($model, 'scopeForOwner')) {
            /** @var callable $callable */
            $callable = [$model, 'scopeForOwner'];

            /** @var Builder<TModel> $scoped */
            $scoped = $callable($query, $owner, $includeGlobal);

            return $scoped;
        }

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

        if ($owner === null) {
            return $includeGlobal
                ? $query->whereNull('owner_type')->whereNull('owner_id')
                : $query;
        }

        return $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
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
