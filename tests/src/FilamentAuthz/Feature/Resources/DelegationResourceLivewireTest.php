<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentAuthz\Fixtures\AuthzUser;
use AIArmada\FilamentAuthz\Models\Delegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\CreateDelegation;
use AIArmada\FilamentAuthz\Resources\DelegationResource\Pages\ListDelegations;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('filament-authz.enterprise.delegation.enabled', true);
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

    Schema::dropIfExists('authz_delegations');
    Schema::create('authz_delegations', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('delegator_id');
        $table->foreignUuid('delegatee_id');
        $table->string('permission');
        $table->boolean('can_redelegate')->default(false);
        $table->timestamp('expires_at')->nullable();
        $table->timestamp('revoked_at')->nullable();
        $table->timestamps();

        $table->index('delegator_id');
        $table->index('delegatee_id');
        $table->index('expires_at');
        $table->index('revoked_at');
        $table->index('created_at');
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

    Filament::setCurrentPanel('admin');

    $this->actingAs(AuthzUser::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]));
});

test('list page renders and shows delegations', function (): void {
    $delegator = AuthzUser::create([
        'name' => 'Delegator',
        'email' => 'delegator@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = AuthzUser::create([
        'name' => 'Delegatee',
        'email' => 'delegatee@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegation = Delegation::create([
        'delegator_id' => $delegator->id,
        'delegatee_id' => $delegatee->id,
        'permission' => 'user.view',
        'can_redelegate' => false,
    ]);

    Livewire::test(ListDelegations::class)
        ->assertSuccessful()
        ->assertTableColumnExists('delegator.name')
        ->assertTableColumnExists('delegatee.name')
        ->assertTableColumnExists('permission')
        ->assertTableColumnExists('active')
        ->assertCanSeeTableRecords([$delegation]);
});

test('active filter hides revoked delegations', function (): void {
    $delegator = AuthzUser::create([
        'name' => 'Delegator 2',
        'email' => 'delegator2@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = AuthzUser::create([
        'name' => 'Delegatee 2',
        'email' => 'delegatee2@example.com',
        'password' => bcrypt('password'),
    ]);

    $active = Delegation::create([
        'delegator_id' => $delegator->id,
        'delegatee_id' => $delegatee->id,
        'permission' => 'user.*',
        'can_redelegate' => true,
    ]);

    $revoked = Delegation::create([
        'delegator_id' => $delegator->id,
        'delegatee_id' => $delegatee->id,
        'permission' => 'order.view',
        'can_redelegate' => false,
        'revoked_at' => now(),
    ]);

    Livewire::test(ListDelegations::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$revoked]);
});

test('list page can revoke an active delegation', function (): void {
    $delegator = AuthzUser::create([
        'name' => 'Delegator 3',
        'email' => 'delegator3@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = AuthzUser::create([
        'name' => 'Delegatee 3',
        'email' => 'delegatee3@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegation = Delegation::create([
        'delegator_id' => $delegator->id,
        'delegatee_id' => $delegatee->id,
        'permission' => 'product.view',
        'can_redelegate' => false,
    ]);

    Livewire::test(ListDelegations::class)
        ->assertSuccessful()
        ->assertActionExists(TestAction::make('revoke')->table($delegation))
        ->callAction(TestAction::make('revoke')->table($delegation))
        ->assertHasNoActionErrors();

    expect($delegation->refresh()->revoked_at)->not->toBeNull();
});

test('create page can create a delegation', function (): void {
    $delegator = AuthzUser::create([
        'name' => 'Delegator 4',
        'email' => 'delegator4@example.com',
        'password' => bcrypt('password'),
    ]);

    $delegatee = AuthzUser::create([
        'name' => 'Delegatee 4',
        'email' => 'delegatee4@example.com',
        'password' => bcrypt('password'),
    ]);

    Livewire::test(CreateDelegation::class)
        ->assertSuccessful()
        ->fillForm([
            'delegator_id' => $delegator->id,
            'delegatee_id' => $delegatee->id,
            'permission' => 'customer.view',
            'can_redelegate' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $delegation = Delegation::query()->sole();

    expect($delegation)
        ->delegator_id->toBe($delegator->id)
        ->delegatee_id->toBe($delegatee->id)
        ->permission->toBe('customer.view')
        ->can_redelegate->toBeTrue();
});
