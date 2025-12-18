<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\ImpactLevel;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use AIArmada\FilamentAuthz\Services\PermissionImpactAnalyzer;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    // Clear data
    ScopedPermission::query()->delete();
    Permission::query()->delete();
    Role::query()->delete();
    User::query()->delete();
    DB::table('model_has_roles')->delete();
    DB::table('model_has_permissions')->delete();
    DB::table('role_has_permissions')->delete();

    // Create and authenticate a user
    $user = User::create([
        'name' => 'System User',
        'email' => 'system@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

describe('PermissionImpactAnalyzer', function (): void {
    test('can be instantiated', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        expect($analyzer)->toBeInstanceOf(PermissionImpactAnalyzer::class);
    });

    test('analyzePermissionGrant returns impact analysis', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $result = $analyzer->analyzePermissionGrant('posts.edit', $role);

        expect($result)->toHaveKeys([
            'permission',
            'role',
            'impact_level',
            'affected_users_count',
            'affected_roles',
            'reasoning',
        ]);
        expect($result['permission'])->toBe('posts.edit');
        expect($result['role'])->toBe('editor');
        expect($result['impact_level'])->toBeInstanceOf(ImpactLevel::class);
        expect($result['affected_roles'])->toContain('editor');
    });

    test('analyzePermissionGrant reflects affected users count', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        // Create users and assign role
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
            ]);
            $user->assignRole($role);
        }

        $result = $analyzer->analyzePermissionGrant('posts.create', $role);

        expect($result['affected_users_count'])->toBe(5);
        expect($result['impact_level'])->toBe(ImpactLevel::Low);
    });

    test('analyzePermissionGrant considers role hierarchy descendants', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        // Create parent role
        $parentRole = Role::create(['name' => 'manager', 'guard_name' => 'web']);

        // Create child role
        $childRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $roleInheritance->setParent($childRole, $parentRole);

        // Assign user to child role
        $user = User::create([
            'name' => 'Child User',
            'email' => 'child@example.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($childRole);

        $result = $analyzer->analyzePermissionGrant('posts.edit', $parentRole);

        // Should affect both parent and child roles
        expect($result['affected_roles'])->toContain('manager', 'editor');
    });

    test('analyzePermissionRevoke returns revoke impact analysis', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        // Add users
        for ($i = 1; $i <= 3; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
            ]);
            $user->assignRole($role);
        }

        $result = $analyzer->analyzePermissionRevoke('posts.delete', $role);

        expect($result)->toHaveKeys([
            'permission',
            'role',
            'impact_level',
            'affected_users_count',
            'affected_roles',
            'users_losing_access',
            'reasoning',
        ]);
        expect($result['permission'])->toBe('posts.delete');
        expect($result['users_losing_access'])->toBe(3);
    });

    test('analyzePermissionRevoke considers users who still have access through other roles', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        // Create roles
        $editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'posts.delete', 'guard_name' => 'web']);

        // Both roles have the permission
        $editorRole->givePermissionTo($permission);
        $adminRole->givePermissionTo($permission);

        // User1 only has editor role
        $user1 = User::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
        ]);
        $user1->assignRole($editorRole);

        // User2 has both roles
        $user2 = User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
        ]);
        $user2->assignRole($editorRole);
        $user2->assignRole($adminRole);

        $result = $analyzer->analyzePermissionRevoke('posts.delete', $editorRole);

        // Only user1 loses access (user2 still has it via admin role)
        expect($result['users_losing_access'])->toBe(1);
        expect($result['affected_users_count'])->toBe(2);
    });

    test('analyzeRoleDeletion returns deletion impact analysis', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        $role = Role::create(['name' => 'contributor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        // Add users
        for ($i = 1; $i <= 2; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
            ]);
            $user->assignRole($role);
        }

        $result = $analyzer->analyzeRoleDeletion($role);

        expect($result)->toHaveKeys([
            'role',
            'impact_level',
            'affected_users_count',
            'child_roles',
            'permissions_to_redistribute',
            'reasoning',
        ]);
        expect($result['role'])->toBe('contributor');
        expect($result['affected_users_count'])->toBe(2);
        expect($result['permissions_to_redistribute'])->toBe(1);
    });

    test('analyzeRoleDeletion escalates impact when role has children', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        // Create parent role
        $parentRole = Role::create(['name' => 'manager', 'guard_name' => 'web']);

        // Create child roles
        $child1 = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $child2 = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $roleInheritance->setParent($child1, $parentRole);
        $roleInheritance->setParent($child2, $parentRole);

        $result = $analyzer->analyzeRoleDeletion($parentRole);

        // Impact should be escalated due to children
        expect($result['child_roles'])->toContain('editor', 'viewer');
        expect($result['impact_level'])->toBe(ImpactLevel::Medium);
    });

    test('analyzeHierarchyChange returns hierarchy change analysis', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        // Create roles with permissions
        $oldParent = Role::create(['name' => 'oldParent', 'guard_name' => 'web']);
        $oldParent->givePermissionTo(Permission::create(['name' => 'perm.old1', 'guard_name' => 'web']));
        $oldParent->givePermissionTo(Permission::create(['name' => 'perm.old2', 'guard_name' => 'web']));

        $newParent = Role::create(['name' => 'newParent', 'guard_name' => 'web']);
        $newParent->givePermissionTo(Permission::create(['name' => 'perm.new1', 'guard_name' => 'web']));

        $childRole = Role::create(['name' => 'child', 'guard_name' => 'web']);
        $roleInheritance->setParent($childRole, $oldParent);

        $result = $analyzer->analyzeHierarchyChange($childRole, $newParent);

        expect($result)->toHaveKeys([
            'role',
            'old_parent',
            'new_parent',
            'impact_level',
            'permissions_gained',
            'permissions_lost',
            'affected_users_count',
            'reasoning',
        ]);
        expect($result['role'])->toBe('child');
        expect($result['old_parent'])->toBe('oldParent');
        expect($result['new_parent'])->toBe('newParent');
        expect($result['permissions_gained'])->toContain('perm.new1');
        expect($result['permissions_lost'])->toContain('perm.old1', 'perm.old2');
    });

    test('analyzeHierarchyChange handles removal from parent', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        $parent = Role::create(['name' => 'parent', 'guard_name' => 'web']);
        $parent->givePermissionTo(Permission::create(['name' => 'inherited.perm', 'guard_name' => 'web']));

        $child = Role::create(['name' => 'child', 'guard_name' => 'web']);
        $roleInheritance->setParent($child, $parent);

        // Move to no parent
        $result = $analyzer->analyzeHierarchyChange($child, null);

        expect($result['old_parent'])->toBe('parent');
        expect($result['new_parent'])->toBeNull();
        expect($result['permissions_lost'])->toContain('inherited.perm');
        expect($result['permissions_gained'])->toBeEmpty();
    });

    test('analyzeBulkChange returns bulk operation analysis', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        // Add users
        for ($i = 1; $i <= 10; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
            ]);
            $user->assignRole($role);
        }

        $permissions = ['perm1', 'perm2', 'perm3', 'perm4', 'perm5'];

        $result = $analyzer->analyzeBulkChange('grant', $role, $permissions);

        expect($result)->toHaveKeys([
            'operation',
            'role',
            'permission_count',
            'impact_level',
            'affected_users_count',
            'affected_roles',
            'reasoning',
        ]);
        expect($result['operation'])->toBe('grant');
        expect($result['permission_count'])->toBe(5);
        expect($result['affected_users_count'])->toBe(10);
    });

    test('analyzeBulkChange escalates impact for many permissions', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        // Create many permissions
        $permissions = [];
        for ($i = 1; $i <= 15; $i++) {
            $permissions[] = "perm{$i}";
        }

        $result = $analyzer->analyzeBulkChange('revoke', $role, $permissions);

        // Impact should be escalated due to many permissions (>10)
        expect($result['permission_count'])->toBe(15);
        // With 0 users, base impact is None, but escalates to Low due to >10 perms
        expect($result['impact_level'])->toBe(ImpactLevel::Low);
    });

    test('impact level escalates correctly based on user count', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        // Create roles with increasing user counts
        $noneRole = Role::create(['name' => 'none', 'guard_name' => 'web']);
        // 0 users = ImpactLevel::None

        $lowRole = Role::create(['name' => 'low', 'guard_name' => 'web']);
        for ($i = 1; $i <= 5; $i++) {
            $u = User::create(['name' => "Low User {$i}", 'email' => "low{$i}@example.com", 'password' => bcrypt('p')]);
            $u->assignRole($lowRole);
        }
        // 5 users = ImpactLevel::Low

        $mediumRole = Role::create(['name' => 'medium', 'guard_name' => 'web']);
        for ($i = 1; $i <= 25; $i++) {
            $u = User::create(['name' => "Med User {$i}", 'email' => "med{$i}@example.com", 'password' => bcrypt('p')]);
            $u->assignRole($mediumRole);
        }
        // 25 users = ImpactLevel::Medium

        // Test impact levels
        $noneResult = $analyzer->analyzePermissionGrant('perm', $noneRole);
        expect($noneResult['impact_level'])->toBe(ImpactLevel::None);

        $lowResult = $analyzer->analyzePermissionGrant('perm', $lowRole);
        expect($lowResult['impact_level'])->toBe(ImpactLevel::Low);

        $mediumResult = $analyzer->analyzePermissionGrant('perm', $mediumRole);
        expect($mediumResult['impact_level'])->toBe(ImpactLevel::Medium);
    });

    test('reasoning is generated correctly', function (): void {
        $roleInheritance = app(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        // Add users
        for ($i = 1; $i <= 3; $i++) {
            $user = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
            ]);
            $user->assignRole($role);
        }

        $result = $analyzer->analyzePermissionGrant('test.permission', $role);

        expect($result['reasoning'])->toContain('3 user(s) will be affected');
    });
});
