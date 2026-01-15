<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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

    public static function shouldRegisterNavigation(): bool
    {
        return (Filament::auth()?->user() !== null) && parent::shouldRegisterNavigation();
    }

    final public static function getNavigationBadge(): ?string
    {
        if (Filament::auth()?->user() === null) {
            return null;
        }

        $owner = (bool) config('jnt.owner.enabled', false) ? OwnerContext::resolve() : null;
        $ownerKey = $owner instanceof Model
            ? $owner->getMorphClass() . ':' . (string) $owner->getKey()
            : 'none';

        $includeGlobal = (bool) config('jnt.owner.include_global', false);
        $cacheKey = 'filament-jnt:nav-badge:' . static::class . ':' . $ownerKey . ':' . ($includeGlobal ? '1' : '0');

        $count = Cache::remember($cacheKey, CarbonImmutable::now()->addSeconds(30), fn (): int => static::getEloquentQuery()->count());

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

        if (! (bool) config('jnt.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        /** @phpstan-ignore-next-line dynamic scope */
        return $query->forOwner($owner, $includeGlobal);
    }
}
