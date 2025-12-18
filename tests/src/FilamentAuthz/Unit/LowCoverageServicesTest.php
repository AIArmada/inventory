<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\PermissionGroup;
use AIArmada\FilamentAuthz\Models\PermissionSnapshot;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use AIArmada\FilamentAuthz\Services\CannotDelegateException;
use AIArmada\FilamentAuthz\Services\DelegationService;
use AIArmada\FilamentAuthz\Services\PermissionGroupService;
use AIArmada\FilamentAuthz\Services\PermissionImpactAnalyzer;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Services\RollbackResult;
use Illuminate\Database\Eloquent\Collection;

describe('DelegationService', function (): void {
    beforeEach(function (): void {
        $this->auditLogger = Mockery::mock(AuditLogger::class);
        $this->auditLogger->shouldReceive('log')->andReturnNull();
        $this->service = new DelegationService($this->auditLogger);
    });

    describe('canDelegate', function (): void {
        it('returns false if user does not have can method', function (): void {
            $delegator = new stdClass;

            $result = $this->service->canDelegate($delegator, 'test.permission');

            expect($result)->toBeFalse();
        });

        it('returns false if user does not have the permission', function (): void {
            $delegator = Mockery::mock();
            $delegator->shouldReceive('can')->with('test.permission')->andReturn(false);

            $result = $this->service->canDelegate($delegator, 'test.permission');

            expect($result)->toBeFalse();
        });

        it('has canDelegate method that checks permissions', function (): void {
            $reflection = new ReflectionMethod($this->service, 'canDelegate');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
            expect($reflection->getReturnType()?->getName())->toBe('bool');
        });
    });

    describe('delegate', function (): void {
        it('throws exception if user cannot delegate', function (): void {
            $delegator = Mockery::mock();
            $delegator->shouldReceive('can')->andReturn(false);

            $delegatee = Mockery::mock();

            expect(fn () => $this->service->delegate($delegator, $delegatee, 'test.permission'))
                ->toThrow(CannotDelegateException::class);
        });
    });

    describe('getDelegationsFor', function (): void {
        it('returns collection for user', function (): void {
            $user = (object) ['id' => 'test-user-id'];

            $result = $this->service->getDelegationsFor($user);

            expect($result)->toBeInstanceOf(Collection::class);
        });
    });

    describe('getDelegationsBy', function (): void {
        it('returns collection for delegator', function (): void {
            $user = (object) ['id' => 'test-user-id'];

            $result = $this->service->getDelegationsBy($user);

            expect($result)->toBeInstanceOf(Collection::class);
        });
    });

    describe('hasDelegatedPermission', function (): void {
        it('checks if user has delegated permission', function (): void {
            $user = (object) ['id' => 'test-user-id'];

            $result = $this->service->hasDelegatedPermission($user, 'test.permission');

            expect($result)->toBeBool();
        });
    });

    describe('cleanupExpired', function (): void {
        it('returns count of cleaned up delegations', function (): void {
            $result = $this->service->cleanupExpired();

            expect($result)->toBeInt();
        });
    });
});

describe('PermissionGroupService', function (): void {
    beforeEach(function (): void {
        $this->service = new PermissionGroupService;
    });

    describe('createGroup', function (): void {
        it('can be called with required parameters', function (): void {
            expect(method_exists($this->service, 'createGroup'))->toBeTrue();
        });

        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'createGroup');

            expect($reflection->getNumberOfRequiredParameters())->toBe(1);
            expect($reflection->getReturnType()?->getName())->toBe(PermissionGroup::class);
        });
    });

    describe('updateGroup', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'updateGroup');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
            expect($reflection->getReturnType()?->getName())->toBe(PermissionGroup::class);
        });
    });

    describe('deleteGroup', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'deleteGroup');

            expect($reflection->getNumberOfRequiredParameters())->toBe(1);
            expect($reflection->getReturnType()?->getName())->toBe('bool');
        });
    });

    describe('syncPermissions', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'syncPermissions');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
        });
    });

    describe('addPermissions', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'addPermissions');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
        });
    });

    describe('removePermissions', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'removePermissions');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
        });
    });

    describe('getGroupPermissions', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'getGroupPermissions');

            expect($reflection->getNumberOfRequiredParameters())->toBe(1);
        });
    });

    describe('getRootGroups', function (): void {
        it('returns collection', function (): void {
            $result = $this->service->getRootGroups();

            expect($result)->toBeInstanceOf(Collection::class);
        });
    });

    describe('getHierarchyTree', function (): void {
        it('returns collection', function (): void {
            $result = $this->service->getHierarchyTree();

            expect($result)->toBeInstanceOf(Collection::class);
        });
    });

    describe('findBySlug', function (): void {
        it('returns null for non-existent slug', function (): void {
            $result = $this->service->findBySlug('non-existent-slug');

            expect($result)->toBeNull();
        });
    });

    describe('moveGroup', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'moveGroup');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
        });
    });

    describe('reorderGroups', function (): void {
        it('accepts array of order', function (): void {
            $this->service->reorderGroups([]);

            expect(true)->toBeTrue();
        });
    });

    describe('getGroupsWithPermission', function (): void {
        it('returns empty collection for non-existent permission', function (): void {
            $result = $this->service->getGroupsWithPermission('non.existent.permission');

            expect($result)->toBeInstanceOf(Collection::class);
            expect($result)->toBeEmpty();
        });
    });

    describe('clearCache', function (): void {
        it('clears cache without error', function (): void {
            $this->service->clearCache();

            expect(true)->toBeTrue();
        });
    });
});

describe('PermissionImpactAnalyzer', function (): void {
    beforeEach(function (): void {
        $this->roleInheritance = Mockery::mock(RoleInheritanceService::class);
        $this->analyzer = new PermissionImpactAnalyzer($this->roleInheritance);
    });

    describe('analyzePermissionGrant', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->analyzer, 'analyzePermissionGrant');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
            expect($reflection->getReturnType()?->getName())->toBe('array');
        });

        it('requires string and Role parameters', function (): void {
            $reflection = new ReflectionMethod($this->analyzer, 'analyzePermissionGrant');
            $params = $reflection->getParameters();

            expect($params[0]->getName())->toBe('permissionName');
            expect($params[0]->getType()?->getName())->toBe('string');
            expect($params[1]->getName())->toBe('role');
        });
    });

    describe('analyzePermissionRevoke', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->analyzer, 'analyzePermissionRevoke');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
            expect($reflection->getReturnType()?->getName())->toBe('array');
        });
    });

    describe('analyzeRoleDeletion', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->analyzer, 'analyzeRoleDeletion');

            expect($reflection->getNumberOfRequiredParameters())->toBe(1);
            expect($reflection->getReturnType()?->getName())->toBe('array');
        });
    });

    describe('analyzeHierarchyChange', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->analyzer, 'analyzeHierarchyChange');

            expect($reflection->getNumberOfRequiredParameters())->toBe(2);
            expect($reflection->getReturnType()?->getName())->toBe('array');
        });
    });

    describe('analyzeBulkChange', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->analyzer, 'analyzeBulkChange');

            expect($reflection->getNumberOfRequiredParameters())->toBe(3);
            expect($reflection->getReturnType()?->getName())->toBe('array');
        });

        it('requires operation, role, and permissions parameters', function (): void {
            $reflection = new ReflectionMethod($this->analyzer, 'analyzeBulkChange');
            $params = $reflection->getParameters();

            expect($params[0]->getName())->toBe('operation');
            expect($params[1]->getName())->toBe('role');
            expect($params[2]->getName())->toBe('permissions');
        });
    });

    describe('protected methods', function (): void {
        it('has getAffectedRoles method', function (): void {
            $reflection = new ReflectionClass($this->analyzer);

            expect($reflection->hasMethod('getAffectedRoles'))->toBeTrue();
        });

        it('has countAffectedUsers method', function (): void {
            $reflection = new ReflectionClass($this->analyzer);

            expect($reflection->hasMethod('countAffectedUsers'))->toBeTrue();
        });

        it('has escalateImpact method', function (): void {
            $reflection = new ReflectionClass($this->analyzer);

            expect($reflection->hasMethod('escalateImpact'))->toBeTrue();
        });

        it('has generateReasoning method', function (): void {
            $reflection = new ReflectionClass($this->analyzer);

            expect($reflection->hasMethod('generateReasoning'))->toBeTrue();
        });
    });
});

describe('PermissionVersioningService', function (): void {
    beforeEach(function (): void {
        $this->auditLogger = Mockery::mock(AuditLogger::class);
        $this->auditLogger->shouldReceive('log')->andReturnNull();
        $this->service = new PermissionVersioningService($this->auditLogger);
    });

    describe('createSnapshot', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'createSnapshot');

            expect($reflection->getNumberOfRequiredParameters())->toBe(1);
            expect($reflection->getReturnType()?->getName())->toBe(PermissionSnapshot::class);
        });
    });

    describe('compare', function (): void {
        it('returns diff array', function (): void {
            $from = Mockery::mock(PermissionSnapshot::class);
            $from->shouldReceive('getRoles')->andReturn([['name' => 'role1']]);
            $from->shouldReceive('getPermissions')->andReturn([['name' => 'perm1']]);
            $from->shouldReceive('getAssignments')->andReturn([]);

            $to = Mockery::mock(PermissionSnapshot::class);
            $to->shouldReceive('getRoles')->andReturn([['name' => 'role1'], ['name' => 'role2']]);
            $to->shouldReceive('getPermissions')->andReturn([['name' => 'perm1']]);
            $to->shouldReceive('getAssignments')->andReturn([]);

            $result = $this->service->compare($from, $to);

            expect($result)->toBeArray();
            expect($result)->toHaveKey('roles');
            expect($result)->toHaveKey('permissions');
            expect($result['roles']['added'])->toContain('role2');
        });
    });

    describe('listSnapshots', function (): void {
        it('returns collection', function (): void {
            $result = $this->service->listSnapshots();

            expect($result)->toBeInstanceOf(Collection::class);
        });
    });

    describe('deleteSnapshot', function (): void {
        it('has correct method signature', function (): void {
            $reflection = new ReflectionMethod($this->service, 'deleteSnapshot');

            expect($reflection->getNumberOfRequiredParameters())->toBe(1);
            expect($reflection->getReturnType()?->getName())->toBe('bool');
        });
    });
});

describe('RollbackResult', function (): void {
    it('can be instantiated', function (): void {
        $snapshot = Mockery::mock(PermissionSnapshot::class);

        $result = new RollbackResult(
            success: true,
            snapshot: $snapshot,
            isDryRun: true
        );

        expect($result->success)->toBeTrue();
        expect($result->isDryRun)->toBeTrue();
    });

    it('accepts preview array', function (): void {
        $snapshot = Mockery::mock(PermissionSnapshot::class);

        $result = new RollbackResult(
            success: true,
            snapshot: $snapshot,
            preview: ['roles' => ['added' => [], 'removed' => []]],
            isDryRun: true
        );

        expect($result->preview)->toBeArray();
    });

    it('is readonly class', function (): void {
        $reflection = new ReflectionClass(RollbackResult::class);

        expect($reflection->isReadOnly())->toBeTrue();
    });
});

describe('CannotDelegateException', function (): void {
    it('is an exception', function (): void {
        $exception = new CannotDelegateException('Test message');

        expect($exception)->toBeInstanceOf(Exception::class);
        expect($exception->getMessage())->toBe('Test message');
    });
});
