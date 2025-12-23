<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class OwnerScope
{
    public static function isEnabled(): bool
    {
        return (bool) config('products.features.owner.enabled', true);
    }

    public static function includeGlobal(): bool
    {
        return (bool) config('products.features.owner.include_global', false);
    }

    public static function resolveOwner(): ?Model
    {
        if (! self::isEnabled()) {
            return null;
        }

        return OwnerContext::resolve();
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    public static function allowedIds(string $modelClass, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn (mixed $id): bool => is_string($id) && $id !== '')));

        if ($ids === []) {
            return [];
        }

        /** @var Builder<Model> $query */
        $query = $modelClass::query();

        if (self::isEnabled() && method_exists($query->getModel(), 'scopeForOwner')) {
            $scoped = call_user_func([$query->getModel(), 'scopeForOwner'], $query, self::resolveOwner(), self::includeGlobal());
            if ($scoped instanceof Builder) {
                $query = $scoped;
            }
        }

        /** @var array<int, string> $allowed */
        $allowed = $query->whereKey($ids)->pluck($query->getModel()->getKeyName())->all();

        return $allowed;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>|null  $ids
     * @return array<int, string>
     */
    public static function ensureAllowed(string $field, string $modelClass, ?array $ids): array
    {
        $ids = $ids ?? [];

        if ($ids === []) {
            return [];
        }

        $allowed = self::allowedIds($modelClass, $ids);

        if (count($allowed) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                $field => ['One or more selected records are invalid for the current owner scope.'],
            ]);
        }

        return $allowed;
    }
}
