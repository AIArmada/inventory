<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Concerns;

use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Facades\Filament;
use Illuminate\Support\Str;

/**
 * Trait for automatic authorization enforcement on Filament pages.
 *
 * Usage:
 *   class SettingsPage extends Page
 *   {
 *       use HasPageAuthz;
 *   }
 */
trait HasPageAuthz
{
    protected static ?string $pagePermissionKey = null;

    /**
     * @var array<string>
     */
    protected static array $requiredPagePermissions = [];

    /**
     * @var array<string>
     */
    protected static array $requiredPageRoles = [];

    protected static ?string $teamPermissionScope = null;

    /**
     * Check if the page should appear in navigation.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    /**
     * Comprehensive access check with multiple strategies.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()?->user();

        if (! $user) {
            return false;
        }

        // Super admin bypass
        if (static::isSuperAdmin($user)) {
            return true;
        }

        // Role requirements
        if (! empty(static::$requiredPageRoles)) {
            if (! method_exists($user, 'hasAnyRole') || ! $user->hasAnyRole(static::$requiredPageRoles)) {
                return false;
            }
        }

        // Team scope check
        if (static::$teamPermissionScope !== null) {
            $team = static::getTeamFromContext();
            if ($team !== null) {
                $aggregator = app(PermissionAggregator::class);

                return $aggregator->userHasPermission($user, static::getPagePermissionKey());
            }
        }

        // Multiple permissions required
        if (! empty(static::$requiredPagePermissions)) {
            $aggregator = app(PermissionAggregator::class);

            return $aggregator->userHasAllPermissions($user, static::$requiredPagePermissions);
        }

        // Standard permission check with hierarchy
        $aggregator = app(PermissionAggregator::class);

        return $aggregator->userHasPermission($user, static::getPagePermissionKey());
    }

    /**
     * Get the permission key for this page.
     */
    public static function getPagePermissionKey(): string
    {
        if (static::$pagePermissionKey !== null) {
            return static::$pagePermissionKey;
        }

        $slug = method_exists(static::class, 'getSlug')
            ? static::getSlug()
            : Str::kebab(class_basename(static::class));

        return "page.{$slug}";
    }

    /**
     * Configure permission key.
     */
    public static function setPagePermissionKey(string $key): void
    {
        static::$pagePermissionKey = $key;
    }

    /**
     * Require specific permissions.
     *
     * @param  array<string>  $permissions
     */
    public static function requirePermissions(array $permissions): void
    {
        static::$requiredPagePermissions = $permissions;
    }

    /**
     * Require specific roles.
     *
     * @param  array<string>  $roles
     */
    public static function requireRoles(array $roles): void
    {
        static::$requiredPageRoles = $roles;
    }

    /**
     * Scope to team permissions.
     */
    public static function scopeToTeam(string $teamIdKey = 'team_id'): void
    {
        static::$teamPermissionScope = $teamIdKey;
    }

    /**
     * Get page title with permission badge (for debugging).
     */
    public function getTitleWithPermissionDebug(): string
    {
        if (! app()->isLocal()) {
            return $this->getTitle();
        }

        return $this->getTitle()." [{$this->getPagePermissionKey()}]";
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
     * Get team from the current context.
     */
    protected static function getTeamFromContext(): mixed
    {
        return Filament::getTenant();
    }
}
