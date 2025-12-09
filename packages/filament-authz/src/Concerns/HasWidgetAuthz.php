<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

/**
 * Trait for automatic authorization enforcement on Filament widgets.
 *
 * Usage:
 *   class RevenueWidget extends Widget
 *   {
 *       use HasWidgetAuthz;
 *   }
 */
trait HasWidgetAuthz
{
    protected static ?string $widgetPermissionKey = null;

    /**
     * @var array<string>
     */
    protected static array $requiredWidgetPermissions = [];

    /**
     * @var array<string>
     */
    protected static array $requiredWidgetRoles = [];

    protected static ?string $widgetTeamScope = null;

    protected static bool $hideWhenUnauthorized = true;

    /**
     * Check if widget can be viewed.
     */
    public static function canView(): bool
    {
        $user = Filament::auth()?->user();

        if (! $user) {
            return false;
        }

        if (static::isSuperAdmin($user)) {
            return true;
        }

        // Role check
        if (! empty(static::$requiredWidgetRoles)) {
            if (! method_exists($user, 'hasAnyRole') || ! $user->hasAnyRole(static::$requiredWidgetRoles)) {
                return false;
            }
        }

        // Team scope
        if (static::$widgetTeamScope !== null) {
            $teamId = static::getCurrentTeamId();
            if ($teamId !== null) {
                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasPermission($user, static::getWidgetPermissionKey());
            }
        }

        // Permission check with aggregation
        if (! empty(static::$requiredWidgetPermissions)) {
            $aggregator = app(PermissionAggregator::class);

            return $aggregator->userHasAllPermissions($user, static::$requiredWidgetPermissions);
        }

        $aggregator = app(PermissionAggregator::class);

        return $aggregator->userHasPermission($user, static::getWidgetPermissionKey());
    }

    /**
     * Get permission key using naming convention.
     */
    public static function getWidgetPermissionKey(): string
    {
        if (static::$widgetPermissionKey !== null) {
            return static::$widgetPermissionKey;
        }

        $name = Str::snake(class_basename(static::class));

        return "widget.{$name}";
    }

    /**
     * Set the widget permission key.
     */
    public static function setWidgetPermissionKey(string $key): void
    {
        static::$widgetPermissionKey = $key;
    }

    /**
     * Require specific permissions.
     *
     * @param  array<string>  $permissions
     */
    public static function requireWidgetPermissions(array $permissions): void
    {
        static::$requiredWidgetPermissions = $permissions;
    }

    /**
     * Require specific roles.
     *
     * @param  array<string>  $roles
     */
    public static function requireWidgetRoles(array $roles): void
    {
        static::$requiredWidgetRoles = $roles;
    }

    /**
     * Scope widget to a team.
     */
    public static function scopeWidgetToTeam(string $teamIdKey = 'team_id'): void
    {
        static::$widgetTeamScope = $teamIdKey;
    }

    /**
     * Configure widget visibility behavior.
     */
    public static function showPlaceholderWhenUnauthorized(): void
    {
        static::$hideWhenUnauthorized = false;
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
