<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

abstract class BaseChipResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    abstract protected static function navigationSortKey(): string;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (method_exists($query->getModel(), 'scopeForOwner')) {
            /** @var Builder $query */
            $query = $query->forOwner(); // @phpstan-ignore method.notFound
        }

        return $query;
    }

    final public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-chip.navigation.group');
    }

    final public static function getNavigationSort(): ?int
    {
        return config('filament-chip.resources.navigation_sort.' . static::navigationSortKey());
    }

    final public static function getNavigationBadge(): ?string
    {
        $count = (int) static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    final public static function getNavigationBadgeColor(): ?string
    {
        return config('filament-chip.navigation.badge_color', 'primary');
    }

    protected static function pollingInterval(): string
    {
        return (string) config('filament-chip.polling_interval', '45s');
    }
}
