<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
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
        return (bool) config('inventory.owner.include_global', false);
    }

    public static function resolveOwner(): ?Model
    {
        if (! self::isEnabled()) {
            return null;
        }

        return OwnerContext::resolve();
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

        return OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
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

    public static function isCurrentContextGlobalOnly(): bool
    {
        return self::isEnabled() && self::resolveOwner() === null;
    }
}
