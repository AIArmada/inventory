<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CashierChipOwnerScope
{
    /**
     * Apply owner scoping to a query when the model supports it.
     *
     * Fail-closed when an owner resolver is bound but the model does not support
     * owner scoping (prevents accidental cross-tenant leaks).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?Model $owner = null, ?bool $includeGlobal = null): Builder
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', true)) {
            return $query;
        }

        $model = $query->getModel();
        $owner ??= self::resolveOwner();
        $includeGlobal ??= (bool) config('cashier-chip.features.owner.include_global', false);

        if ($owner === null) {
            return $query->whereKey([]);
        }

        if (! method_exists($model, 'scopeForOwner')) {
            return $query->whereKey([]);
        }

        $ownerTypeColumn = 'owner_type';
        $ownerIdColumn = 'owner_id';

        $modelClass = $model::class;

        if (method_exists($modelClass, 'ownerScopeConfig')) {
            /** @var \AIArmada\CommerceSupport\Support\OwnerScopeConfig $config */
            $config = $modelClass::ownerScopeConfig();
            $ownerTypeColumn = $config->ownerTypeColumn;
            $ownerIdColumn = $config->ownerIdColumn;
        }

        return OwnerQuery::applyToEloquentBuilder(
            $query->withoutGlobalScope(OwnerScope::class),
            $owner,
            $includeGlobal,
            $ownerTypeColumn,
            $ownerIdColumn,
        );
    }

    public static function resolveOwner(): ?Model
    {
        if (! (bool) config('cashier-chip.features.owner.enabled', true)) {
            return null;
        }

        return OwnerContext::resolve();
    }
}
