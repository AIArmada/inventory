<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Service for managing role hierarchy (parent-child relationships).
 *
 * Note: This service relies on additional columns (parent_role_id, level, is_system, etc.)
 * added to the roles table by filament-authz migrations.
 *
 * @phpstan-type ExtendedRole Role&object{parent_role_id: string|null, level: int, is_system: bool}
 */
class RoleInheritanceService
{
    protected const CACHE_KEY_PREFIX = 'permissions:role_hierarchy:';

    /**
     * Set a parent role for a role.
     */
    public function setParent(Role $role, ?Role $parent): Role
    {
        if ($parent !== null) {
            // Prevent circular references
            if ($this->isAncestorOf($role, $parent)) {
                throw new InvalidArgumentException('Cannot set a descendant role as parent.');
            }

            // Check depth limit
            $maxDepth = config('filament-authz.hierarchies.max_role_depth', 5);
            $parentDepth = $this->getDepth($parent);
            $subtreeDepth = $this->getMaxSubtreeDepth($role);

            if ($parentDepth + 1 + $subtreeDepth > $maxDepth) {
                throw new InvalidArgumentException("Setting this parent would exceed the maximum depth of {$maxDepth}.");
            }
        }

        /** @phpstan-ignore property.notFound */
        $role->parent_role_id = $parent?->id;
        /** @phpstan-ignore property.notFound */
        $role->level = $parent !== null ? $this->getDepth($parent) + 1 : 0;
        $role->save();

        // Update levels for all descendants
        $this->updateDescendantLevels($role);
        $this->clearCache();

        return $role->refresh();
    }

    /**
     * Get the parent role.
     */
    public function getParent(Role $role): ?Role
    {
        /** @phpstan-ignore property.notFound */
        $parentId = $role->parent_role_id ?? null;

        if ($parentId === null) {
            return null;
        }

        return Role::find($parentId);
    }

    /**
     * Get all ancestor roles.
     *
     * @return Collection<int, Role>
     */
    public function getAncestors(Role $role): Collection
    {
        $cacheKey = self::CACHE_KEY_PREFIX . "ancestors:{$role->id}";
        $ttl = config('filament-authz.cache_ttl', 3600);

        if (config('filament-authz.hierarchies.cache_hierarchy', true)) {
            return Cache::remember($cacheKey, $ttl, fn () => $this->fetchAncestors($role));
        }

        return $this->fetchAncestors($role);
    }

    /**
     * Get all descendant roles.
     *
     * @return Collection<int, Role>
     */
    public function getDescendants(Role $role): Collection
    {
        $cacheKey = self::CACHE_KEY_PREFIX . "descendants:{$role->id}";
        $ttl = config('filament-authz.cache_ttl', 3600);

        if (config('filament-authz.hierarchies.cache_hierarchy', true)) {
            return Cache::remember($cacheKey, $ttl, fn () => $this->fetchDescendants($role));
        }

        return $this->fetchDescendants($role);
    }

    /**
     * Get direct children roles.
     *
     * @return Collection<int, Role>
     */
    public function getChildren(Role $role): Collection
    {
        return Role::query()
            ->where('parent_role_id', $role->id)
            ->get();
    }

    /**
     * Get all root roles (no parent).
     *
     * @return Collection<int, Role>
     */
    public function getRootRoles(): Collection
    {
        return Role::query()
            ->whereNull('parent_role_id')
            ->get();
    }

    /**
     * Check if a role is an ancestor of another.
     */
    public function isAncestorOf(Role $potentialAncestor, Role $role): bool
    {
        $ancestors = $this->getAncestors($role);

        return $ancestors->contains('id', $potentialAncestor->id);
    }

    /**
     * Check if a role is a descendant of another.
     */
    public function isDescendantOf(Role $potentialDescendant, Role $role): bool
    {
        $descendants = $this->getDescendants($role);

        return $descendants->contains('id', $potentialDescendant->id);
    }

    /**
     * Get the depth of a role in the hierarchy.
     */
    public function getDepth(Role $role): int
    {
        return $role->level ?? $this->getAncestors($role)->count();
    }

    /**
     * Get the full hierarchy tree.
     *
     * @return Collection<int, Role>
     */
    public function getHierarchyTree(): Collection
    {
        $cacheKey = self::CACHE_KEY_PREFIX . 'tree';
        $ttl = config('filament-authz.cache_ttl', 3600);

        if (config('filament-authz.hierarchies.cache_hierarchy', true)) {
            return Cache::remember($cacheKey, $ttl, fn () => $this->buildHierarchyTree());
        }

        return $this->buildHierarchyTree();
    }

    /**
     * Move a role to a new position in the hierarchy.
     */
    public function moveRole(Role $role, ?Role $newParent): Role
    {
        return $this->setParent($role, $newParent);
    }

    /**
     * Detach a role from its parent.
     */
    public function detachFromParent(Role $role): Role
    {
        return $this->setParent($role, null);
    }

    /**
     * Get inherited permissions from ancestors.
     *
     * @return Collection<int, Permission>
     */
    public function getInheritedPermissions(Role $role): Collection
    {
        $ancestors = $this->getAncestors($role);
        $permissions = new Collection;

        foreach ($ancestors as $ancestor) {
            $permissions = $permissions->merge($ancestor->permissions);
        }

        // Return as Eloquent Collection
        return new Collection($permissions->unique('id')->values()->all());
    }

    /**
     * Clear the hierarchy cache.
     */
    public function clearCache(): void
    {
        // Clear tree cache
        Cache::forget(self::CACHE_KEY_PREFIX . 'tree');

        // Clear individual role caches
        $roles = Role::query()->pluck('id');
        foreach ($roles as $roleId) {
            Cache::forget(self::CACHE_KEY_PREFIX . "ancestors:{$roleId}");
            Cache::forget(self::CACHE_KEY_PREFIX . "descendants:{$roleId}");
        }
    }

    /**
     * Fetch ancestors without cache.
     *
     * @return Collection<int, Role>
     */
    protected function fetchAncestors(Role $role): Collection
    {
        // Try using recursive CTE if available
        if ($this->supportsRecursiveCTE()) {
            return $this->fetchAncestorsWithCTE($role);
        }

        // Fallback to iterative approach
        $ancestors = new Collection;
        $current = $this->getParent($role);

        while ($current !== null) {
            $ancestors->push($current);
            $current = $this->getParent($current);
        }

        return $ancestors;
    }

    /**
     * Fetch ancestors using recursive CTE.
     *
     * @return Collection<int, Role>
     */
    protected function fetchAncestorsWithCTE(Role $role): Collection
    {
        $tableName = config('permission.table_names.roles', 'roles');

        $sql = "
            WITH RECURSIVE role_tree AS (
                SELECT id, name, guard_name, parent_role_id, level, 0 AS depth
                FROM {$tableName}
                WHERE id = ?
                
                UNION ALL
                
                SELECT r.id, r.name, r.guard_name, r.parent_role_id, r.level, rt.depth + 1
                FROM {$tableName} r
                INNER JOIN role_tree rt ON r.id = rt.parent_role_id
            )
            SELECT * FROM role_tree WHERE depth > 0 ORDER BY depth ASC
        ";

        $results = DB::select($sql, [$role->id]);

        return Role::hydrate($results);
    }

    /**
     * Fetch descendants without cache.
     *
     * @return Collection<int, Role>
     */
    protected function fetchDescendants(Role $role): Collection
    {
        if ($this->supportsRecursiveCTE()) {
            return $this->fetchDescendantsWithCTE($role);
        }

        $descendants = new Collection;

        $children = $this->getChildren($role);
        foreach ($children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->fetchDescendants($child));
        }

        return $descendants;
    }

    /**
     * Fetch descendants using recursive CTE.
     *
     * @return Collection<int, Role>
     */
    protected function fetchDescendantsWithCTE(Role $role): Collection
    {
        $tableName = config('permission.table_names.roles', 'roles');

        $sql = "
            WITH RECURSIVE role_tree AS (
                SELECT id, name, guard_name, parent_role_id, level, 0 AS depth
                FROM {$tableName}
                WHERE id = ?
                
                UNION ALL
                
                SELECT r.id, r.name, r.guard_name, r.parent_role_id, r.level, rt.depth + 1
                FROM {$tableName} r
                INNER JOIN role_tree rt ON rt.id = r.parent_role_id
            )
            SELECT * FROM role_tree WHERE depth > 0 ORDER BY depth ASC
        ";

        $results = DB::select($sql, [$role->id]);

        return Role::hydrate($results);
    }

    /**
     * Build the full hierarchy tree.
     *
     * @return Collection<int, Role>
     */
    protected function buildHierarchyTree(): Collection
    {
        return Role::query()
            ->whereNull('parent_role_id')
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /**
     * Update levels for all descendants of a role.
     */
    protected function updateDescendantLevels(Role $role): void
    {
        $children = $this->getChildren($role);
        /** @phpstan-ignore property.notFound */
        $newLevel = ($role->level ?? 0) + 1;

        foreach ($children as $child) {
            /** @phpstan-ignore property.notFound */
            $child->level = $newLevel;
            $child->save();
            $this->updateDescendantLevels($child);
        }
    }

    /**
     * Get the maximum depth of a role's subtree.
     */
    protected function getMaxSubtreeDepth(Role $role): int
    {
        $children = $this->getChildren($role);

        if ($children->isEmpty()) {
            return 0;
        }

        $maxDepth = 0;
        foreach ($children as $child) {
            $childDepth = 1 + $this->getMaxSubtreeDepth($child);
            $maxDepth = max($maxDepth, $childDepth);
        }

        return $maxDepth;
    }

    /**
     * Check if the database supports recursive CTEs.
     */
    protected function supportsRecursiveCTE(): bool
    {
        $driver = DB::getDriverName();

        return in_array($driver, ['mysql', 'pgsql', 'sqlite'], true);
    }
}
