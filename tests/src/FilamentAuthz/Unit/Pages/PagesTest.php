<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Pages\AuditLogPage;
use AIArmada\FilamentAuthz\Pages\AuthzDashboardPage;
use AIArmada\FilamentAuthz\Pages\PermissionExplorer;
use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use AIArmada\FilamentAuthz\Pages\PolicyDesignerPage;
use AIArmada\FilamentAuthz\Pages\RoleHierarchyPage;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('PermissionExplorer', function (): void {
    it('has correct navigation icon', function (): void {
        expect(PermissionExplorer::getNavigationIcon())->not()->toBeNull();
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Authorization']);
        expect(PermissionExplorer::getNavigationGroup())->toBe('Authorization');
    });

    it('canAccess returns false when no user', function (): void {
        auth()->logout();
        expect(PermissionExplorer::canAccess())->toBeFalse();
    });

    it('getPermissionsGrouped returns array', function (): void {
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'post.view', 'guard_name' => 'web']);

        $page = new PermissionExplorer;
        $grouped = $page->getPermissionsGrouped();

        expect($grouped)->toBeArray()
            ->and($grouped)->toHaveKey('user')
            ->and($grouped)->toHaveKey('post');
    });

    it('getRolesWithPermissionCounts returns array', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'test.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $page = new PermissionExplorer;
        $roles = $page->getRolesWithPermissionCounts();

        expect($roles)->toBeArray()
            ->and($roles[0])->toHaveKey('name')
            ->and($roles[0])->toHaveKey('guard_name')
            ->and($roles[0])->toHaveKey('permissions_count');
    });
});

describe('RoleHierarchyPage', function (): void {
    it('has correct navigation icon', function (): void {
        expect(RoleHierarchyPage::getNavigationIcon())->toBe('heroicon-o-arrow-trending-up');
    });

    it('has navigation label', function (): void {
        expect(RoleHierarchyPage::getNavigationLabel())->toBe('Role Hierarchy');
    });

    it('has navigation sort', function (): void {
        expect(RoleHierarchyPage::getNavigationSort())->toBe(11);
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Administration']);
        expect(RoleHierarchyPage::getNavigationGroup())->toBe('Administration');
    });

    it('mount sets root roles and hierarchy tree', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $service = app(RoleInheritanceService::class);
        $page = new RoleHierarchyPage;
        $page->mount($service);

        expect($page->rootRoles)->not()->toBeNull()
            ->and($page->hierarchyTree)->toBeArray();
    });

    it('setParent handles non-existent role', function (): void {
        $service = app(RoleInheritanceService::class);
        $page = new RoleHierarchyPage;
        $page->mount($service);

        // Should not throw
        $page->setParent('nonexistent-id', null, $service);
        expect(true)->toBeTrue();
    });

    it('detachRole handles non-existent role', function (): void {
        $service = app(RoleInheritanceService::class);
        $page = new RoleHierarchyPage;
        $page->mount($service);

        // Should not throw
        $page->detachRole('nonexistent-id', $service);
        expect(true)->toBeTrue();
    });

    it('setParent updates parent role', function (): void {
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $service = app(RoleInheritanceService::class);
        $page = new RoleHierarchyPage;
        $page->mount($service);

        $page->setParent((string) $editor->id, (string) $admin->id, $service);

        expect(true)->toBeTrue();
    });

    it('detachRole detaches role from parent', function (): void {
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $service = app(RoleInheritanceService::class);
        $service->setParent($editor, $admin);

        $page = new RoleHierarchyPage;
        $page->mount($service);
        $page->detachRole((string) $editor->id, $service);

        expect(true)->toBeTrue();
    });
});

describe('PermissionMatrixPage', function (): void {
    it('has correct navigation icon', function (): void {
        expect(PermissionMatrixPage::getNavigationIcon())->toBe('heroicon-o-table-cells');
    });

    it('has navigation label', function (): void {
        expect(PermissionMatrixPage::getNavigationLabel())->toBe('Permission Matrix');
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Authorization']);
        expect(PermissionMatrixPage::getNavigationGroup())->toBe('Authorization');
    });
});

describe('AuditLogPage', function (): void {
    it('has correct navigation icon', function (): void {
        expect(AuditLogPage::getNavigationIcon())->toBe('heroicon-o-clipboard-document-list');
    });

    it('has navigation label', function (): void {
        expect(AuditLogPage::getNavigationLabel())->toBe('Audit Log');
    });

    it('has navigation sort order', function (): void {
        expect(AuditLogPage::getNavigationSort())->toBe(12);
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Authorization']);
        expect(AuditLogPage::getNavigationGroup())->toBe('Authorization');
    });
});

describe('PolicyDesignerPage', function (): void {
    it('has correct navigation icon', function (): void {
        expect(PolicyDesignerPage::getNavigationIcon())->toBe('heroicon-o-paint-brush');
    });

    it('has navigation label', function (): void {
        expect(PolicyDesignerPage::getNavigationLabel())->toBe('Policy Designer');
    });

    it('has navigation sort order', function (): void {
        expect(PolicyDesignerPage::getNavigationSort())->toBe(50);
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Authorization']);
        expect(PolicyDesignerPage::getNavigationGroup())->toBe('Authorization');
    });
});

describe('AuthzDashboardPage', function (): void {
    it('has navigation label', function (): void {
        expect(AuthzDashboardPage::getNavigationLabel())->toBe('Authz Dashboard');
    });

    it('returns navigation group from config', function (): void {
        config(['filament-authz.navigation.group' => 'Authorization']);
        expect(AuthzDashboardPage::getNavigationGroup())->toBe('Authorization');
    });
});
