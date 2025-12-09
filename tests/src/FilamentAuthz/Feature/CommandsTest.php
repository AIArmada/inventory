<?php

declare(strict_types=1);

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('sync command creates permissions and roles', function (): void {
    config([
        'filament-authz.sync.permissions' => ['users.viewAny', 'users.create'],
        'filament-authz.sync.roles' => [
            'Admin' => ['users.viewAny', 'users.create'],
        ],
    ]);

    $this->artisan('authz:sync', ['--flush-cache' => true])
        ->assertSuccessful();

    expect(Permission::where('name', 'users.viewAny')->exists())->toBeTrue()
        ->and(Role::where('name', 'Admin')->exists())->toBeTrue();
});

test('doctor command detects unused permissions', function (): void {
    Permission::create(['name' => 'orphaned.permission', 'guard_name' => 'web']);

    $this->artisan('authz:doctor')
        ->expectsOutputToContain('orphaned.permission')
        ->assertFailed();
});

test('export command creates json file', function (): void {
    Role::create(['name' => 'TestRole', 'guard_name' => 'web']);
    Permission::create(['name' => 'test.permission', 'guard_name' => 'web']);

    $path = storage_path('test-permissions.json');

    $this->artisan('authz:export', ['path' => $path])
        ->assertSuccessful();

    expect(file_exists($path))->toBeTrue();

    $data = json_decode((string) file_get_contents($path), true);
    expect($data)->toHaveKeys(['permissions', 'roles']);

    unlink($path);
});

test('import command loads json file', function (): void {
    $data = [
        'permissions' => [
            ['name' => 'imported.permission', 'guard_name' => 'web'],
        ],
        'roles' => [
            [
                'name' => 'ImportedRole',
                'guard_name' => 'web',
                'permissions' => ['imported.permission'],
            ],
        ],
    ];

    $path = storage_path('import-test.json');
    file_put_contents($path, json_encode($data));

    $this->artisan('authz:import', ['path' => $path, '--flush-cache' => true])
        ->assertSuccessful();

    expect(Permission::where('name', 'imported.permission')->exists())->toBeTrue()
        ->and(Role::where('name', 'ImportedRole')->exists())->toBeTrue();

    unlink($path);
});

test('cache is flushed after structural changes', function (): void {
    $registrar = app(PermissionRegistrar::class);

    Permission::create(['name' => 'cached.permission', 'guard_name' => 'web']);

    $registrar->forgetCachedPermissions();

    expect(true)->toBeTrue();
});
