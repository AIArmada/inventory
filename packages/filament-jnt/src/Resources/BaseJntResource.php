<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

abstract class BaseJntResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    abstract protected static function navigationSortKey(): string;

    final public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-jnt.navigation_group');
    }

    final public static function getNavigationSort(): ?int
    {
        return config('filament-jnt.resources.navigation_sort.' . static::navigationSortKey());
    }

    final public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    final public static function getNavigationBadgeColor(): ?string
    {
        return config('filament-jnt.navigation_badge_color', 'primary');
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-jnt.polling_interval', '30s');
    }

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = parent::getEloquentQuery();

        $model = $query->getModel();

        if (! method_exists($model, 'scopeForOwner')) {
            return $query;
        }

        $owner = null;
        if (app()->bound(OwnerResolverInterface::class)) {
            $owner = app(OwnerResolverInterface::class)->resolve();
        }

        /** @var bool $includeGlobal */
        $includeGlobal = (bool) config('jnt.owner.include_global', true);

        /** @var Builder<Model> $scoped */
        $scoped = call_user_func([$model, 'scopeForOwner'], $query, $owner, $includeGlobal);

        return $scoped;
    }
}
