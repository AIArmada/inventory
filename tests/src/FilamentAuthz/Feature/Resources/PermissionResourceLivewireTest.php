<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentAuthz\Fixtures\AuthzUser;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\EditPermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\ListPermissions;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

test('list page renders and shows permissions', function (): void {
    $permission = Permission::create([
        'name' => 'order.view',
        'guard_name' => 'web',
    ]);

    Livewire::test(ListPermissions::class)
        ->assertSuccessful()
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('guard_name')
        ->assertCanSeeTableRecords([$permission]);
});

test('create page can create a permission', function (): void {
    Livewire::test(CreatePermission::class)
        ->assertSuccessful()
        ->fillForm([
            'name' => 'order.update',
            'guard_name' => 'web',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $permission = Permission::query()->sole();

    expect($permission)
        ->name->toBe('order.update')
        ->guard_name->toBe('web');
});

test('edit page can update a permission', function (): void {
    $permission = Permission::create([
        'name' => 'order.view',
        'guard_name' => 'web',
    ]);

    Livewire::test(EditPermission::class, ['record' => $permission->getKey()])
        ->assertSuccessful()
        ->fillForm([
            'name' => 'order.read',
            'guard_name' => 'web',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($permission->refresh()->name)->toBe('order.read');
});
