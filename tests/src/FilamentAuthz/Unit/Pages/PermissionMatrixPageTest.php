<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Pages\PermissionMatrixPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::query()->delete();
    Permission::query()->delete();

    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'products.view', 'guard_name' => 'web']);

    $this->adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $this->managerRole = Role::create(['name' => 'manager', 'guard_name' => 'web']);

    $this->adminRole->givePermissionTo(['users.view', 'users.create', 'users.update']);
});

describe('PermissionMatrixPage Static Properties', function (): void {
    it('has correct navigation icon', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $prop = $reflection->getProperty('navigationIcon');
        $prop->setAccessible(true);

        expect($prop->getValue())->toBe('heroicon-o-table-cells');
    });

    it('has correct title', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $prop = $reflection->getProperty('title');
        $prop->setAccessible(true);

        expect($prop->getValue())->toBe('Permission Matrix');
    });

    it('has correct navigation label', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $prop = $reflection->getProperty('navigationLabel');
        $prop->setAccessible(true);

        expect($prop->getValue())->toBe('Permission Matrix');
    });

    it('has correct navigation sort', function (): void {
        $reflection = new ReflectionClass(PermissionMatrixPage::class);
        $prop = $reflection->getProperty('navigationSort');
        $prop->setAccessible(true);

        expect($prop->getValue())->toBe(10);
    });

    it('returns navigation group from config', function (): void {
        $group = PermissionMatrixPage::getNavigationGroup();

        expect($group)->toBe(config('filament-authz.navigation.group', 'Administration'));
    });
});

describe('PermissionMatrixPage mount', function (): void {
    it('loads all permissions and roles on mount', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        expect($page->allPermissions)->toHaveCount(6)
            ->and($page->allRoles)->toHaveCount(2);
    });

    it('groups permissions by prefix', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        expect($page->groupedPermissions)->toHaveKey('users')
            ->toHaveKey('orders')
            ->toHaveKey('products');

        expect($page->groupedPermissions['users'])->toHaveCount(3);
        expect($page->groupedPermissions['orders'])->toHaveCount(2);
        expect($page->groupedPermissions['products'])->toHaveCount(1);
    });
});

describe('PermissionMatrixPage selectRole', function (): void {
    it('selects a role and loads its permissions', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        $page->selectRole((string) $this->adminRole->id);

        expect($page->selectedRole)->toBe((string) $this->adminRole->id);

        $usersViewPerm = Permission::where('name', 'users.view')->first();
        $ordersViewPerm = Permission::where('name', 'orders.view')->first();

        expect($page->permissions[$usersViewPerm->id])->toBeTrue()
            ->and($page->permissions[$ordersViewPerm->id])->toBeFalse();
    });

    it('handles invalid role id gracefully', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        $page->selectRole('invalid-uuid');

        expect($page->selectedRole)->toBe('invalid-uuid')
            ->and($page->permissions)->toBeEmpty();
    });
});

describe('PermissionMatrixPage togglePermission', function (): void {
    it('toggles permission from false to true', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();
        $page->selectRole((string) $this->adminRole->id);

        $ordersViewPerm = Permission::where('name', 'orders.view')->first();

        expect($page->permissions[$ordersViewPerm->id])->toBeFalse();

        $page->togglePermission((string) $ordersViewPerm->id);

        expect($page->permissions[$ordersViewPerm->id])->toBeTrue();
    });

    it('toggles permission from true to false', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();
        $page->selectRole((string) $this->adminRole->id);

        $usersViewPerm = Permission::where('name', 'users.view')->first();

        expect($page->permissions[$usersViewPerm->id])->toBeTrue();

        $page->togglePermission((string) $usersViewPerm->id);

        expect($page->permissions[$usersViewPerm->id])->toBeFalse();
    });

    it('handles non-existent permission id', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        $page->togglePermission('non-existent-id');

        expect($page->permissions['non-existent-id'])->toBeTrue();
    });
});

describe('PermissionMatrixPage savePermissions', function (): void {
    it('does nothing when no role selected', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        $page->savePermissions();

        expect(true)->toBeTrue();
    });

    it('syncs permissions to selected role', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();
        $page->selectRole((string) $this->adminRole->id);

        $ordersViewPerm = Permission::where('name', 'orders.view')->first();
        $page->togglePermission((string) $ordersViewPerm->id);

        $page->savePermissions();

        $this->adminRole->refresh();
        expect($this->adminRole->hasPermissionTo('orders.view'))->toBeTrue();
    });

    it('removes permissions when toggled off', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();
        $page->selectRole((string) $this->adminRole->id);

        $usersViewPerm = Permission::where('name', 'users.view')->first();
        $page->togglePermission((string) $usersViewPerm->id);

        $page->savePermissions();

        $this->adminRole->refresh();
        expect($this->adminRole->hasPermissionTo('users.view'))->toBeFalse();
    });
});

describe('PermissionMatrixPage getSelectedRoleName', function (): void {
    it('returns null when no role selected', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        expect($page->getSelectedRoleName())->toBeNull();
    });

    it('returns role name when role is selected', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();
        $page->selectRole((string) $this->adminRole->id);

        expect($page->getSelectedRoleName())->toBe('admin');
    });
});

describe('PermissionMatrixPage getMatrixData', function (): void {
    it('returns grouped permissions structure', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        expect($page->groupedPermissions)->toBeArray()
            ->and($page->groupedPermissions)->toHaveKey('users')
            ->and($page->groupedPermissions)->toHaveKey('orders')
            ->and($page->groupedPermissions)->toHaveKey('products');
    });
});

describe('PermissionMatrixPage getHeaderActions', function (): void {
    it('returns header actions array', function (): void {
        $page = new PermissionMatrixPage;
        $page->mount();

        $reflection = new ReflectionClass($page);
        $method = $reflection->getMethod('getHeaderActions');
        $method->setAccessible(true);

        $actions = $method->invoke($page);

        expect($actions)->toBeArray()
            ->and($actions)->toHaveCount(2);
    });
});
