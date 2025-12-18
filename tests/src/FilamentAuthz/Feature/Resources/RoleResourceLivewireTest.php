<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentAuthz\Fixtures\AuthzUser;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\CreateRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\EditRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\ListRoles;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    config()->set('filament-authz.user_model', AuthzUser::class);
    config()->set('auth.defaults.guard', 'web');

    Schema::dropIfExists('authz_users');
    Schema::create('authz_users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::dropIfExists('role_has_permissions');
    Schema::dropIfExists('model_has_permissions');
    Schema::dropIfExists('model_has_roles');
    Schema::dropIfExists('permissions');
    Schema::dropIfExists('roles');

    Schema::create('permissions', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();

        $table->unique(['name', 'guard_name']);
    });

    Schema::create('roles', function (Blueprint $table): void {
        $table->bigIncrements('id');
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();

        $table->unique(['name', 'guard_name']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');

        $table->primary(['permission_id', 'role_id']);
    });

    Schema::create('model_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->uuid('model_id');

        $table->primary(['permission_id', 'model_id', 'model_type']);
        $table->index(['model_id', 'model_type']);
    });

    Schema::create('model_has_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->uuid('model_id');

        $table->primary(['role_id', 'model_id', 'model_type']);
        $table->index(['model_id', 'model_type']);
    });

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Filament::setCurrentPanel('admin');

    $this->actingAs(AuthzUser::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]));
});

test('list page renders and shows roles', function (): void {
    $permission = Permission::create([
        'name' => 'order.view',
        'guard_name' => 'web',
    ]);

    $role = Role::create([
        'name' => 'Manager',
        'guard_name' => 'web',
    ]);

    $role->syncPermissions([$permission->id]);

    Livewire::test(ListRoles::class)
        ->assertSuccessful()
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('guard_name')
        ->assertTableColumnExists('permissions_count')
        ->assertCanSeeTableRecords([$role]);
});

test('create page can create a role with permissions', function (): void {
    $permissionA = Permission::create([
        'name' => 'order.view',
        'guard_name' => 'web',
    ]);

    $permissionB = Permission::create([
        'name' => 'order.update',
        'guard_name' => 'web',
    ]);

    Livewire::test(CreateRole::class)
        ->assertSuccessful()
        ->fillForm([
            'name' => 'Support',
            'guard_name' => 'web',
            'permissions' => [$permissionA->id, $permissionB->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::query()->where('name', 'Support')->sole();

    expect($role->guard_name)->toBe('web');
    expect($role->permissions()->pluck('id')->all())
        ->toMatchArray([$permissionA->id, $permissionB->id]);
});

test('edit page can update permissions', function (): void {
    $permissionA = Permission::create([
        'name' => 'order.view',
        'guard_name' => 'web',
    ]);

    $permissionB = Permission::create([
        'name' => 'order.update',
        'guard_name' => 'web',
    ]);

    $role = Role::create([
        'name' => 'Ops',
        'guard_name' => 'web',
    ]);

    $role->syncPermissions([$permissionA->id]);

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->assertSuccessful()
        ->fillForm([
            'name' => 'Ops',
            'guard_name' => 'web',
            'permissions' => [$permissionB->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->refresh()->permissions()->pluck('id')->all())
        ->toMatchArray([$permissionB->id]);
});
