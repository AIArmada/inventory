<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\PermissionTester;
use AIArmada\FilamentAuthz\Services\PolicyEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->aggregator = Mockery::mock(PermissionAggregator::class);
    $this->policyEngine = Mockery::mock(PolicyEngine::class);
    $this->contextualAuth = Mockery::mock(ContextualAuthorizationService::class);

    $this->tester = new PermissionTester(
        $this->aggregator,
        $this->policyEngine,
        $this->contextualAuth
    );

    $this->user = (object) ['id' => 'test-user-id'];
});

describe('PermissionTester test method', function (): void {
    it('returns allowed when user has direct permission', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(true);

        $this->aggregator->shouldReceive('getPermissionSource')
            ->with($this->user, 'user.view')
            ->andReturn(['type' => 'direct', 'source' => null, 'via' => null]);

        $result = $this->tester->test($this->user, 'user.view');

        expect($result['allowed'])->toBeTrue()
            ->and($result['reason'])->toBe('Granted directly to user')
            ->and($result['source']['type'])->toBe('direct');
    });

    it('returns allowed when user has role permission', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(true);

        $this->aggregator->shouldReceive('getPermissionSource')
            ->with($this->user, 'user.view')
            ->andReturn(['type' => 'role', 'source' => 'admin', 'via' => null]);

        $result = $this->tester->test($this->user, 'user.view');

        expect($result['allowed'])->toBeTrue()
            ->and($result['reason'])->toBe("Granted via role 'admin'")
            ->and($result['source']['type'])->toBe('role');
    });

    it('returns allowed when user has inherited permission', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(true);

        $this->aggregator->shouldReceive('getPermissionSource')
            ->with($this->user, 'user.view')
            ->andReturn(['type' => 'inherited', 'source' => 'editor', 'via' => 'admin']);

        $result = $this->tester->test($this->user, 'user.view');

        expect($result['allowed'])->toBeTrue()
            ->and($result['reason'])->toBe("Inherited from role 'editor' via 'admin'")
            ->and($result['source']['type'])->toBe('inherited');
    });

    it('returns allowed when user has wildcard permission', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(true);

        $this->aggregator->shouldReceive('getPermissionSource')
            ->with($this->user, 'user.view')
            ->andReturn(['type' => 'wildcard', 'source' => 'user.*', 'via' => null]);

        $result = $this->tester->test($this->user, 'user.view');

        expect($result['allowed'])->toBeTrue()
            ->and($result['reason'])->toBe("Matched wildcard permission 'user.*'");
    });

    it('returns allowed when user has contextual permission', function (): void {
        $context = ['team_id' => 'team-123'];

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(false);

        $this->contextualAuth->shouldReceive('canWithContext')
            ->with($this->user, 'user.view', $context)
            ->andReturn(true);

        $result = $this->tester->test($this->user, 'user.view', $context);

        expect($result['allowed'])->toBeTrue()
            ->and($result['reason'])->toBe('Granted via contextual/scoped permission')
            ->and($result['source']['type'])->toBe('contextual');
    });

    it('returns allowed via ABAC policy', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(false);

        $this->policyEngine->shouldReceive('evaluate')
            ->with('view', 'user', [])
            ->andReturn(PolicyDecision::Permit);

        $result = $this->tester->test($this->user, 'user.view', []);

        expect($result['allowed'])->toBeTrue()
            ->and($result['reason'])->toBe('Granted via ABAC policy')
            ->and($result['policy_decision'])->toBe(PolicyDecision::Permit);
    });

    it('returns denied when no permission exists', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(false);

        $this->policyEngine->shouldReceive('evaluate')
            ->with('view', 'user', [])
            ->andReturn(PolicyDecision::Deny);

        $result = $this->tester->test($this->user, 'user.view', []);

        expect($result['allowed'])->toBeFalse()
            ->and($result['reason'])->toBe('Permission not found through any authorization mechanism')
            ->and($result['source']['type'])->toBe('none');
    });
});

describe('PermissionTester testBatch method', function (): void {
    it('tests multiple permissions at once', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(true);

        $this->aggregator->shouldReceive('getPermissionSource')
            ->with($this->user, 'user.view')
            ->andReturn(['type' => 'direct', 'source' => null, 'via' => null]);

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.create')
            ->andReturn(false);

        $this->policyEngine->shouldReceive('evaluate')
            ->with('create', 'user', [])
            ->andReturn(PolicyDecision::Deny);

        $results = $this->tester->testBatch($this->user, ['user.view', 'user.create']);

        expect($results)->toHaveKeys(['user.view', 'user.create'])
            ->and($results['user.view']['allowed'])->toBeTrue()
            ->and($results['user.create']['allowed'])->toBeFalse();
    });
});

describe('PermissionTester simulateRoleGrant method', function (): void {
    it('simulates what permissions a user would gain from a role', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'post.edit', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->aggregator->shouldReceive('getEffectivePermissionNames')
            ->with($this->user)
            ->andReturn(collect(['user.view']));

        $result = $this->tester->simulateRoleGrant($this->user, $role);

        expect($result['current_permissions'])->toBe(['user.view'])
            ->and($result['new_permissions'])->toBe(['post.edit'])
            ->and($result['removed_permissions'])->toBe([])
            ->and($result['net_change'])->toBe(1);
    });

    it('does not double count existing permissions', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->aggregator->shouldReceive('getEffectivePermissionNames')
            ->with($this->user)
            ->andReturn(collect(['user.view']));

        $result = $this->tester->simulateRoleGrant($this->user, $role);

        expect($result['new_permissions'])->toBe([])
            ->and($result['net_change'])->toBe(0);
    });
});

describe('PermissionTester simulateRoleRevoke method', function (): void {
    it('simulates what permissions a user would lose from revoking a role', function (): void {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'post.edit', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->aggregator->shouldReceive('getEffectivePermissionNames')
            ->with($this->user)
            ->andReturn(collect(['user.view', 'post.edit']));

        // Use Eloquent Collection type
        $this->aggregator->shouldReceive('getEffectiveRoles')
            ->with($this->user)
            ->andReturn(new Illuminate\Database\Eloquent\Collection([$role]));

        $result = $this->tester->simulateRoleRevoke($this->user, $role);

        expect($result['current_permissions'])->toBe(['user.view', 'post.edit'])
            ->and($result['removed_permissions'])->toBe(['post.edit'])
            ->and($result['net_change'])->toBe(-1);
    });
});

describe('PermissionTester generatePermissionMatrix method', function (): void {
    it('generates a full permission matrix for a user', function (): void {
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.create', 'guard_name' => 'web']);

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(true);

        $this->aggregator->shouldReceive('getPermissionSource')
            ->with($this->user, 'user.view')
            ->andReturn(['type' => 'direct', 'source' => null, 'via' => null]);

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.create')
            ->andReturn(false);

        $this->policyEngine->shouldReceive('evaluate')
            ->with('create', 'user', [])
            ->andReturn(PolicyDecision::Deny);

        $matrix = $this->tester->generatePermissionMatrix($this->user);

        expect($matrix)->toHaveKeys(['user.view', 'user.create'])
            ->and($matrix['user.view']['has_permission'])->toBeTrue()
            ->and($matrix['user.create']['has_permission'])->toBeFalse();
    });
});

describe('PermissionTester detectConflicts method', function (): void {
    it('detects policy override conflicts', function (): void {
        $this->aggregator->shouldReceive('getEffectivePermissionNames')
            ->with($this->user)
            ->andReturn(collect(['user.view']));

        $this->policyEngine->shouldReceive('evaluate')
            ->with('view', 'user', [])
            ->andReturn(PolicyDecision::Deny);

        $conflicts = $this->tester->detectConflicts($this->user);

        expect($conflicts)->toHaveCount(1)
            ->and($conflicts[0]['permission'])->toBe('user.view')
            ->and($conflicts[0]['conflict_type'])->toBe('policy_override')
            ->and($conflicts[0]['details'])->toContain('ABAC policy denies');
    });

    it('returns empty array when no conflicts', function (): void {
        $this->aggregator->shouldReceive('getEffectivePermissionNames')
            ->with($this->user)
            ->andReturn(collect(['user.view']));

        $this->policyEngine->shouldReceive('evaluate')
            ->with('view', 'user', [])
            ->andReturn(PolicyDecision::Permit);

        $conflicts = $this->tester->detectConflicts($this->user);

        expect($conflicts)->toHaveCount(0);
    });
});

describe('PermissionTester formatReason', function (): void {
    it('formats implicit permission reason', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(true);

        $this->aggregator->shouldReceive('getPermissionSource')
            ->with($this->user, 'user.view')
            ->andReturn(['type' => 'implicit', 'source' => 'user.*', 'via' => null]);

        $result = $this->tester->test($this->user, 'user.view');

        expect($result['reason'])->toBe("Implied by permission 'user.*'");
    });

    it('formats unknown source reason', function (): void {
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($this->user, 'user.view')
            ->andReturn(true);

        $this->aggregator->shouldReceive('getPermissionSource')
            ->with($this->user, 'user.view')
            ->andReturn(['type' => 'unknown', 'source' => null, 'via' => null]);

        $result = $this->tester->test($this->user, 'user.view');

        expect($result['reason'])->toBe('Unknown source');
    });
});
