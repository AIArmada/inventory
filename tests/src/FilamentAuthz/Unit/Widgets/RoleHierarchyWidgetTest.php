<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Widgets\RoleHierarchyWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

describe('RoleHierarchyWidget', function (): void {
    it('has correct view', function (): void {
        $widget = new RoleHierarchyWidget;

        $reflection = new ReflectionClass($widget);
        $viewProperty = $reflection->getProperty('view');
        $viewProperty->setAccessible(true);

        expect($viewProperty->getValue($widget))->toBe('filament-authz::widgets.role-hierarchy');
    });

    it('has correct sort order', function (): void {
        expect(RoleHierarchyWidget::getSort())->toBe(2);
    });

    it('returns empty hierarchy when no roles exist', function (): void {
        $widget = new RoleHierarchyWidget;
        $hierarchy = $widget->getHierarchy();

        expect($hierarchy)->toBeArray();
    });

    it('returns hierarchy with roles', function (): void {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $widget = new RoleHierarchyWidget;
        $hierarchy = $widget->getHierarchy();

        expect($hierarchy)->toBeArray()
            ->and($hierarchy)->not->toBeEmpty();
    });

    it('builds node with correct structure', function (): void {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $service = app(RoleInheritanceService::class);
        $widget = new RoleHierarchyWidget;

        $reflection = new ReflectionClass($widget);
        $method = $reflection->getMethod('buildNode');
        $method->setAccessible(true);

        $node = $method->invoke($widget, $role, $service, 0);

        expect($node)->toHaveKeys(['id', 'name', 'level', 'permission_count', 'children'])
            ->and($node['name'])->toBe('admin')
            ->and($node['level'])->toBe(0)
            ->and($node['permission_count'])->toBe(1)
            ->and($node['children'])->toBeArray();
    });

    it('builds nested hierarchy with children', function (): void {
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $service = app(RoleInheritanceService::class);
        $service->setParent($editor, $admin);

        $widget = new RoleHierarchyWidget;
        $hierarchy = $widget->getHierarchy();

        // Find admin in hierarchy
        $adminNode = collect($hierarchy)->first(fn ($node) => $node['name'] === 'admin');

        expect($adminNode)->not->toBeNull()
            ->and($adminNode['children'])->toHaveCount(1)
            ->and($adminNode['children'][0]['name'])->toBe('editor')
            ->and($adminNode['children'][0]['level'])->toBe(1);
    });
});
