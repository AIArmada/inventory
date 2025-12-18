<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\RoleComparer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::query()->delete();
    Permission::query()->delete();

    // Create permissions
    $permissions = [
        'orders.view', 'orders.create', 'orders.update', 'orders.delete',
        'products.view', 'products.create', 'products.update', 'products.delete',
        'users.view', 'users.create', 'users.update', 'users.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'web']);
    }

    test()->comparer = app(RoleComparer::class);
});

describe('RoleComparer → compare', function (): void {
    it('compares two roles with shared permissions', function (): void {
        $roleA = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleA->givePermissionTo(['orders.view', 'orders.create', 'products.view']);

        $roleB = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $roleB->givePermissionTo(['orders.view', 'products.view', 'products.create']);

        $result = test()->comparer->compare($roleA, $roleB);

        expect($result['role_a'])->toBe('admin')
            ->and($result['role_b'])->toBe('editor')
            ->and($result['shared_permissions'])->toContain('orders.view')
            ->and($result['shared_permissions'])->toContain('products.view')
            ->and($result['only_in_a'])->toContain('orders.create')
            ->and($result['only_in_b'])->toContain('products.create');
    });

    it('calculates similarity percentage correctly', function (): void {
        $roleA = Role::create(['name' => 'roleA', 'guard_name' => 'web']);
        $roleA->givePermissionTo(['orders.view', 'orders.create']);

        $roleB = Role::create(['name' => 'roleB', 'guard_name' => 'web']);
        $roleB->givePermissionTo(['orders.view', 'orders.create']);

        $result = test()->comparer->compare($roleA, $roleB);

        expect($result['similarity_percent'])->toBe(100.0);
    });

    it('returns 0 similarity for roles with no shared permissions', function (): void {
        $roleA = Role::create(['name' => 'roleA', 'guard_name' => 'web']);
        $roleA->givePermissionTo(['orders.view', 'orders.create']);

        $roleB = Role::create(['name' => 'roleB', 'guard_name' => 'web']);
        $roleB->givePermissionTo(['products.view', 'products.create']);

        $result = test()->comparer->compare($roleA, $roleB);

        expect($result['similarity_percent'])->toBe(0.0)
            ->and($result['shared_permissions'])->toBeEmpty();
    });

    it('handles roles with no permissions', function (): void {
        $roleA = Role::create(['name' => 'roleA', 'guard_name' => 'web']);
        $roleB = Role::create(['name' => 'roleB', 'guard_name' => 'web']);

        $result = test()->comparer->compare($roleA, $roleB);

        expect($result['similarity_percent'])->toBe(100.0)
            ->and($result['shared_permissions'])->toBeEmpty()
            ->and($result['only_in_a'])->toBeEmpty()
            ->and($result['only_in_b'])->toBeEmpty();
    });
});

describe('RoleComparer → findSimilarRoles', function (): void {
    it('finds roles above similarity threshold', function (): void {
        $roleA = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleA->givePermissionTo(['orders.view', 'orders.create', 'orders.update', 'orders.delete']);

        $roleB = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $roleB->givePermissionTo(['orders.view', 'orders.create', 'orders.update']);

        $roleC = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $roleC->givePermissionTo(['orders.view']);

        $similar = test()->comparer->findSimilarRoles($roleA, 50.0);

        $roleNames = array_column($similar, 'role');
        expect($roleNames)->toContain('editor');
    });

    it('returns empty for roles with no similar matches', function (): void {
        $roleA = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleA->givePermissionTo(['orders.view']);

        $roleB = Role::create(['name' => 'other', 'guard_name' => 'web']);
        $roleB->givePermissionTo(['products.view']);

        $similar = test()->comparer->findSimilarRoles($roleA, 80.0);

        expect($similar)->toBeEmpty();
    });

    it('sorts results by similarity descending', function (): void {
        $roleA = Role::create(['name' => 'target', 'guard_name' => 'web']);
        $roleA->givePermissionTo(['orders.view', 'orders.create', 'orders.update', 'orders.delete']);

        $roleB = Role::create(['name' => 'similar', 'guard_name' => 'web']);
        $roleB->givePermissionTo(['orders.view', 'orders.create', 'orders.update', 'orders.delete']);

        $roleC = Role::create(['name' => 'less_similar', 'guard_name' => 'web']);
        $roleC->givePermissionTo(['orders.view', 'orders.create']);

        $similar = test()->comparer->findSimilarRoles($roleA, 40.0);

        expect($similar[0]['role'])->toBe('similar')
            ->and($similar[0]['similarity_percent'])->toBeGreaterThan($similar[1]['similarity_percent']);
    });
});

describe('RoleComparer → findRedundantRoles', function (): void {
    it('finds roles with identical permissions', function (): void {
        $roleA = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $roleA->givePermissionTo(['orders.view', 'orders.create']);

        $roleB = Role::create(['name' => 'admin_copy', 'guard_name' => 'web']);
        $roleB->givePermissionTo(['orders.view', 'orders.create']);

        $redundant = test()->comparer->findRedundantRoles();

        expect($redundant)->not->toBeEmpty()
            ->and($redundant[0]['roles'])->toContain('admin')
            ->and($redundant[0]['roles'])->toContain('admin_copy');
    });

    it('returns empty when no redundant roles exist', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web'])
            ->givePermissionTo(['orders.view']);

        Role::create(['name' => 'editor', 'guard_name' => 'web'])
            ->givePermissionTo(['products.view']);

        $redundant = test()->comparer->findRedundantRoles();

        expect($redundant)->toBeEmpty();
    });
});

describe('RoleComparer → getDiff', function (): void {
    it('returns permissions to add and remove', function (): void {
        $from = Role::create(['name' => 'from', 'guard_name' => 'web']);
        $from->givePermissionTo(['orders.view', 'orders.create']);

        $to = Role::create(['name' => 'to', 'guard_name' => 'web']);
        $to->givePermissionTo(['orders.view', 'products.view']);

        $diff = test()->comparer->getDiff($from, $to);

        expect($diff['to_add'])->toContain('products.view')
            ->and($diff['to_remove'])->toContain('orders.create')
            ->and($diff['operations_count'])->toBe(2);
    });

    it('returns empty diff for identical roles', function (): void {
        $from = Role::create(['name' => 'from', 'guard_name' => 'web']);
        $from->givePermissionTo(['orders.view']);

        $to = Role::create(['name' => 'to', 'guard_name' => 'web']);
        $to->givePermissionTo(['orders.view']);

        $diff = test()->comparer->getDiff($from, $to);

        expect($diff['to_add'])->toBeEmpty()
            ->and($diff['to_remove'])->toBeEmpty()
            ->and($diff['operations_count'])->toBe(0);
    });
});

describe('RoleComparer → generateHierarchyReport', function (): void {
    it('generates report with total roles', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);

        $report = test()->comparer->generateHierarchyReport();

        expect($report['total_roles'])->toBe(3);
    });

    it('reports max depth and roles per level', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $report = test()->comparer->generateHierarchyReport();

        expect($report)->toHaveKey('max_depth')
            ->and($report)->toHaveKey('roles_per_level');
    });
});

describe('RoleComparer → findUnusedPermissions', function (): void {
    it('finds permissions not assigned to any role', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['orders.view', 'orders.create']);

        $unused = test()->comparer->findUnusedPermissions();

        expect($unused)->toContain('products.view')
            ->and($unused)->toContain('users.view')
            ->and($unused)->not->toContain('orders.view');
    });

    it('returns empty when all permissions are assigned', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::all()->pluck('name')->toArray());

        $unused = test()->comparer->findUnusedPermissions();

        expect($unused)->toBeEmpty();
    });
});
