<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\ConditionOperator;
use AIArmada\FilamentAuthz\Services\PermissionBuilder;
use AIArmada\FilamentAuthz\Services\PolicyBuilder;
use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;

describe('PermissionBuilder', function (): void {
    it('creates builder for resource', function (): void {
        $builder = PermissionBuilder::for('orders');

        expect($builder)->toBeInstanceOf(PermissionBuilder::class);
    });

    it('builds crud permissions', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->crud()
            ->build();

        expect($permissions)->toHaveKey('orders.viewAny')
            ->toHaveKey('orders.view')
            ->toHaveKey('orders.create')
            ->toHaveKey('orders.update')
            ->toHaveKey('orders.delete');
    });

    it('builds soft delete permissions', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->softDeletes()
            ->build();

        expect($permissions)->toHaveKey('orders.restore')
            ->toHaveKey('orders.forceDelete');
    });

    it('builds full crud permissions', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->fullCrud()
            ->build();

        expect($permissions)->toHaveKey('orders.viewAny')
            ->toHaveKey('orders.delete')
            ->toHaveKey('orders.restore')
            ->toHaveKey('orders.forceDelete');
    });

    it('adds custom abilities', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->abilities(['approve', 'reject'])
            ->build();

        expect($permissions)->toHaveKey('orders.approve')
            ->toHaveKey('orders.reject');
    });

    it('adds single ability with description', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->ability('export', 'Export orders to CSV')
            ->build();

        expect($permissions)->toHaveKey('orders.export');
        expect($permissions['orders.export']['description'])->toBe('Export orders to CSV');
    });

    it('adds view only permissions', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->viewOnly()
            ->build();

        expect($permissions)->toHaveKey('orders.viewAny')
            ->toHaveKey('orders.view');
        expect(count($permissions))->toBe(2);
    });

    it('adds manage ability', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->manage()
            ->build();

        expect($permissions)->toHaveKey('orders.manage');
    });

    it('adds wildcard permission', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->wildcard()
            ->build();

        expect($permissions)->toHaveKey('orders.*');
    });

    it('adds export ability', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->export()
            ->build();

        expect($permissions)->toHaveKey('orders.export');
    });

    it('adds import ability', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->import()
            ->build();

        expect($permissions)->toHaveKey('orders.import');
    });

    it('adds replicate ability', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->replicate()
            ->build();

        expect($permissions)->toHaveKey('orders.replicate');
    });

    it('adds bulk action abilities', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->bulkActions()
            ->build();

        expect($permissions)->toHaveKey('orders.bulkDelete')
            ->toHaveKey('orders.bulkUpdate');
    });

    it('sets group', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->ability('view')
            ->group('Sales')
            ->build();

        expect($permissions['orders.view']['group'])->toBe('Sales');
    });

    it('sets guard name', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->ability('view')
            ->guard('admin')
            ->build();

        expect($permissions['orders.view']['guard_name'])->toBe('admin');
    });

    it('sets descriptions', function (): void {
        $permissions = PermissionBuilder::for('orders')
            ->abilities(['view', 'create'])
            ->describe(['view' => 'View orders list', 'create' => 'Create new orders'])
            ->build();

        expect($permissions['orders.view']['description'])->toBe('View orders list');
        expect($permissions['orders.create']['description'])->toBe('Create new orders');
    });

    it('gets names only', function (): void {
        $names = PermissionBuilder::for('orders')
            ->crud()
            ->getNames();

        expect($names)->toContain('orders.viewAny')
            ->toContain('orders.view')
            ->toContain('orders.create');
    });

    it('generates auto descriptions', function (): void {
        $permissions = PermissionBuilder::for('order')
            ->crud()
            ->build();

        expect($permissions['order.viewAny']['description'])->toContain('View any');
        expect($permissions['order.create']['description'])->toContain('Create');
    });
});

describe('PolicyBuilder', function (): void {
    it('creates policy with name', function (): void {
        $builder = PolicyBuilder::create('Test Policy');

        expect($builder)->toBeInstanceOf(PolicyBuilder::class);
    });

    it('sets description', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->description('A test policy')
            ->toArray();

        expect($policy['description'])->toBe('A test policy');
    });

    it('sets allow effect', function (): void {
        $policy = PolicyBuilder::create('Allow Policy')
            ->allow()
            ->toArray();

        expect($policy['effect'])->toBe('allow');
    });

    it('sets deny effect', function (): void {
        $policy = PolicyBuilder::create('Deny Policy')
            ->deny()
            ->toArray();

        expect($policy['effect'])->toBe('deny');
    });

    it('sets target action', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->action('orders.create')
            ->toArray();

        expect($policy['target_action'])->toBe('orders.create');
    });

    it('sets target resource', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->resource('Order')
            ->toArray();

        expect($policy['target_resource'])->toBe('Order');
    });

    it('sets all actions on resource', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->allActionsOn('Order')
            ->toArray();

        expect($policy['target_action'])->toBe('*');
        expect($policy['target_resource'])->toBe('Order');
    });

    it('sets any resource for action', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->anyResource('view')
            ->toArray();

        expect($policy['target_action'])->toBe('view');
        expect($policy['target_resource'])->toBe('*');
    });

    it('adds conditions with when', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->when('status', ConditionOperator::Equals, 'active')
            ->toArray();

        expect($policy['conditions'])->toHaveCount(1);
        expect($policy['conditions'][0]['attribute'])->toBe('status');
        expect($policy['conditions'][0]['operator'])->toBe('eq');
        expect($policy['conditions'][0]['value'])->toBe('active');
    });

    it('adds whereEquals condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereEquals('type', 'premium')
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('eq');
    });

    it('adds whereNotEquals condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereNotEquals('status', 'deleted')
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('neq');
    });

    it('adds whereGreaterThan condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereGreaterThan('amount', 100)
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('gt');
    });

    it('adds whereLessThan condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereLessThan('count', 10)
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('lt');
    });

    it('adds whereIn condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereIn('role', ['admin', 'manager'])
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('in');
        expect($policy['conditions'][0]['value'])->toBe(['admin', 'manager']);
    });

    it('adds whereNotIn condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereNotIn('role', ['banned'])
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('not_in');
    });

    it('adds whereContains condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereContains('name', 'test')
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('contains');
    });

    it('adds whereStartsWith condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereStartsWith('code', 'ORD-')
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('starts_with');
    });

    it('adds whereBetween condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereBetween('amount', 100, 1000)
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('between');
        expect($policy['conditions'][0]['value'])->toBe([100, 1000]);
    });

    it('adds whereNull condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereNull('deleted_at')
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('is_null');
    });

    it('adds whereNotNull condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereNotNull('verified_at')
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('is_not_null');
    });

    it('adds whereMatches condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereMatches('email', '/^admin@/')
            ->toArray();

        expect($policy['conditions'][0]['operator'])->toBe('matches');
    });

    it('adds whereOwner condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereOwner()
            ->toArray();

        expect($policy['conditions'][0]['attribute'])->toBe('resource.user_id');
        expect($policy['conditions'][0]['value'])->toBe('@user.id');
    });

    it('adds whereTeamMember condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereTeamMember()
            ->toArray();

        expect($policy['conditions'][0]['attribute'])->toBe('user.team_ids');
    });

    it('adds whereHasRole condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereHasRole('admin')
            ->toArray();

        expect($policy['conditions'][0]['attribute'])->toBe('user.roles');
        expect($policy['conditions'][0]['value'])->toBe('admin');
    });

    it('adds whereIpInRange condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->whereIpInRange(['192.168.1.0/24', '10.0.0.0/8'])
            ->toArray();

        expect($policy['conditions'][0]['attribute'])->toBe('request.ip');
    });

    it('adds duringBusinessHours condition', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->duringBusinessHours()
            ->toArray();

        expect($policy['conditions'][0]['attribute'])->toBe('request.hour');
        expect($policy['conditions'][0]['value'])->toBe([9, 17]);
    });

    it('sets priority', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->priority(50)
            ->toArray();

        expect($policy['priority'])->toBe(50);
    });

    it('sets high priority', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->highPriority()
            ->toArray();

        expect($policy['priority'])->toBe(100);
    });

    it('sets low priority', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->lowPriority()
            ->toArray();

        expect($policy['priority'])->toBe(-100);
    });

    it('sets inactive', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->inactive()
            ->toArray();

        expect($policy['is_active'])->toBeFalse();
    });

    it('sets validity period', function (): void {
        $from = new DateTimeImmutable('2024-01-01');
        $until = new DateTimeImmutable('2024-12-31');

        $policy = PolicyBuilder::create('Test Policy')
            ->validBetween($from, $until)
            ->toArray();

        expect($policy['valid_from'])->toBe('2024-01-01 00:00:00');
        expect($policy['valid_until'])->toBe('2024-12-31 00:00:00');
    });

    it('sets valid from', function (): void {
        $from = new DateTimeImmutable('2024-01-01');

        $policy = PolicyBuilder::create('Test Policy')
            ->validFrom($from)
            ->toArray();

        expect($policy['valid_from'])->toBe('2024-01-01 00:00:00');
    });

    it('sets valid until', function (): void {
        $until = new DateTimeImmutable('2024-12-31');

        $policy = PolicyBuilder::create('Test Policy')
            ->validUntil($until)
            ->toArray();

        expect($policy['valid_until'])->toBe('2024-12-31 00:00:00');
    });

    it('sets metadata', function (): void {
        $policy = PolicyBuilder::create('Test Policy')
            ->metadata(['source' => 'api', 'version' => '1.0'])
            ->toArray();

        expect($policy['metadata'])->toBe(['source' => 'api', 'version' => '1.0']);
    });

    it('generates slug from name', function (): void {
        $policy = PolicyBuilder::create('Allow Admin Orders')
            ->toArray();

        expect($policy['slug'])->toBe('allow-admin-orders');
    });
});

describe('WildcardPermissionResolver', function (): void {
    it('identifies wildcards', function (): void {
        $resolver = new WildcardPermissionResolver;

        expect($resolver->isWildcard('orders.*'))->toBeTrue();
        expect($resolver->isWildcard('*'))->toBeTrue();
        expect($resolver->isWildcard('*.view'))->toBeTrue();
        expect($resolver->isWildcard('orders.create'))->toBeFalse();
    });

    it('matches exact permissions', function (): void {
        $resolver = new WildcardPermissionResolver;

        expect($resolver->matches('orders.create', 'orders.create'))->toBeTrue();
        expect($resolver->matches('orders.create', 'orders.delete'))->toBeFalse();
    });

    it('matches universal wildcard', function (): void {
        $resolver = new WildcardPermissionResolver;

        expect($resolver->matches('*', 'orders.create'))->toBeTrue();
        expect($resolver->matches('*', 'anything'))->toBeTrue();
    });

    it('matches prefix wildcard', function (): void {
        $resolver = new WildcardPermissionResolver;

        expect($resolver->matches('orders.*', 'orders.create'))->toBeTrue();
        expect($resolver->matches('orders.*', 'orders.delete'))->toBeTrue();
        expect($resolver->matches('orders.*', 'products.create'))->toBeFalse();
    });

    it('extracts prefix from permission', function (): void {
        $resolver = new WildcardPermissionResolver;

        expect($resolver->extractPrefix('orders.create'))->toBe('orders');
        expect($resolver->extractPrefix('products.view'))->toBe('products');
        expect($resolver->extractPrefix('single'))->toBeNull();
    });

    it('extracts action from permission', function (): void {
        $resolver = new WildcardPermissionResolver;

        expect($resolver->extractAction('orders.create'))->toBe('create');
        expect($resolver->extractAction('products.viewAny'))->toBe('viewAny');
        expect($resolver->extractAction('single'))->toBeNull();
    });

    it('builds permission from components', function (): void {
        $resolver = new WildcardPermissionResolver;

        expect($resolver->buildPermission('orders', 'create'))->toBe('orders.create');
        expect($resolver->buildPermission('products', 'view'))->toBe('products.view');
    });

    it('resolves non-wildcard permission', function (): void {
        $resolver = new WildcardPermissionResolver;

        $resolved = $resolver->resolve('orders.create');

        expect($resolved->toArray())->toBe(['orders.create']);
    });

    it('clears cache', function (): void {
        $resolver = new WildcardPermissionResolver;

        $resolver->clearCache();

        expect(true)->toBeTrue();
    });
});
