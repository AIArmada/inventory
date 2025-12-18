<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\TeamPermissionService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    // Clear data
    ScopedPermission::query()->delete();
    Permission::query()->delete();
    Role::query()->delete();
    User::query()->delete();

    // Create and authenticate a user
    $user = User::create([
        'name' => 'System User',
        'email' => 'system@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

describe('TeamPermissionService', function (): void {
    test('can be instantiated', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        expect($service)->toBeInstanceOf(TeamPermissionService::class);
    });

    test('grantTeamPermission creates scoped permission for team', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $scopedPermission = $service->grantTeamPermission(
            user: $user,
            permission: 'posts.view',
            teamId: 'team-123'
        );

        expect($scopedPermission)->toBeInstanceOf(ScopedPermission::class);
        expect($scopedPermission->scope_type)->toBe(PermissionScope::Team);
        expect($scopedPermission->scope_id)->toBe('team-123');
        expect($scopedPermission->permissionable_type)->toBe(User::class);
        expect($scopedPermission->permissionable_id)->toBe($user->id);
    });

    test('grantTeamPermission supports conditions', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Conditional User',
            'email' => 'conditional@example.com',
            'password' => bcrypt('password'),
        ]);

        $scopedPermission = $service->grantTeamPermission(
            user: $user,
            permission: 'posts.edit',
            teamId: 'team-456',
            conditions: ['status' => 'draft']
        );

        expect($scopedPermission->conditions)->toBe(['status' => 'draft']);
    });

    test('grantTeamPermission supports expiration', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Expiring User',
            'email' => 'expiring@example.com',
            'password' => bcrypt('password'),
        ]);

        $expiresAt = now()->addDays(30);

        $scopedPermission = $service->grantTeamPermission(
            user: $user,
            permission: 'posts.delete',
            teamId: 'team-789',
            expiresAt: $expiresAt
        );

        expect($scopedPermission->expires_at->format('Y-m-d'))->toBe($expiresAt->format('Y-m-d'));
    });

    test('revokeTeamPermission removes permission from team', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Revoke User',
            'email' => 'revoke@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant permission first
        $service->grantTeamPermission($user, 'posts.view', 'team-123');

        // Verify it exists
        expect(ScopedPermission::count())->toBe(1);

        // Revoke permission
        $deleted = $service->revokeTeamPermission($user, 'posts.view', 'team-123');

        expect($deleted)->toBe(1);
        expect(ScopedPermission::count())->toBe(0);
    });

    test('getTeamPermissions returns all permissions for user in team', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Multi Permission User',
            'email' => 'multi@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant multiple permissions
        $service->grantTeamPermission($user, 'posts.view', 'team-123');
        $service->grantTeamPermission($user, 'posts.edit', 'team-123');
        $service->grantTeamPermission($user, 'posts.delete', 'team-123');

        // Also grant permission in different team
        $service->grantTeamPermission($user, 'posts.view', 'team-456');

        $permissions = $service->getTeamPermissions($user, 'team-123');

        expect($permissions)->toHaveCount(3);
        expect($permissions->pluck('permission.name')->toArray())
            ->toContain('posts.view')
            ->toContain('posts.edit')
            ->toContain('posts.delete');
    });

    test('getTeamsWithPermission returns all teams where user has permission', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Multi Team User',
            'email' => 'multiteam@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant same permission in multiple teams
        $service->grantTeamPermission($user, 'posts.view', 'team-123');
        $service->grantTeamPermission($user, 'posts.view', 'team-456');
        $service->grantTeamPermission($user, 'posts.view', 'team-789');

        // Grant different permission in another team
        $service->grantTeamPermission($user, 'posts.edit', 'team-999');

        $teams = $service->getTeamsWithPermission($user, 'posts.view');

        expect($teams)->toHaveCount(3);
        expect($teams->toArray())
            ->toContain('team-123')
            ->toContain('team-456')
            ->toContain('team-789');
    });

    test('revokeAllTeamPermissions removes all permissions from team', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Revoke All User',
            'email' => 'revokeall@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant multiple permissions
        $service->grantTeamPermission($user, 'posts.view', 'team-123');
        $service->grantTeamPermission($user, 'posts.edit', 'team-123');
        $service->grantTeamPermission($user, 'posts.delete', 'team-123');

        // Also grant permission in different team (should not be revoked)
        $service->grantTeamPermission($user, 'posts.view', 'team-456');

        expect(ScopedPermission::count())->toBe(4);

        // Revoke all permissions from team-123
        $deleted = $service->revokeAllTeamPermissions($user, 'team-123');

        expect($deleted)->toBe(3);
        expect(ScopedPermission::count())->toBe(1);

        // Verify team-456 permission still exists
        $remaining = $service->getTeamPermissions($user, 'team-456');
        expect($remaining)->toHaveCount(1);
    });

    test('copyTeamPermissions copies all permissions to new team', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Copy User',
            'email' => 'copy@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant permissions in source team
        $service->grantTeamPermission($user, 'posts.view', 'team-source');
        $service->grantTeamPermission($user, 'posts.edit', 'team-source');
        $service->grantTeamPermission($user, 'posts.delete', 'team-source', ['status' => 'draft']);

        // Copy to new team
        $count = $service->copyTeamPermissions($user, 'team-source', 'team-dest');

        expect($count)->toBe(3);

        // Verify permissions copied
        $destPermissions = $service->getTeamPermissions($user, 'team-dest');
        expect($destPermissions)->toHaveCount(3);

        // Verify source permissions still exist
        $sourcePermissions = $service->getTeamPermissions($user, 'team-source');
        expect($sourcePermissions)->toHaveCount(3);

        // Verify conditions were copied
        $deletePermission = $destPermissions->first(
            fn ($p) => $p->permission->name === 'posts.delete'
        );
        expect($deletePermission->conditions)->toBe(['status' => 'draft']);
    });

    test('hasTeamPermission delegates to contextual auth', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Check User',
            'email' => 'check@example.com',
            'password' => bcrypt('password'),
        ]);

        // Initially should not have permission
        expect($service->hasTeamPermission($user, 'posts.view', 'team-123'))->toBeFalse();

        // Grant permission
        $service->grantTeamPermission($user, 'posts.view', 'team-123');

        // Now should have permission
        expect($service->hasTeamPermission($user, 'posts.view', 'team-123'))->toBeTrue();

        // Should not have permission in other team
        expect($service->hasTeamPermission($user, 'posts.view', 'team-456'))->toBeFalse();
    });

    test('integer team ids are converted to string', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TeamPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Integer Team User',
            'email' => 'intteam@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant with integer team id
        $scopedPermission = $service->grantTeamPermission($user, 'posts.view', 123);

        expect($scopedPermission->scope_id)->toBe('123');

        // Get with integer team id
        $permissions = $service->getTeamPermissions($user, 123);
        expect($permissions)->toHaveCount(1);

        // Revoke with integer team id
        $deleted = $service->revokeTeamPermission($user, 'posts.view', 123);
        expect($deleted)->toBe(1);
    });
});
