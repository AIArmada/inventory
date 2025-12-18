<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\RoleTemplateCommand;
use AIArmada\FilamentAuthz\Models\RoleTemplate;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RoleTemplate::query()->delete();
    Role::query()->delete();
    Permission::query()->delete();

    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
});

describe('RoleTemplateCommand command properties', function (): void {
    it('has correct signature', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new ReflectionClass($command);
        $signature = $reflection->getProperty('signature');
        $signature->setAccessible(true);

        expect($signature->getValue($command))->toContain('authz:templates');
    });

    it('has correct description', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new ReflectionClass($command);
        $description = $reflection->getProperty('description');
        $description->setAccessible(true);

        expect($description->getValue($command))->toBe('Manage role templates');
    });
});

describe('RoleTemplateCommand listTemplates', function (): void {
    it('displays warning when no templates exist', function (): void {
        $this->artisan('authz:templates', ['action' => 'list'])
            ->assertSuccessful();
    });

    it('displays templates in table format', function (): void {
        RoleTemplate::create([
            'name' => 'Admin Template',
            'slug' => 'admin-template',
            'guard_name' => 'web',
            'default_permissions' => ['users.view', 'users.create'],
            'is_system' => true,
        ]);

        $this->artisan('authz:templates', ['action' => 'list'])
            ->assertSuccessful();
    });
});

describe('RoleTemplateCommand createRoleFromTemplate', function (): void {
    it('fails when template not found', function (): void {
        $this->artisan('authz:templates', [
            'action' => 'create-role',
            '--template' => 'non-existent',
            '--role' => 'new-role',
        ])->assertFailed();
    });

    it('creates role from template', function (): void {
        RoleTemplate::create([
            'name' => 'Manager Template',
            'slug' => 'manager-template',
            'guard_name' => 'web',
            'default_permissions' => ['users.view', 'orders.view'],
            'is_system' => false,
        ]);

        $this->artisan('authz:templates', [
            'action' => 'create-role',
            '--template' => 'manager-template',
            '--role' => 'new-manager',
        ])->assertSuccessful();

        expect(Role::where('name', 'new-manager')->exists())->toBeTrue();
    });
});

describe('RoleTemplateCommand syncAllRoles', function (): void {
    it('fails when template not found', function (): void {
        $this->artisan('authz:templates', [
            'action' => 'sync-all',
            '--template' => 'non-existent',
        ])->assertFailed();
    });

    it('syncs all roles from template', function (): void {
        $template = RoleTemplate::create([
            'name' => 'Worker Template',
            'slug' => 'worker-template',
            'guard_name' => 'web',
            'default_permissions' => ['users.view'],
            'is_system' => false,
        ]);

        $role = Role::create(['name' => 'worker', 'guard_name' => 'web']);
        $role->template_id = $template->id;
        $role->save();

        $this->artisan('authz:templates', [
            'action' => 'sync-all',
            '--template' => 'worker-template',
        ])->assertSuccessful();
    });
});

describe('RoleTemplateCommand getPermissionOptions', function (): void {
    it('returns all permissions as options', function (): void {
        $command = new RoleTemplateCommand;
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getPermissionOptions');
        $method->setAccessible(true);

        $options = $method->invoke($command);

        expect($options)->toBeArray()
            ->and($options)->toHaveKey('users.view')
            ->and($options)->toHaveKey('users.create')
            ->and($options)->toHaveKey('orders.view');
    });
});

describe('RoleTemplateCommand with service', function (): void {
    it('uses RoleTemplateService for listing', function (): void {
        $mockService = Mockery::mock(RoleTemplateService::class);
        $mockService->shouldReceive('getActiveTemplates')
            ->once()
            ->andReturn(new Illuminate\Database\Eloquent\Collection);

        $this->app->instance(RoleTemplateService::class, $mockService);

        $this->artisan('authz:templates', ['action' => 'list'])
            ->assertSuccessful();
    });

    it('uses findBySlug for template lookup', function (): void {
        $template = RoleTemplate::create([
            'name' => 'Test Template',
            'slug' => 'test-template',
            'guard_name' => 'web',
            'default_permissions' => [],
            'is_system' => false,
        ]);

        $mockService = Mockery::mock(RoleTemplateService::class);
        $mockService->shouldReceive('findBySlug')
            ->with('test-template')
            ->once()
            ->andReturn($template);
        $mockService->shouldReceive('createRoleFromTemplate')
            ->once()
            ->andReturn(Role::create(['name' => 'created-role', 'guard_name' => 'web']));

        $this->app->instance(RoleTemplateService::class, $mockService);

        $this->artisan('authz:templates', [
            'action' => 'create-role',
            '--template' => 'test-template',
            '--role' => 'created-role',
        ])->assertSuccessful();
    });
});
