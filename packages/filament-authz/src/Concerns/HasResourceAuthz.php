<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait for automatic authorization enforcement on Filament resources.
 *
 * Usage:
 *   class OrderResource extends Resource
 *   {
 *       use HasResourceAuthz;
 *
 *       protected static array $customAbilities = ['approve', 'ship', 'refund'];
 *   }
 */
trait HasResourceAuthz
{
    /**
     * @var array<string>
     */
    protected static array $customAbilities = [];

    protected static ?string $permissionPrefix = null;

    protected static ?string $resourceTeamScope = null;

    protected static bool $restrictToOwned = false;

    protected static string $ownerColumn = 'user_id';

    /**
     * @var array<string>
     */
    protected static array $ownerAbilities = ['view', 'update', 'delete'];

    /**
     * Define custom abilities beyond CRUD.
     *
     * @param  array<string>  $abilities
     */
    public static function abilities(array $abilities): void
    {
        static::$customAbilities = $abilities;
    }

    /**
     * Get all abilities for this resource.
     *
     * @return array<string>
     */
    public static function getAllAbilities(): array
    {
        return array_merge(
            ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'],
            static::$customAbilities
        );
    }

    /**
     * Get permission for specific ability.
     */
    public static function getPermissionFor(string $ability): string
    {
        $prefix = static::$permissionPrefix ?? Str::snake(class_basename(static::getModel()));

        return "{$prefix}.{$ability}";
    }

    /**
     * Check if user can perform ability.
     */
    public static function canPerform(string $ability, ?Model $record = null): bool
    {
        $user = Filament::auth()?->user();

        if (! $user) {
            return false;
        }

        if (static::isSuperAdmin($user)) {
            return true;
        }

        // Owner check for record-specific abilities
        if ($record !== null && static::hasOwnerPermissions()) {
            if (static::isOwner($user, $record)) {
                return in_array($ability, static::$ownerAbilities);
            }
        }

        $aggregator = app(PermissionAggregator::class);

        return $aggregator->userHasPermission(
            $user,
            static::getPermissionFor($ability)
        );
    }

    /**
     * Set the permission prefix for this resource.
     */
    public static function setPermissionPrefix(string $prefix): void
    {
        static::$permissionPrefix = $prefix;
    }

    /**
     * Scope queries to team.
     */
    public static function scopeResourceToTeam(string $teamIdKey = 'team_id'): void
    {
        static::$resourceTeamScope = $teamIdKey;
    }

    /**
     * Restrict queries to owned records.
     */
    public static function restrictToOwned(bool $restrict = true): void
    {
        static::$restrictToOwned = $restrict;
    }

    /**
     * Set the owner column.
     */
    public static function setOwnerColumn(string $column): void
    {
        static::$ownerColumn = $column;
    }

    /**
     * Set abilities that owners can perform on their own records.
     *
     * @param  array<string>  $abilities
     */
    public static function setOwnerAbilities(array $abilities): void
    {
        static::$ownerAbilities = $abilities;
    }

    /**
     * Apply permission-based query scopes.
     * Override in your resource to enable.
     */
    public static function scopeEloquentQueryWithPermissions(Builder $query): Builder
    {
        // Apply team scope if configured
        if (static::$resourceTeamScope !== null) {
            $teamId = static::getCurrentTeamId();
            if ($teamId !== null) {
                $query->where(static::$resourceTeamScope, $teamId);
            }
        }

        // Apply owner-only filter if configured
        if (static::$restrictToOwned) {
            $user = Filament::auth()?->user();
            if ($user !== null) {
                $aggregator = app(PermissionAggregator::class);
                if (! $aggregator->userHasPermission($user, static::getPermissionFor('viewAny'))) {
                    $query->where(static::$ownerColumn, $user->id);
                }
            }
        }

        return $query;
    }

    /**
     * Check if owner permissions are enabled.
     */
    protected static function hasOwnerPermissions(): bool
    {
        return ! empty(static::$ownerAbilities);
    }

    /**
     * Check if user owns the record.
     */
    protected static function isOwner(mixed $user, Model $record): bool
    {
        $ownerColumn = static::$ownerColumn;

        if (! isset($record->{$ownerColumn})) {
            return false;
        }

        return $record->{$ownerColumn} === $user->id;
    }

    /**
     * Check if user is a super admin.
     */
    protected static function isSuperAdmin(mixed $user): bool
    {
        $superAdminRole = config('filament-authz.super_admin_role', 'super_admin');

        return method_exists($user, 'hasRole') && $user->hasRole($superAdminRole);
    }

    /**
     * Get the current team ID.
     */
    protected static function getCurrentTeamId(): mixed
    {
        $tenant = Filament::getTenant();

        return $tenant?->id ?? null;
    }
}
