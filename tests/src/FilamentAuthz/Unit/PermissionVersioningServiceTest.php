<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use AIArmada\FilamentAuthz\Services\RollbackResult;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    PermissionSnapshot::query()->delete();
    Role::query()->delete();
    Permission::query()->delete();
    User::query()->delete();

    // Create test user and authenticate
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    // Create some test roles and permissions
    $permissions = ['orders.view', 'orders.create', 'products.view', 'products.create'];
    foreach ($permissions as $perm) {
        Permission::create(['name' => $perm, 'guard_name' => 'web']);
    }

    $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $adminRole->givePermissionTo(['orders.view', 'orders.create', 'products.view', 'products.create']);

    $editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $editorRole->givePermissionTo(['orders.view', 'products.view']);

    test()->service = app(PermissionVersioningService::class);
});

describe('PermissionVersioningService → createSnapshot', function (): void {
    it('creates a snapshot with basic info', function (): void {
        $snapshot = test()->service->createSnapshot('Initial State');

        expect($snapshot)->toBeInstanceOf(PermissionSnapshot::class)
            ->and($snapshot->name)->toBe('Initial State')
            ->and($snapshot->hash)->not->toBeEmpty();
    });

    it('creates a snapshot with description', function (): void {
        $snapshot = test()->service->createSnapshot('Backup', 'Before major changes');

        expect($snapshot->description)->toBe('Before major changes');
    });

    it('captures roles in state', function (): void {
        $snapshot = test()->service->createSnapshot('Roles Test');

        $roles = $snapshot->getRoles();

        expect($roles)->toBeArray()
            ->and(collect($roles)->pluck('name')->toArray())->toContain('admin')
            ->and(collect($roles)->pluck('name')->toArray())->toContain('editor');
    });

    it('captures permissions in state', function (): void {
        $snapshot = test()->service->createSnapshot('Permissions Test');

        $permissions = $snapshot->getPermissions();

        expect($permissions)->toBeArray()
            ->and(collect($permissions)->pluck('name')->toArray())->toContain('orders.view')
            ->and(collect($permissions)->pluck('name')->toArray())->toContain('products.create');
    });

    it('captures assignments in state', function (): void {
        $snapshot = test()->service->createSnapshot('Assignments Test');

        $assignments = $snapshot->getAssignments();

        expect($assignments)->toBeArray()
            ->and($assignments)->not->toBeEmpty();
    });
});

describe('PermissionVersioningService → compare', function (): void {
    it('compares two snapshots with no changes', function (): void {
        $snapshot1 = test()->service->createSnapshot('First');
        $snapshot2 = test()->service->createSnapshot('Second');

        $diff = test()->service->compare($snapshot1, $snapshot2);

        expect($diff['roles']['added'])->toBeEmpty()
            ->and($diff['roles']['removed'])->toBeEmpty()
            ->and($diff['permissions']['added'])->toBeEmpty()
            ->and($diff['permissions']['removed'])->toBeEmpty();
    });

    it('detects added roles', function (): void {
        $snapshot1 = test()->service->createSnapshot('Before');

        Role::create(['name' => 'moderator', 'guard_name' => 'web']);

        $snapshot2 = test()->service->createSnapshot('After');

        $diff = test()->service->compare($snapshot1, $snapshot2);

        expect($diff['roles']['added'])->toContain('moderator');
    });

    it('detects removed roles', function (): void {
        $snapshot1 = test()->service->createSnapshot('Before');

        Role::where('name', 'editor')->delete();

        $snapshot2 = test()->service->createSnapshot('After');

        $diff = test()->service->compare($snapshot1, $snapshot2);

        expect($diff['roles']['removed'])->toContain('editor');
    });

    it('detects added permissions', function (): void {
        $snapshot1 = test()->service->createSnapshot('Before');

        Permission::create(['name' => 'users.view', 'guard_name' => 'web']);

        $snapshot2 = test()->service->createSnapshot('After');

        $diff = test()->service->compare($snapshot1, $snapshot2);

        expect($diff['permissions']['added'])->toContain('users.view');
    });

    it('detects removed permissions', function (): void {
        $snapshot1 = test()->service->createSnapshot('Before');

        Permission::where('name', 'products.create')->delete();

        $snapshot2 = test()->service->createSnapshot('After');

        $diff = test()->service->compare($snapshot1, $snapshot2);

        expect($diff['permissions']['removed'])->toContain('products.create');
    });
});

describe('PermissionVersioningService → previewRollback', function (): void {
    it('previews rollback changes', function (): void {
        $snapshot = test()->service->createSnapshot('Original');

        // Make changes
        Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
        Role::create(['name' => 'moderator', 'guard_name' => 'web']);

        $preview = test()->service->previewRollback($snapshot);

        expect($preview)->toBeArray()
            ->and($preview['roles'])->toHaveKey('added')
            ->and($preview['permissions'])->toHaveKey('added');
    });
});

describe('PermissionVersioningService → rollback', function (): void {
    it('performs dry run without making changes', function (): void {
        $snapshot = test()->service->createSnapshot('Original');

        Permission::create(['name' => 'users.view', 'guard_name' => 'web']);

        $result = test()->service->rollback($snapshot, dryRun: true);

        expect($result)->toBeInstanceOf(RollbackResult::class)
            ->and($result->success)->toBeTrue()
            ->and($result->isDryRun)->toBeTrue()
            ->and($result->preview)->not->toBeNull();

        // Permission should still exist after dry run
        expect(Permission::where('name', 'users.view')->exists())->toBeTrue();
    });
});

describe('PermissionVersioningService → listSnapshots', function (): void {
    it('returns all snapshots', function (): void {
        test()->service->createSnapshot('First');
        test()->service->createSnapshot('Second');
        test()->service->createSnapshot('Third');

        $snapshots = test()->service->listSnapshots();

        expect($snapshots)->toBeInstanceOf(Collection::class)
            ->and($snapshots->count())->toBe(3);
    });

    it('returns snapshots ordered by created_at descending', function (): void {
        test()->service->createSnapshot('First');
        sleep(1);
        test()->service->createSnapshot('Second');
        sleep(1);
        test()->service->createSnapshot('Third');

        $snapshots = test()->service->listSnapshots();

        expect($snapshots->first()->name)->toBe('Third')
            ->and($snapshots->last()->name)->toBe('First');
    });
});

describe('PermissionVersioningService → deleteSnapshot', function (): void {
    it('deletes a snapshot', function (): void {
        $snapshot = test()->service->createSnapshot('To Delete');

        $result = test()->service->deleteSnapshot($snapshot);

        expect($result)->toBeTrue()
            ->and(PermissionSnapshot::find($snapshot->id))->toBeNull();
    });
});

describe('RollbackResult value object', function (): void {
    it('creates with all properties', function (): void {
        $snapshot = test()->service->createSnapshot('Test');

        $result = new RollbackResult(
            success: true,
            snapshot: $snapshot,
            preview: ['roles' => ['added' => []]],
            restoredAt: now(),
            isDryRun: false
        );

        expect($result->success)->toBeTrue()
            ->and($result->snapshot)->toBe($snapshot)
            ->and($result->preview)->toBeArray()
            ->and($result->isDryRun)->toBeFalse();
    });
});
