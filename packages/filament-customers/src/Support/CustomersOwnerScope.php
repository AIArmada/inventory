<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CustomersOwnerScope
{
    public static function resolveOwner(): ?Model
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    /**
     * Apply owner scoping to a query.
     *
     * Filament surfaces default to owner-only (no global rows) for security.
     * To include global rows (owner=null), explicitly pass $includeGlobal = true.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  bool  $includeGlobal  When true, includes rows with owner=null (global rows)
     * @return Builder<TModel>
     */
    public static function applyToOwnedQuery(Builder $query, bool $includeGlobal = false): Builder
    {
        if (! (bool) config('customers.features.owner.enabled', false)) {
            return $query;
        }

        $owner = self::resolveOwner();

        return OwnerQuery::applyToEloquentBuilder($query, $owner, $includeGlobal);
    }
}
