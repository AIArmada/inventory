<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionAggregator
{
    protected const CACHE_KEY_PREFIX = 'permissions:aggregated:';

    public function __construct(
        protected RoleInheritanceService $roleInheritance,
        protected WildcardPermissionResolver $wildcardResolver,
        protected ImplicitPermissionService $implicitService
    ) {}

    /**
     * Get all effective permissions for a user.
     *
     * @param  object  $user
     * @return Collection<int, Permission>
     */
    public function getEffectivePermissions($user): Collection
    {
        if (! method_exists($user, 'getRoleNames')) {
            return new Collection;
        }

        $cacheKey = self::CACHE_KEY_PREFIX . "user:{$user->getKey()}";
        $ttl = config('filament-authz.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($user): Collection {
            $permissions = collect();

            // Direct permissions
            if (method_exists($user, 'getDirectPermissions')) {
                $permissions = $permissions->merge($user->getDirectPermissions());
            }

            // Role permissions (including inherited from parent roles)
            $roleNames = $user->getRoleNames();
            foreach ($roleNames as $roleName) {
                /** @var Role|null $role */
                $role = Role::findByName($roleName);
                if ($role !== null) {
                    $rolePermissions = $this->getEffectiveRolePermissions($role);
                    $permissions = $permissions->merge($rolePermissions);
                }
            }

            /** @var Collection<int, Permission> */
            return new Collection($permissions->unique('id')->values()->all());
        });
    }

    /**
     * Get all effective permissions for a role (including inherited).
     *
     * @return Collection<int, Permission>
     */
    public function getEffectiveRolePermissions(Role $role): Collection
    {
        $cacheKey = self::CACHE_KEY_PREFIX . "role:{$role->id}";
        $ttl = config('filament-authz.cache_ttl', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($role): Collection {
            $permissions = $role->permissions;

            // Add inherited permissions from parent roles
            $inheritedPermissions = $this->roleInheritance->getInheritedPermissions($role);
            $permissions = $permissions->merge($inheritedPermissions);

            return new Collection($permissions->unique('id')->values()->all());
        });
    }

    /**
     * Get all effective permission names for a user.
     *
     * @param  object  $user
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getEffectivePermissionNames($user): \Illuminate\Support\Collection
    {
        return $this->getEffectivePermissions($user)->pluck('name');
    }

    /**
     * Check if a user has a permission (with full aggregation).
     *
     * @param  object  $user
     */
    public function userHasPermission($user, string $permission, ?Model $resource = null): bool
    {
        $permissionNames = $this->getEffectivePermissionNames($user);

        // Direct match
        if ($permissionNames->contains($permission)) {
            return true;
        }

        // Check wildcard permissions
        foreach ($permissionNames as $userPermission) {
            if ($this->wildcardResolver->isWildcard($userPermission)) {
                if ($this->wildcardResolver->matches($userPermission, $permission)) {
                    return true;
                }
            }
        }

        // Check implicit permissions
        foreach ($permissionNames as $userPermission) {
            if ($this->implicitService->implies($userPermission, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user has any of the given permissions.
     *
     * @param  object  $user
     * @param  array<string>  $permissions
     */
    public function userHasAnyPermission($user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->userHasPermission($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user has all of the given permissions.
     *
     * @param  object  $user
     * @param  array<string>  $permissions
     */
    public function userHasAllPermissions($user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->userHasPermission($user, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all roles for a user (including inherited roles).
     *
     * @param  object  $user
     * @return Collection<int, Role>
     */
    public function getEffectiveRoles($user): Collection
    {
        if (! method_exists($user, 'roles')) {
            return new Collection;
        }

        $directRoles = $user->roles;
        $allRoles = collect();

        foreach ($directRoles as $role) {
            $allRoles->push($role);
            $ancestors = $this->roleInheritance->getAncestors($role);
            $allRoles = $allRoles->merge($ancestors);
        }

        return $allRoles->unique('id');
    }

    /**
     * Get the permission source (which role/grant provides the permission).
     *
     * @param  object  $user
     * @return array{type: string, source: string|null, via: string|null}
     */
    public function getPermissionSource($user, string $permission): array
    {
        // Check direct permissions
        if (method_exists($user, 'getDirectPermissions')) {
            $directPermissions = $user->getDirectPermissions();
            if ($directPermissions->contains('name', $permission)) {
                return ['type' => 'direct', 'source' => null, 'via' => null];
            }
        }

        // Check role permissions
        if (method_exists($user, 'getRoleNames')) {
            foreach ($user->getRoleNames() as $roleName) {
                /** @var Role|null $role */
                $role = Role::findByName($roleName);
                if ($role === null) {
                    continue;
                }

                // Direct role permission
                if ($role->hasPermissionTo($permission)) {
                    return ['type' => 'role', 'source' => $role->name, 'via' => null];
                }

                // Inherited role permission
                $ancestors = $this->roleInheritance->getAncestors($role);
                foreach ($ancestors as $ancestor) {
                    if ($ancestor->hasPermissionTo($permission)) {
                        return ['type' => 'inherited', 'source' => $ancestor->name, 'via' => $role->name];
                    }
                }
            }
        }

        // Check wildcard match
        $permissionNames = $this->getEffectivePermissionNames($user);
        foreach ($permissionNames as $userPermission) {
            if ($this->wildcardResolver->matches($userPermission, $permission)) {
                return ['type' => 'wildcard', 'source' => $userPermission, 'via' => null];
            }
        }

        // Check implicit
        foreach ($permissionNames as $userPermission) {
            if ($this->implicitService->implies($userPermission, $permission)) {
                return ['type' => 'implicit', 'source' => $userPermission, 'via' => null];
            }
        }

        return ['type' => 'none', 'source' => null, 'via' => null];
    }

    /**
     * Clear the aggregation cache for a user.
     *
     * @param  object  $user
     */
    public function clearUserCache($user): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . "user:{$user->getKey()}");
    }

    /**
     * Clear the aggregation cache for a role.
     */
    public function clearRoleCache(Role $role): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . "role:{$role->id}");

        // Also clear descendants
        $descendants = $this->roleInheritance->getDescendants($role);
        foreach ($descendants as $descendant) {
            Cache::forget(self::CACHE_KEY_PREFIX . "role:{$descendant->id}");
        }
    }

    /**
     * Clear all aggregation caches.
     */
    public function clearAllCache(): void
    {
        $this->wildcardResolver->clearCache();
        $this->implicitService->clearCache();
        $this->roleInheritance->clearCache();

        // Note: Individual user/role caches will be cleared on next access
    }
}
