<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\TemporalPermissionService;
use Carbon\Carbon;
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

describe('TemporalPermissionService', function (): void {
    test('can be instantiated', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        expect($service)->toBeInstanceOf(TemporalPermissionService::class);
    });

    test('grantTemporaryPermission creates scoped permission with expiration', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Temp User',
            'email' => 'temp@example.com',
            'password' => bcrypt('password'),
        ]);

        $expiresAt = Carbon::now()->addHours(2);

        $scopedPermission = $service->grantTemporaryPermission(
            user: $user,
            permission: 'posts.edit',
            expiresAt: $expiresAt
        );

        expect($scopedPermission)->toBeInstanceOf(ScopedPermission::class);
        // scope_type may be string or enum depending on Laravel version
        $scopeType = $scopedPermission->scope_type;
        $expectedScope = $scopeType instanceof PermissionScope ? $scopeType : PermissionScope::tryFrom($scopeType);
        expect($expectedScope)->toBe(PermissionScope::Temporal);
        expect($scopedPermission->scope_id)->toBe('temporary');
        expect($scopedPermission->expires_at->format('Y-m-d H'))->toBe($expiresAt->format('Y-m-d H'));
    });

    test('grantTemporaryPermission supports custom scope', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Scoped User',
            'email' => 'scoped@example.com',
            'password' => bcrypt('password'),
        ]);

        $scopedPermission = $service->grantTemporaryPermission(
            user: $user,
            permission: 'posts.delete',
            expiresAt: Carbon::now()->addHours(1),
            scope: PermissionScope::Team,
            scopeValue: 'team-123'
        );

        // scope_type may be string or enum depending on Laravel version
        $scopeType = $scopedPermission->scope_type;
        $expectedScope = $scopeType instanceof PermissionScope ? $scopeType : PermissionScope::tryFrom($scopeType);
        expect($expectedScope)->toBe(PermissionScope::Team);
        expect($scopedPermission->scope_id)->toBe('team-123');
    });

    test('grantForDuration creates permission that expires after duration', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Duration User',
            'email' => 'duration@example.com',
            'password' => bcrypt('password'),
        ]);

        Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00'));

        $scopedPermission = $service->grantForDuration(
            user: $user,
            permission: 'reports.view',
            minutes: 30
        );

        expect($scopedPermission->expires_at->format('Y-m-d H:i'))->toBe('2024-01-15 10:30');

        Carbon::setTestNow();
    });

    test('grantDuringHours creates permission with time range condition', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Hours User',
            'email' => 'hours@example.com',
            'password' => bcrypt('password'),
        ]);

        $scopedPermission = $service->grantDuringHours(
            user: $user,
            permission: 'system.access',
            startHour: 9,
            endHour: 17
        );

        expect($scopedPermission->scope_id)->toBe('hours:9-17');
        expect($scopedPermission->conditions)->toHaveKey('time_range');
        expect($scopedPermission->conditions['time_range']['start_hour'])->toBe(9);
        expect($scopedPermission->conditions['time_range']['end_hour'])->toBe(17);
    });

    test('grantOnDays creates permission with allowed days condition', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Days User',
            'email' => 'days@example.com',
            'password' => bcrypt('password'),
        ]);

        $weekdays = [1, 2, 3, 4, 5]; // Monday to Friday

        $scopedPermission = $service->grantOnDays(
            user: $user,
            permission: 'attendance.mark',
            days: $weekdays
        );

        expect($scopedPermission->scope_id)->toBe('days:1,2,3,4,5');
        expect($scopedPermission->conditions)->toHaveKey('allowed_days');
        expect($scopedPermission->conditions['allowed_days'])->toBe($weekdays);
    });

    test('hasActiveTemporaryPermission returns true for active permission', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant permission that expires in future
        $service->grantTemporaryPermission(
            user: $user,
            permission: 'posts.view',
            expiresAt: Carbon::now()->addHours(1)
        );

        expect($service->hasActiveTemporaryPermission($user, 'posts.view'))->toBeTrue();
    });

    test('hasActiveTemporaryPermission returns false for expired permission', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Expired User',
            'email' => 'expired@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant permission that expired in past
        $service->grantTemporaryPermission(
            user: $user,
            permission: 'posts.view',
            expiresAt: Carbon::now()->subHours(1)
        );

        expect($service->hasActiveTemporaryPermission($user, 'posts.view'))->toBeFalse();
    });

    test('hasActiveTemporaryPermission respects time range', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Time Range User',
            'email' => 'timerange@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant permission valid 9am-5pm
        $service->grantDuringHours($user, 'office.access', 9, 17);

        // Test during valid hours (noon)
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));
        expect($service->hasActiveTemporaryPermission($user, 'office.access'))->toBeTrue();

        // Test outside valid hours (8pm)
        Carbon::setTestNow(Carbon::parse('2024-01-15 20:00:00'));
        expect($service->hasActiveTemporaryPermission($user, 'office.access'))->toBeFalse();

        Carbon::setTestNow();
    });

    test('hasActiveTemporaryPermission respects allowed days', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Days Check User',
            'email' => 'dayscheck@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant permission valid Monday to Friday (1-5)
        $service->grantOnDays($user, 'work.access', [1, 2, 3, 4, 5]);

        // Test on Wednesday (day 3)
        Carbon::setTestNow(Carbon::parse('2024-01-17 10:00:00')); // Wednesday
        expect($service->hasActiveTemporaryPermission($user, 'work.access'))->toBeTrue();

        // Test on Saturday (day 6)
        Carbon::setTestNow(Carbon::parse('2024-01-20 10:00:00')); // Saturday
        expect($service->hasActiveTemporaryPermission($user, 'work.access'))->toBeFalse();

        Carbon::setTestNow();
    });

    test('getExpiringPermissions returns permissions expiring soon', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Expiring User',
            'email' => 'expiring@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant permissions with different expiration times
        $service->grantTemporaryPermission($user, 'perm1', Carbon::now()->addMinutes(30)); // Within 60 min
        $service->grantTemporaryPermission($user, 'perm2', Carbon::now()->addMinutes(45)); // Within 60 min
        $service->grantTemporaryPermission($user, 'perm3', Carbon::now()->addHours(2)); // After 60 min

        $expiring = $service->getExpiringPermissions($user, 60);

        expect($expiring)->toHaveCount(2);
        expect($expiring->pluck('permission.name')->toArray())->toContain('perm1', 'perm2');
    });

    test('extendPermission extends expiration time', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Extend User',
            'email' => 'extend@example.com',
            'password' => bcrypt('password'),
        ]);

        Carbon::setTestNow(Carbon::parse('2024-01-15 10:00:00'));

        // Grant permission expiring in 30 minutes
        $service->grantTemporaryPermission($user, 'extend.test', Carbon::now()->addMinutes(30));

        // Extend by 30 more minutes
        $extended = $service->extendPermission($user, 'extend.test', 30);

        expect($extended)->not->toBeNull();
        expect($extended->expires_at->format('Y-m-d H:i'))->toBe('2024-01-15 11:00');

        Carbon::setTestNow();
    });

    test('extendPermission returns null for non-existent permission', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'No Perm User',
            'email' => 'noperm@example.com',
            'password' => bcrypt('password'),
        ]);

        $extended = $service->extendPermission($user, 'nonexistent.perm', 30);

        expect($extended)->toBeNull();
    });

    test('revokeExpired removes only expired permissions', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Revoke User',
            'email' => 'revoke@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create expired permission
        $service->grantTemporaryPermission($user, 'expired.perm', Carbon::now()->subHours(1));

        // Create active permission
        $service->grantTemporaryPermission($user, 'active.perm', Carbon::now()->addHours(1));

        expect(ScopedPermission::count())->toBe(2);

        $deleted = $service->revokeExpired();

        expect($deleted)->toBe(1);
        expect(ScopedPermission::count())->toBe(1);

        // Verify active permission still exists
        expect(ScopedPermission::whereHas('permission', fn ($q) => $q->where('name', 'active.perm'))->exists())->toBeTrue();
    });

    test('overnight time range works correctly', function (): void {
        $aggregator = app(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $service = new TemporalPermissionService($contextualAuth);

        $user = User::create([
            'name' => 'Overnight User',
            'email' => 'overnight@example.com',
            'password' => bcrypt('password'),
        ]);

        // Grant permission valid 22:00-06:00 (overnight shift)
        $service->grantDuringHours($user, 'night.access', 22, 6);

        // Test at 23:00 (valid)
        Carbon::setTestNow(Carbon::parse('2024-01-15 23:00:00'));
        expect($service->hasActiveTemporaryPermission($user, 'night.access'))->toBeTrue();

        // Test at 03:00 (valid, after midnight)
        Carbon::setTestNow(Carbon::parse('2024-01-16 03:00:00'));
        expect($service->hasActiveTemporaryPermission($user, 'night.access'))->toBeTrue();

        // Test at 12:00 (invalid, during day)
        Carbon::setTestNow(Carbon::parse('2024-01-15 12:00:00'));
        expect($service->hasActiveTemporaryPermission($user, 'night.access'))->toBeFalse();

        Carbon::setTestNow();
    });
});
