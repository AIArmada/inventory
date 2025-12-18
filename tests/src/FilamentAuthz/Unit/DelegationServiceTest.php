<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Models\Delegation;
use AIArmada\FilamentAuthz\Services\CannotDelegateException;
use AIArmada\FilamentAuthz\Services\DelegationService;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Delegation::query()->delete();
    Permission::query()->delete();
    User::query()->delete();

    // Configure the user model for Delegation relationships
    config()->set('filament-authz.user_model', User::class);

    // Create permissions
    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'orders.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'delegate.orders.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'delegate.*', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

describe('DelegationService', function (): void {
    describe('canDelegate', function (): void {
        test('returns false when user does not have the permission', function (): void {
            $service = app(DelegationService::class);
            $user = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);

            $result = $service->canDelegate($user, 'orders.view');

            expect($result)->toBeFalse();
        });

        test('returns false when user has permission but no delegation rights', function (): void {
            $service = app(DelegationService::class);
            $user = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->givePermissionTo('orders.view');

            $result = $service->canDelegate($user, 'orders.view');

            expect($result)->toBeFalse();
        });

        test('returns true when user has permission and specific delegation rights', function (): void {
            $service = app(DelegationService::class);
            $user = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $result = $service->canDelegate($user, 'orders.view');

            expect($result)->toBeTrue();
        });

        test('returns true when user has permission and wildcard delegation rights', function (): void {
            $service = app(DelegationService::class);
            $user = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $user->givePermissionTo(['orders.view', 'delegate.*']);

            $result = $service->canDelegate($user, 'orders.view');

            expect($result)->toBeTrue();
        });
    });

    describe('delegate', function (): void {
        test('throws exception when user cannot delegate', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            expect(fn () => $service->delegate($delegator, $delegatee, 'orders.view'))
                ->toThrow(CannotDelegateException::class);
        });

        test('creates delegation when user can delegate', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegation = $service->delegate($delegator, $delegatee, 'orders.view');

            expect($delegation)->toBeInstanceOf(Delegation::class);
            expect($delegation->delegator_id)->toBe($delegator->id);
            expect($delegation->delegatee_id)->toBe($delegatee->id);
            expect($delegation->permission)->toBe('orders.view');
            expect($delegatee->hasPermissionTo('orders.view'))->toBeTrue();
        });

        test('creates delegation with expiration', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $expiresAt = now()->addDays(7);
            $delegation = $service->delegate($delegator, $delegatee, 'orders.view', $expiresAt);

            expect($delegation->expires_at->format('Y-m-d'))->toBe($expiresAt->format('Y-m-d'));
        });

        test('grants delegation rights when canRedelegate is true', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegation = $service->delegate($delegator, $delegatee, 'orders.view', null, true);

            expect($delegation->can_redelegate)->toBeTrue();
            expect($delegatee->hasPermissionTo('delegate.orders.view'))->toBeTrue();
        });
    });

    describe('revoke', function (): void {
        test('revokes delegation and removes permission', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegation = $service->delegate($delegator, $delegatee, 'orders.view');
            $service->revoke($delegation);

            $delegation->refresh();
            $delegatee->refresh();

            expect($delegation->isRevoked())->toBeTrue();
            expect($delegatee->hasPermissionTo('orders.view'))->toBeFalse();
        });

        test('revokes delegation rights when canRedelegate was true', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegation = $service->delegate($delegator, $delegatee, 'orders.view', null, true);
            $service->revoke($delegation);

            $delegatee->refresh();
            expect($delegatee->hasPermissionTo('delegate.orders.view'))->toBeFalse();
        });

        test('cascades revocation to sub-delegations', function (): void {
            $service = app(DelegationService::class);

            // Create delegation chain: A -> B -> C
            $userA = User::create([
                'name' => 'User A',
                'email' => 'usera@example.com',
                'password' => bcrypt('password'),
            ]);
            $userA->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $userB = User::create([
                'name' => 'User B',
                'email' => 'userb@example.com',
                'password' => bcrypt('password'),
            ]);

            $userC = User::create([
                'name' => 'User C',
                'email' => 'userc@example.com',
                'password' => bcrypt('password'),
            ]);

            // A delegates to B with redelegation rights
            $delegationAB = $service->delegate($userA, $userB, 'orders.view', null, true);

            // B delegates to C
            $delegationBC = $service->delegate($userB, $userC, 'orders.view');

            // Revoke A->B should cascade to B->C
            $service->revoke($delegationAB);

            $delegationAB->refresh();
            $delegationBC->refresh();

            expect($delegationAB->isRevoked())->toBeTrue();
            expect($delegationBC->isRevoked())->toBeTrue();
        });
    });

    describe('getDelegationsFor', function (): void {
        test('returns active delegations for user', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $service->delegate($delegator, $delegatee, 'orders.view');

            $delegations = $service->getDelegationsFor($delegatee);

            expect($delegations)->toHaveCount(1);
            expect($delegations->first()->permission)->toBe('orders.view');
        });

        test('excludes revoked delegations', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegation = $service->delegate($delegator, $delegatee, 'orders.view');
            $service->revoke($delegation);

            $delegations = $service->getDelegationsFor($delegatee);

            expect($delegations)->toBeEmpty();
        });

        test('excludes expired delegations', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            // Create expired delegation directly
            Delegation::create([
                'delegator_id' => $delegator->id,
                'delegatee_id' => $delegatee->id,
                'permission' => 'orders.view',
                'expires_at' => now()->subDay(),
                'can_redelegate' => false,
            ]);

            $delegations = $service->getDelegationsFor($delegatee);

            expect($delegations)->toBeEmpty();
        });
    });

    describe('getDelegationsBy', function (): void {
        test('returns delegations made by user', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $service->delegate($delegator, $delegatee, 'orders.view');

            $delegations = $service->getDelegationsBy($delegator);

            expect($delegations)->toHaveCount(1);
            expect((string) $delegations->first()->delegatee_id)->toBe((string) $delegatee->id);
        });

        test('excludes revoked delegations', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegation = $service->delegate($delegator, $delegatee, 'orders.view');
            $service->revoke($delegation);

            $delegations = $service->getDelegationsBy($delegator);

            expect($delegations)->toBeEmpty();
        });
    });

    describe('hasDelegatedPermission', function (): void {
        test('returns true for active delegation', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $service->delegate($delegator, $delegatee, 'orders.view');

            expect($service->hasDelegatedPermission($delegatee, 'orders.view'))->toBeTrue();
        });

        test('returns false for revoked delegation', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegation = $service->delegate($delegator, $delegatee, 'orders.view');
            $service->revoke($delegation);

            expect($service->hasDelegatedPermission($delegatee, 'orders.view'))->toBeFalse();
        });

        test('returns false for expired delegation', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            Delegation::create([
                'delegator_id' => $delegator->id,
                'delegatee_id' => $delegatee->id,
                'permission' => 'orders.view',
                'expires_at' => now()->subDay(),
                'can_redelegate' => false,
            ]);

            expect($service->hasDelegatedPermission($delegatee, 'orders.view'))->toBeFalse();
        });

        test('returns false when no delegation exists', function (): void {
            $service = app(DelegationService::class);
            $user = User::create([
                'name' => 'User',
                'email' => 'user@example.com',
                'password' => bcrypt('password'),
            ]);

            expect($service->hasDelegatedPermission($user, 'orders.view'))->toBeFalse();
        });
    });

    describe('cleanupExpired', function (): void {
        test('revokes expired delegations', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            Delegation::create([
                'delegator_id' => $delegator->id,
                'delegatee_id' => $delegatee->id,
                'permission' => 'orders.view',
                'expires_at' => now()->subDay(),
                'can_redelegate' => false,
            ]);

            $count = $service->cleanupExpired();

            expect($count)->toBe(1);
            expect(Delegation::whereNull('revoked_at')->count())->toBe(0);
        });

        test('returns count of revoked delegations', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            // Create 3 expired delegations using existing permission
            for ($i = 0; $i < 3; $i++) {
                Delegation::create([
                    'delegator_id' => $delegator->id,
                    'delegatee_id' => $delegatee->id,
                    'permission' => 'orders.view',
                    'expires_at' => now()->subDay(),
                    'can_redelegate' => false,
                ]);
            }

            $count = $service->cleanupExpired();

            expect($count)->toBe(3);
        });

        test('does not affect non-expired delegations', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            // Create non-expired delegation
            $activeDelegation = $service->delegate($delegator, $delegatee, 'orders.view', now()->addDay());

            // Create expired delegation
            Delegation::create([
                'delegator_id' => $delegator->id,
                'delegatee_id' => $delegatee->id,
                'permission' => 'orders.create',
                'expires_at' => now()->subDay(),
                'can_redelegate' => false,
            ]);

            $service->cleanupExpired();

            $activeDelegation->refresh();
            expect($activeDelegation->isRevoked())->toBeFalse();
        });

        test('returns zero when no expired delegations', function (): void {
            $service = app(DelegationService::class);

            $count = $service->cleanupExpired();

            expect($count)->toBe(0);
        });
    });

    describe('getDelegationChain', function (): void {
        test('returns chain including parent and children', function (): void {
            $service = app(DelegationService::class);

            // Create chain: A -> B -> C
            $userA = User::create([
                'name' => 'User A',
                'email' => 'usera@example.com',
                'password' => bcrypt('password'),
            ]);
            $userA->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $userB = User::create([
                'name' => 'User B',
                'email' => 'userb@example.com',
                'password' => bcrypt('password'),
            ]);

            $userC = User::create([
                'name' => 'User C',
                'email' => 'userc@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegationAB = $service->delegate($userA, $userB, 'orders.view', null, true);
            $delegationBC = $service->delegate($userB, $userC, 'orders.view');

            $chain = $service->getDelegationChain($delegationAB);

            expect($chain)->toHaveCount(2);
            expect($chain->pluck('id')->toArray())->toContain($delegationAB->id, $delegationBC->id);
        });

        test('returns single delegation when no chain exists', function (): void {
            $service = app(DelegationService::class);
            $delegator = User::create([
                'name' => 'Delegator',
                'email' => 'delegator@example.com',
                'password' => bcrypt('password'),
            ]);
            $delegator->givePermissionTo(['orders.view', 'delegate.orders.view']);

            $delegatee = User::create([
                'name' => 'Delegatee',
                'email' => 'delegatee@example.com',
                'password' => bcrypt('password'),
            ]);

            $delegation = $service->delegate($delegator, $delegatee, 'orders.view');

            $chain = $service->getDelegationChain($delegation);

            expect($chain)->toHaveCount(1);
            expect($chain->first()->id)->toBe($delegation->id);
        });
    });
});
