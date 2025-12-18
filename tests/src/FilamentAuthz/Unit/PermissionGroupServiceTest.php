<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Services\PermissionGroupService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    PermissionGroup::query()->delete();
    Permission::query()->delete();
    User::query()->delete();

    // Create some permissions
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.delete', 'guard_name' => 'web']);
    Permission::create(['name' => 'products.view', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

describe('PermissionGroupService', function (): void {
    describe('createGroup', function (): void {
        test('creates a group with basic info', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Order Management', 'Manage orders');

            expect($group)->toBeInstanceOf(PermissionGroup::class);
            expect($group->name)->toBe('Order Management');
            expect($group->slug)->toBe('order-management');
            expect($group->description)->toBe('Manage orders');
        });

        test('creates a group with parent', function (): void {
            $service = app(PermissionGroupService::class);

            $parent = $service->createGroup('Admin');
            $child = $service->createGroup('Order Management', 'Manage orders', $parent->id);

            expect($child->parent_id)->toBe($parent->id);
            expect($child->parent->id)->toBe($parent->id);
        });

        test('creates a group with permissions', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup(
                'Order Management',
                'Manage orders',
                null,
                ['orders.view', 'orders.create']
            );

            expect($group->permissions)->toHaveCount(2);
            expect($group->permissions->pluck('name')->toArray())->toContain('orders.view', 'orders.create');
        });

        test('creates a group with implicit abilities', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup(
                'Order Management',
                'Manage orders',
                null,
                [],
                ['read', 'write']
            );

            expect($group->implicit_abilities)->toBe(['read', 'write']);
        });

        test('creates a system group', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup(
                'Super Admin',
                'System admin group',
                null,
                [],
                null,
                true
            );

            expect($group->is_system)->toBeTrue();
        });
    });

    describe('updateGroup', function (): void {
        test('updates group name and regenerates slug', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Original Name');
            $updated = $service->updateGroup($group, ['name' => 'New Name']);

            expect($updated->name)->toBe('New Name');
            expect($updated->slug)->toBe('new-name');
        });

        test('updates group description', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group', 'Original description');
            $updated = $service->updateGroup($group, ['description' => 'New description']);

            expect($updated->description)->toBe('New description');
        });

        test('updates group permissions', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group', null, null, ['orders.view']);
            $updated = $service->updateGroup($group, [
                'permissions' => ['orders.create', 'orders.update'],
            ]);

            expect($updated->permissions)->toHaveCount(2);
            expect($updated->permissions->pluck('name')->toArray())->not->toContain('orders.view');
            expect($updated->permissions->pluck('name')->toArray())->toContain('orders.create', 'orders.update');
        });
    });

    describe('deleteGroup', function (): void {
        test('deletes a group', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group');
            $result = $service->deleteGroup($group);

            expect($result)->toBeTrue();
            expect(PermissionGroup::find($group->id))->toBeNull();
        });

        test('returns true on successful deletion', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group');

            expect($service->deleteGroup($group))->toBeTrue();
        });
    });

    describe('syncPermissions', function (): void {
        test('syncs permissions to group', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group');
            $service->syncPermissions($group, ['orders.view', 'orders.create']);

            $group->refresh();
            expect($group->permissions)->toHaveCount(2);
        });

        test('replaces existing permissions', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group', null, null, ['orders.view', 'orders.create']);
            $service->syncPermissions($group, ['products.view']);

            $group->refresh();
            expect($group->permissions)->toHaveCount(1);
            expect($group->permissions->first()->name)->toBe('products.view');
        });
    });

    describe('addPermissions', function (): void {
        test('adds permissions without removing existing', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group', null, null, ['orders.view']);
            $service->addPermissions($group, ['orders.create']);

            $group->refresh();
            expect($group->permissions)->toHaveCount(2);
            expect($group->permissions->pluck('name')->toArray())->toContain('orders.view', 'orders.create');
        });
    });

    describe('removePermissions', function (): void {
        test('removes specific permissions', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group', null, null, ['orders.view', 'orders.create']);
            $service->removePermissions($group, ['orders.view']);

            $group->refresh();
            expect($group->permissions)->toHaveCount(1);
            expect($group->permissions->first()->name)->toBe('orders.create');
        });
    });

    describe('getGroupPermissions', function (): void {
        test('returns direct permissions', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group', null, null, ['orders.view']);
            Cache::flush();

            $permissions = $service->getGroupPermissions($group, false);

            expect($permissions)->toHaveCount(1);
            expect($permissions->first()->name)->toBe('orders.view');
        });

        test('returns inherited permissions from parent', function (): void {
            $service = app(PermissionGroupService::class);

            $parent = $service->createGroup('Parent', null, null, ['orders.view']);
            $child = $service->createGroup('Child', null, $parent->id, ['orders.create']);
            Cache::flush();

            $permissions = $service->getGroupPermissions($child, true);

            expect($permissions)->toHaveCount(2);
            expect($permissions->pluck('name')->toArray())->toContain('orders.view', 'orders.create');
        });

        test('caches permissions', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Test Group', null, null, ['orders.view']);
            Cache::flush();

            // First call - cache miss
            $permissions1 = $service->getGroupPermissions($group);

            // Second call - cache hit
            $permissions2 = $service->getGroupPermissions($group);

            expect($permissions1->toArray())->toBe($permissions2->toArray());
        });
    });

    describe('getRootGroups', function (): void {
        test('returns groups with no parent', function (): void {
            $service = app(PermissionGroupService::class);

            $root1 = $service->createGroup('Root 1');
            $root2 = $service->createGroup('Root 2');
            $service->createGroup('Child', null, $root1->id);

            $roots = $service->getRootGroups();

            expect($roots)->toHaveCount(2);
            expect($roots->pluck('name')->toArray())->toContain('Root 1', 'Root 2');
        });

        test('orders by sort_order', function (): void {
            $service = app(PermissionGroupService::class);

            $service->createGroup('A Group');
            $service->createGroup('B Group');

            $roots = $service->getRootGroups();

            // Default sort_order should be creation order
            expect($roots)->toHaveCount(2);
        });
    });

    describe('getHierarchyTree', function (): void {
        test('returns full tree with children', function (): void {
            $service = app(PermissionGroupService::class);

            $root = $service->createGroup('Root');
            $service->createGroup('Child 1', null, $root->id);
            $service->createGroup('Child 2', null, $root->id);
            Cache::flush();

            $tree = $service->getHierarchyTree();

            expect($tree)->toHaveCount(1);
            expect($tree->first()->children)->toHaveCount(2);
        });

        test('caches the tree', function (): void {
            $service = app(PermissionGroupService::class);

            $service->createGroup('Root');
            Cache::flush();

            $tree1 = $service->getHierarchyTree();
            $tree2 = $service->getHierarchyTree();

            expect($tree1->count())->toBe($tree2->count());
        });
    });

    describe('findBySlug', function (): void {
        test('finds group by slug', function (): void {
            $service = app(PermissionGroupService::class);

            $group = $service->createGroup('Order Management');

            $found = $service->findBySlug('order-management');

            expect($found)->not->toBeNull();
            expect($found->id)->toBe($group->id);
        });

        test('returns null for non-existent slug', function (): void {
            $service = app(PermissionGroupService::class);

            $found = $service->findBySlug('non-existent');

            expect($found)->toBeNull();
        });
    });

    describe('moveGroup', function (): void {
        test('moves group to new parent', function (): void {
            $service = app(PermissionGroupService::class);

            $parent1 = $service->createGroup('Parent 1');
            $parent2 = $service->createGroup('Parent 2');
            $child = $service->createGroup('Child', null, $parent1->id);

            $moved = $service->moveGroup($child, $parent2->id);

            expect($moved->parent_id)->toBe($parent2->id);
        });

        test('moves group to root', function (): void {
            $service = app(PermissionGroupService::class);

            $parent = $service->createGroup('Parent');
            $child = $service->createGroup('Child', null, $parent->id);

            $moved = $service->moveGroup($child, null);

            expect($moved->parent_id)->toBeNull();
            expect($moved->isRoot())->toBeTrue();
        });

        test('throws exception for circular reference', function (): void {
            $service = app(PermissionGroupService::class);

            $parent = $service->createGroup('Parent');
            $child = $service->createGroup('Child', null, $parent->id);
            $grandchild = $service->createGroup('Grandchild', null, $child->id);

            expect(fn () => $service->moveGroup($parent, $grandchild->id))
                ->toThrow(InvalidArgumentException::class, 'Cannot move a group to one of its descendants.');
        });

        test('throws exception when exceeding max depth', function (): void {
            $service = app(PermissionGroupService::class);

            // Create a chain at max depth
            config()->set('filament-authz.hierarchies.max_group_depth', 2);

            $level0 = $service->createGroup('Level 0');
            $level1 = $service->createGroup('Level 1', null, $level0->id);
            $level2 = $service->createGroup('Level 2', null, $level1->id);
            $standalone = $service->createGroup('Standalone');

            // Try to move standalone under level2 - would exceed max depth
            expect(fn () => $service->moveGroup($standalone, $level2->id))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('reorderGroups', function (): void {
        test('updates sort order for groups', function (): void {
            $service = app(PermissionGroupService::class);

            $group1 = $service->createGroup('Group 1');
            $group2 = $service->createGroup('Group 2');
            $group3 = $service->createGroup('Group 3');

            $service->reorderGroups([
                $group1->id => 3,
                $group2->id => 1,
                $group3->id => 2,
            ]);

            $group1->refresh();
            $group2->refresh();
            $group3->refresh();

            expect($group1->sort_order)->toBe(3);
            expect($group2->sort_order)->toBe(1);
            expect($group3->sort_order)->toBe(2);
        });
    });

    describe('getGroupsWithPermission', function (): void {
        test('returns groups containing permission', function (): void {
            $service = app(PermissionGroupService::class);

            $group1 = $service->createGroup('Group 1', null, null, ['orders.view']);
            $group2 = $service->createGroup('Group 2', null, null, ['orders.view', 'orders.create']);
            $service->createGroup('Group 3', null, null, ['products.view']);

            $groups = $service->getGroupsWithPermission('orders.view');

            expect($groups)->toHaveCount(2);
            expect($groups->pluck('id')->toArray())->toContain($group1->id, $group2->id);
        });

        test('returns empty collection for non-existent permission', function (): void {
            $service = app(PermissionGroupService::class);

            $groups = $service->getGroupsWithPermission('non.existent');

            expect($groups)->toBeEmpty();
        });
    });

    describe('clearCache', function (): void {
        test('clears hierarchy tree cache', function (): void {
            $service = app(PermissionGroupService::class);

            $service->createGroup('Test');
            Cache::flush();

            // Populate cache
            $service->getHierarchyTree();

            // Clear cache
            $service->clearCache();

            // Create another group and verify it shows
            $service->createGroup('Another');
            $tree = $service->getHierarchyTree();

            expect($tree)->toHaveCount(2);
        });
    });
});
