<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;

final class OwnerScopedQuery
{
    /**
     * Apply the current owner boundary through an Affiliate relationship.
     *
     * This enforces the monorepo contract that tenant boundaries are server-side.
     * Global rows are included only when `affiliates.owner.include_global` is enabled.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function throughAffiliate(Builder $query, string $relation = 'affiliate'): Builder
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('affiliates.owner.include_global', false);

        return $query->whereHas($relation, function (Builder $affiliateQuery) use ($owner, $includeGlobal): void {
            $scoped = $affiliateQuery->withoutGlobalScope(OwnerScope::class);
            OwnerQuery::applyToEloquentBuilder($scoped, $owner, $includeGlobal);
        });
    }
}
