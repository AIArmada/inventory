<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Services\PermissionGroupService;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    PermissionGroup::query()->delete();
    Permission::query()->delete();
    User::query()->delete();

    // Create permissions
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'products.view', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

describe('PermissionGroupsCommand', function (): void {
    describe('list action', function (): void {
        it('lists all groups when groups exist', function (): void {
            // Create some groups
            $service = app(PermissionGroupService::class);
            $service->createGroup('Admin', 'Administrator permissions');
            $service->createGroup('Editor', 'Editor permissions');

            artisan('authz:groups', ['action' => 'list'])
                ->assertExitCode(0);
        });

        it('shows info message when no groups exist', function (): void {
            artisan('authz:groups', ['action' => 'list'])
                ->expectsOutput('No permission groups found.')
                ->assertExitCode(0);
        });
    });

    describe('show action', function (): void {
        it('shows group details with --group option', function (): void {
            $service = app(PermissionGroupService::class);
            $group = $service->createGroup('Admin', 'Administrator permissions');

            artisan('authz:groups', ['action' => 'show', '--group' => 'admin'])
                ->assertExitCode(0);
        });

        it('shows error for non-existent group', function (): void {
            artisan('authz:groups', ['action' => 'show', '--group' => 'nonexistent'])
                ->expectsOutput('Group not found: nonexistent')
                ->assertExitCode(1);
        });

        it('returns error when no groups exist and no group option', function (): void {
            artisan('authz:groups', ['action' => 'show'])
                ->expectsOutput('No permission groups found.')
                ->assertExitCode(1);
        });
    });

    describe('sync action', function (): void {
        it('shows error for non-existent group', function (): void {
            artisan('authz:groups', ['action' => 'sync', '--group' => 'nonexistent'])
                ->expectsOutput('Group not found: nonexistent')
                ->assertExitCode(1);
        });

        it('returns error when no groups exist and no group option', function (): void {
            artisan('authz:groups', ['action' => 'sync'])
                ->expectsOutput('No permission groups found.')
                ->assertExitCode(1);
        });
    });

    describe('delete action', function (): void {
        it('shows error for non-existent group', function (): void {
            artisan('authz:groups', ['action' => 'delete', '--group' => 'nonexistent'])
                ->expectsOutput('Group not found: nonexistent')
                ->assertExitCode(1);
        });

        it('prevents deletion of system groups', function (): void {
            // Create a system group
            PermissionGroup::create([
                'name' => 'System Group',
                'slug' => 'system-group',
                'guard_name' => 'web',
                'is_system' => true,
            ]);

            artisan('authz:groups', ['action' => 'delete', '--group' => 'system-group'])
                ->expectsOutput('Cannot delete a system group.')
                ->assertExitCode(1);
        });

        it('returns error when no groups exist and no group option', function (): void {
            artisan('authz:groups', ['action' => 'delete'])
                ->expectsOutput('No permission groups found.')
                ->assertExitCode(1);
        });
    });

    describe('unknown action', function (): void {
        it('handles unknown action', function (): void {
            artisan('authz:groups', ['action' => 'unknown'])
                ->expectsOutput('Unknown action: unknown')
                ->assertExitCode(1);
        });
    });

    describe('command registration', function (): void {
        it('is registered in artisan', function (): void {
            expect(Artisan::all())->toHaveKey('authz:groups');
        });

        it('has correct signature', function (): void {
            $command = Artisan::all()['authz:groups'];

            expect($command->getName())->toBe('authz:groups');
        });
    });
});
