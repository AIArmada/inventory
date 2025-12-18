<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    // Clear data
    ScopedPermission::query()->delete();
    Permission::query()->delete();
    Role::query()->delete();
    User::query()->delete();

    // Create and authenticate a user
    $user = User::create([
        'name' => 'System User',
        'email' => 'system@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

describe('RoleInheritanceService', function (): void {
    test('can be instantiated', function (): void {
        $service = app(RoleInheritanceService::class);

        expect($service)->toBeInstanceOf(RoleInheritanceService::class);
    });

    test('setParent establishes parent-child relationship', function (): void {
        $service = app(RoleInheritanceService::class);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $result = $service->setParent($child, $parent);

        expect((string) $result->parent_role_id)->toBe((string) $parent->id);
        expect((int) $result->level)->toBe(1);
    });

    test('setParent can clear parent relationship', function (): void {
        $service = app(RoleInheritanceService::class);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($child, $parent);
        $result = $service->setParent($child, null);

        expect($result->parent_role_id)->toBeNull();
        expect($result->level)->toBe(0);
    });

    test('setParent prevents circular references', function (): void {
        $service = app(RoleInheritanceService::class);

        $grandparent = Role::create(['name' => 'grandparent', 'guard_name' => 'web']);
        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($parent, $grandparent);
        $service->setParent($child, $parent);

        // Try to set grandparent's parent as child (circular)
        expect(fn () => $service->setParent($grandparent, $child))
            ->toThrow(InvalidArgumentException::class, 'Cannot set a descendant role as parent.');
    });

    test('setParent enforces max depth limit', function (): void {
        $service = app(RoleInheritanceService::class);

        // Create a chain up to max depth
        $roles = [];
        $maxDepth = config('filament-authz.hierarchies.max_role_depth', 5);

        // Create more roles than max depth
        for ($i = 0; $i <= $maxDepth + 2; $i++) {
            $roles[$i] = Role::create(['name' => "role_{$i}", 'guard_name' => 'web']);
        }

        // Build chain up to max depth - 1 (so we're one below max)
        for ($i = 1; $i < $maxDepth; $i++) {
            $service->setParent($roles[$i], $roles[$i - 1]);
        }

        // At this point we have a chain of maxDepth roles (0 to maxDepth-1)
        // Adding another should succeed
        $service->setParent($roles[$maxDepth], $roles[$maxDepth - 1]);

        // But adding one MORE should fail (exceeds depth)
        expect(fn () => $service->setParent($roles[$maxDepth + 1], $roles[$maxDepth]))
            ->toThrow(InvalidArgumentException::class);
    });

    test('getParent returns parent role', function (): void {
        $service = app(RoleInheritanceService::class);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);
        $service->setParent($child, $parent);

        $result = $service->getParent($child);

        expect($result)->not->toBeNull();
        expect($result->id)->toBe($parent->id);
    });

    test('getParent returns null for root role', function (): void {
        $service = app(RoleInheritanceService::class);

        $role = Role::create(['name' => 'root', 'guard_name' => 'web']);

        $result = $service->getParent($role);

        expect($result)->toBeNull();
    });

    test('getAncestors returns all ancestors in order', function (): void {
        $service = app(RoleInheritanceService::class);

        $grandparent = Role::create(['name' => 'grandparent', 'guard_name' => 'web']);
        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($parent, $grandparent);
        $service->setParent($child, $parent);

        $ancestors = $service->getAncestors($child);

        expect($ancestors)->toHaveCount(2);
        expect($ancestors->pluck('name')->toArray())->toContain('parent', 'grandparent');
    });

    test('getAncestors returns empty collection for root', function (): void {
        $service = app(RoleInheritanceService::class);

        $role = Role::create(['name' => 'root', 'guard_name' => 'web']);

        $ancestors = $service->getAncestors($role);

        expect($ancestors)->toBeEmpty();
    });

    test('getDescendants returns all descendants', function (): void {
        $service = app(RoleInheritanceService::class);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child1 = Role::create(['name' => 'child1', 'guard_name' => 'web']);
        $child2 = Role::create(['name' => 'child2', 'guard_name' => 'web']);
        $grandchild = Role::create(['name' => 'grandchild', 'guard_name' => 'web']);

        $service->setParent($child1, $parent);
        $service->setParent($child2, $parent);
        $service->setParent($grandchild, $child1);

        $descendants = $service->getDescendants($parent);

        expect($descendants)->toHaveCount(3);
        expect($descendants->pluck('name')->toArray())->toContain('child1', 'child2', 'grandchild');
    });

    test('getDescendants returns empty for leaf node', function (): void {
        $service = app(RoleInheritanceService::class);

        $role = Role::create(['name' => 'leaf', 'guard_name' => 'web']);

        $descendants = $service->getDescendants($role);

        expect($descendants)->toBeEmpty();
    });

    test('getChildren returns direct children only', function (): void {
        $service = app(RoleInheritanceService::class);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child1 = Role::create(['name' => 'child1', 'guard_name' => 'web']);
        $child2 = Role::create(['name' => 'child2', 'guard_name' => 'web']);
        $grandchild = Role::create(['name' => 'grandchild', 'guard_name' => 'web']);

        $service->setParent($child1, $parent);
        $service->setParent($child2, $parent);
        $service->setParent($grandchild, $child1);

        $children = $service->getChildren($parent);

        expect($children)->toHaveCount(2);
        expect($children->pluck('name')->toArray())->toContain('child1', 'child2');
        expect($children->pluck('name')->toArray())->not->toContain('grandchild');
    });

    test('getRootRoles returns roles without parents', function (): void {
        $service = app(RoleInheritanceService::class);

        $root1 = Role::create(['name' => 'root1', 'guard_name' => 'web']);
        $root2 = Role::create(['name' => 'root2', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($child, $root1);

        $roots = $service->getRootRoles();

        expect($roots)->toHaveCount(2);
        expect($roots->pluck('name')->toArray())->toContain('root1', 'root2');
    });

    test('isAncestorOf returns true for ancestor', function (): void {
        $service = app(RoleInheritanceService::class);

        $grandparent = Role::create(['name' => 'grandparent', 'guard_name' => 'web']);
        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($parent, $grandparent);
        $service->setParent($child, $parent);

        expect($service->isAncestorOf($grandparent, $child))->toBeTrue();
        expect($service->isAncestorOf($parent, $child))->toBeTrue();
    });

    test('isAncestorOf returns false for non-ancestor', function (): void {
        $service = app(RoleInheritanceService::class);

        $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);

        expect($service->isAncestorOf($role1, $role2))->toBeFalse();
    });

    test('isDescendantOf returns true for descendant', function (): void {
        $service = app(RoleInheritanceService::class);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);
        $grandchild = Role::create(['name' => 'grandchild', 'guard_name' => 'web']);

        $service->setParent($child, $parent);
        $service->setParent($grandchild, $child);

        expect($service->isDescendantOf($grandchild, $parent))->toBeTrue();
        expect($service->isDescendantOf($child, $parent))->toBeTrue();
    });

    test('isDescendantOf returns false for non-descendant', function (): void {
        $service = app(RoleInheritanceService::class);

        $role1 = Role::create(['name' => 'role1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role2', 'guard_name' => 'web']);

        expect($service->isDescendantOf($role1, $role2))->toBeFalse();
    });

    test('getDepth returns correct depth', function (): void {
        $service = app(RoleInheritanceService::class);

        $grandparent = Role::create(['name' => 'grandparent', 'guard_name' => 'web']);
        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($parent, $grandparent);
        $service->setParent($child, $parent);

        expect($service->getDepth($grandparent))->toBe(0);
        expect($service->getDepth($parent))->toBe(1);
        expect($service->getDepth($child))->toBe(2);
    });

    test('getHierarchyTree returns all root roles', function (): void {
        $service = app(RoleInheritanceService::class);

        $root1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $root2 = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $service->setParent($child, $root1);

        $tree = $service->getHierarchyTree();

        // Tree should contain root roles
        expect($tree->pluck('name')->toArray())->toContain('admin', 'super_admin');
    });

    test('moveRole changes parent', function (): void {
        $service = app(RoleInheritanceService::class);

        $oldParent = Role::create(['name' => 'oldParent', 'guard_name' => 'web']);
        $newParent = Role::create(['name' => 'newParent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($child, $oldParent);
        $result = $service->moveRole($child, $newParent);

        expect((string) $result->parent_role_id)->toBe((string) $newParent->id);
    });

    test('detachFromParent removes parent', function (): void {
        $service = app(RoleInheritanceService::class);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($child, $parent);
        $result = $service->detachFromParent($child);

        expect($result->parent_role_id)->toBeNull();
        expect($result->level)->toBe(0);
    });

    test('getInheritedPermissions returns permissions from ancestors', function (): void {
        $service = app(RoleInheritanceService::class);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $perm1 = Permission::create(['name' => 'perm1', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'perm2', 'guard_name' => 'web']);
        $parent->givePermissionTo($perm1, $perm2);

        $service->setParent($child, $parent);

        $inherited = $service->getInheritedPermissions($child);

        expect($inherited)->toHaveCount(2);
        expect($inherited->pluck('name')->toArray())->toContain('perm1', 'perm2');
    });

    test('clearCache clears hierarchy cache', function (): void {
        $service = app(RoleInheritanceService::class);

        // Create a role and cache some data
        $role = Role::create(['name' => 'cached', 'guard_name' => 'web']);
        $service->getAncestors($role);
        $service->getDescendants($role);

        // Clear cache should not throw
        $service->clearCache();

        // Verify we can still fetch data after cache clear
        $ancestors = $service->getAncestors($role);
        expect($ancestors)->toBeEmpty();
    });

    test('updateDescendantLevels updates all descendants when parent changes', function (): void {
        $service = app(RoleInheritanceService::class);

        $grandparent = Role::create(['name' => 'grandparent', 'guard_name' => 'web']);
        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);

        $service->setParent($parent, $grandparent);
        $service->setParent($child, $parent);

        // Child should be at level 2
        $child->refresh();
        expect($child->level)->toBe(2);

        // Now detach parent from grandparent
        $service->detachFromParent($parent);

        // Child level should update
        $child->refresh();
        expect($child->level)->toBe(1);
    });
});
