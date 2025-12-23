<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class CashierOwnerScope
{
    /**
     * @var array<string, bool>
     */
    private static array $hasColumnCache = [];

    /**
     * Apply owner scoping to a query.
     *
     * Strategy:
     * - If owner scoping primitives exist but no owner context is available, fail closed.
     * - If the model supports `scopeForOwner`, use it.
     * - Else, if the model has a `user_id` or `billable_id` column and the billable model supports owner scoping,
     *   scope via a subquery of billable IDs for the current owner.
     * - Else, fail closed when an owner context exists.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?Model $owner = null, ?bool $includeGlobal = null): Builder
    {
        $owner ??= self::resolveOwner();

        $requiresOwnerContext = self::requiresOwnerContext();

        if ($owner === null) {
            return $requiresOwnerContext ? self::empty($query) : $query;
        }

        // If the configured billable model does not support owner scoping, we cannot safely
        // enforce tenant boundaries here. Treat as non-owner mode and leave the query unchanged.
        if (! $requiresOwnerContext) {
            return $query;
        }

        $includeGlobal ??= false;
        $model = $query->getModel();

        if (method_exists($model, 'scopeForOwner')) {
            /** @phpstan-ignore-next-line dynamic local scope */
            return $query->forOwner($owner, $includeGlobal);
        }

        if (self::modelHasColumn($model, 'user_id')) {
            return self::applyViaBillableIdSubquery($query, 'user_id', $owner, $includeGlobal);
        }

        if (self::modelHasColumn($model, 'billable_id')) {
            return self::applyViaBillableIdSubquery($query, 'billable_id', $owner, $includeGlobal);
        }

        return self::empty($query);
    }

    private static function resolveOwner(): ?Model
    {
        return OwnerContext::resolve();
    }

    private static function requiresOwnerContext(): bool
    {
        $billableModel = (string) config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel)) {
            return false;
        }

        $billable = new $billableModel;

        return method_exists($billable, 'scopeForOwner');
    }

    private static function modelHasColumn(Model $model, string $column): bool
    {
        $table = $model->getTable();
        $connection = $model->getConnectionName() ?? config('database.default');
        $cacheKey = $connection . ':' . $table . ':' . $column;

        if (array_key_exists($cacheKey, self::$hasColumnCache)) {
            return self::$hasColumnCache[$cacheKey];
        }

        return self::$hasColumnCache[$cacheKey] = Schema::connection($connection)->hasColumn($table, $column);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function applyViaBillableIdSubquery(Builder $query, string $foreignKey, Model $owner, bool $includeGlobal): Builder
    {
        $billableModel = (string) config('cashier.models.billable', 'App\\Models\\User');

        if (! class_exists($billableModel)) {
            return self::empty($query);
        }

        $billable = new $billableModel;

        if (! method_exists($billable, 'scopeForOwner')) {
            return self::empty($query);
        }

        $billableKeyName = $billable->getKeyName();

        /** @var Builder<Model> $billables */
        $billables = $billableModel::query();

        /** @phpstan-ignore-next-line dynamic local scope */
        $billables = $billables->forOwner($owner, $includeGlobal)->select($billableKeyName);

        return $query->whereIn($foreignKey, $billables);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function empty(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
