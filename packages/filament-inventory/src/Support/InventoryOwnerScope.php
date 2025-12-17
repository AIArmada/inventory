<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class InventoryOwnerScope
{
    public static function isEnabled(): bool
    {
        return (bool) config('inventory.owner.enabled', false);
    }

    public static function includeGlobal(): bool
    {
        return (bool) config('inventory.owner.include_global', true);
    }

    public static function resolveOwner(): ?Model
    {
        if (! self::isEnabled()) {
            return null;
        }

        if (! app()->bound(OwnerResolverInterface::class)) {
            return null;
        }

        /** @var OwnerResolverInterface $resolver */
        $resolver = app(OwnerResolverInterface::class);

        return $resolver->resolve();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyToLocationQuery(Builder $query): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        $owner = self::resolveOwner();
        $includeGlobal = self::includeGlobal();

        if ($owner === null) {
            return $query->whereNull('owner_type')->whereNull('owner_id');
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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyToQueryByLocationRelation(Builder $query, string $relation = 'location'): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $locationQuery): Builder => self::applyToLocationQuery($locationQuery));
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function applyToMovementQuery(Builder $query): Builder
    {
        if (! self::isEnabled()) {
            return $query;
        }

        return $query->where(function (Builder $builder): void {
            $builder
                ->whereHas('fromLocation', fn (Builder $locationQuery): Builder => self::applyToLocationQuery($locationQuery))
                ->orWhereHas('toLocation', fn (Builder $locationQuery): Builder => self::applyToLocationQuery($locationQuery));
        });
    }

    public static function cacheKeySuffix(): string
    {
        if (! self::isEnabled()) {
            return 'owner=disabled';
        }

        $owner = self::resolveOwner();

        $ownerKey = $owner === null
            ? 'null'
            : $owner->getMorphClass() . ':' . $owner->getKey();

        return 'owner=' . $ownerKey . '|includeGlobal=' . (self::includeGlobal() ? '1' : '0');
    }
}
