<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\ConditionOperator;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use AIArmada\FilamentAuthz\ValueObjects\PolicyCondition;

describe('PolicyCondition', function (): void {
    it('can be created with constructor', function (): void {
        $condition = new PolicyCondition(
            attribute: 'status',
            operator: ConditionOperator::Equals,
            value: 'active',
            description: 'Status must be active'
        );

        expect($condition->attribute)->toBe('status')
            ->and($condition->operator)->toBe(ConditionOperator::Equals)
            ->and($condition->value)->toBe('active')
            ->and($condition->description)->toBe('Status must be active');
    });

    it('can be created from array', function (): void {
        $condition = PolicyCondition::fromArray([
            'attribute' => 'role',
            'operator' => 'eq',
            'value' => 'admin',
            'description' => 'Must be admin',
        ]);

        expect($condition->attribute)->toBe('role')
            ->and($condition->operator)->toBe(ConditionOperator::Equals)
            ->and($condition->value)->toBe('admin')
            ->and($condition->description)->toBe('Must be admin');
    });

    it('creates equals condition with static method', function (): void {
        $condition = PolicyCondition::equals('status', 'published');

        expect($condition->attribute)->toBe('status')
            ->and($condition->operator)->toBe(ConditionOperator::Equals)
            ->and($condition->value)->toBe('published');
    });

    it('creates notEquals condition with static method', function (): void {
        $condition = PolicyCondition::notEquals('status', 'deleted');

        expect($condition->operator)->toBe(ConditionOperator::NotEquals);
    });

    it('creates greaterThan condition with static method', function (): void {
        $condition = PolicyCondition::greaterThan('age', 18);

        expect($condition->operator)->toBe(ConditionOperator::GreaterThan)
            ->and($condition->value)->toBe(18);
    });

    it('creates lessThan condition with static method', function (): void {
        $condition = PolicyCondition::lessThan('age', 65);

        expect($condition->operator)->toBe(ConditionOperator::LessThan);
    });

    it('creates in condition with static method', function (): void {
        $condition = PolicyCondition::in('status', ['active', 'pending']);

        expect($condition->operator)->toBe(ConditionOperator::In)
            ->and($condition->value)->toBe(['active', 'pending']);
    });

    it('creates notIn condition with static method', function (): void {
        $condition = PolicyCondition::notIn('status', ['deleted', 'archived']);

        expect($condition->operator)->toBe(ConditionOperator::NotIn);
    });

    it('creates contains condition with static method', function (): void {
        $condition = PolicyCondition::contains('name', 'admin');

        expect($condition->operator)->toBe(ConditionOperator::Contains);
    });

    it('creates startsWith condition with static method', function (): void {
        $condition = PolicyCondition::startsWith('email', 'admin@');

        expect($condition->operator)->toBe(ConditionOperator::StartsWith);
    });

    it('creates between condition with static method', function (): void {
        $condition = PolicyCondition::between('age', [18, 65]);

        expect($condition->operator)->toBe(ConditionOperator::Between)
            ->and($condition->value)->toBe([18, 65]);
    });

    it('creates isNull condition with static method', function (): void {
        $condition = PolicyCondition::isNull('deleted_at');

        expect($condition->operator)->toBe(ConditionOperator::IsNull)
            ->and($condition->value)->toBeNull();
    });

    it('creates isNotNull condition with static method', function (): void {
        $condition = PolicyCondition::isNotNull('verified_at');

        expect($condition->operator)->toBe(ConditionOperator::IsNotNull);
    });

    it('creates matches (regex) condition with static method', function (): void {
        $condition = PolicyCondition::matches('email', '/.*@example\.com$/');

        expect($condition->operator)->toBe(ConditionOperator::Matches);
    });

    it('converts to array', function (): void {
        $condition = PolicyCondition::equals('status', 'active');
        $array = $condition->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKey('attribute')
            ->and($array)->toHaveKey('operator')
            ->and($array)->toHaveKey('value')
            ->and($array)->toHaveKey('description')
            ->and($array['attribute'])->toBe('status')
            ->and($array['operator'])->toBe('eq')
            ->and($array['value'])->toBe('active');
    });

    it('evaluates equals condition correctly', function (): void {
        $condition = PolicyCondition::equals('status', 'active');

        expect($condition->evaluate(['status' => 'active']))->toBeTrue()
            ->and($condition->evaluate(['status' => 'inactive']))->toBeFalse();
    });

    it('evaluates nested attribute with dot notation', function (): void {
        $condition = PolicyCondition::equals('user.role', 'admin');

        expect($condition->evaluate(['user' => ['role' => 'admin']]))->toBeTrue()
            ->and($condition->evaluate(['user' => ['role' => 'user']]))->toBeFalse();
    });

    it('generates human-readable description', function (): void {
        $condition = PolicyCondition::equals('status', 'active');
        $description = $condition->describe();

        expect($description)->toBeString()
            ->and($description)->toContain('status')
            ->and($description)->toContain('active');
    });

    it('uses custom description when provided', function (): void {
        $condition = new PolicyCondition(
            attribute: 'status',
            operator: ConditionOperator::Equals,
            value: 'active',
            description: 'Custom description here'
        );

        expect($condition->describe())->toBe('Custom description here');
    });

    it('describes array values correctly', function (): void {
        $condition = PolicyCondition::in('status', ['active', 'pending']);
        $description = $condition->describe();

        expect($description)->toContain('active')
            ->and($description)->toContain('pending');
    });
});

describe('DiscoveredResource', function (): void {
    it('can be instantiated', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create', 'update', 'delete'],
            metadata: ['source' => 'test'],
            panel: 'admin',
        );

        expect($resource->fqcn)->toBe('App\\Filament\\Resources\\UserResource')
            ->and($resource->model)->toBe('App\\Models\\User')
            ->and($resource->panel)->toBe('admin')
            ->and($resource->permissions)->toHaveCount(4);
    });

    it('converts to permission keys', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create'],
            metadata: [],
            panel: 'admin',
        );

        $keys = $resource->toPermissionKeys();

        expect($keys)->toBeArray()
            ->and($keys)->toContain('user.view')
            ->and($keys)->toContain('user.create');
    });

    it('converts to array', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view'],
            metadata: [],
            panel: 'admin',
        );

        $array = $resource->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKey('fqcn')
            ->and($array)->toHaveKey('model')
            ->and($array)->toHaveKey('panel')
            ->and($array)->toHaveKey('permissions');
    });

    it('gets model basename', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view'],
            metadata: [],
        );

        expect($resource->getModelBasename())->toBe('User');
    });

    it('gets resource basename', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view'],
            metadata: [],
        );

        expect($resource->getResourceBasename())->toBe('UserResource');
    });

    it('gets policy class', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Filament\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view'],
            metadata: [],
        );

        expect($resource->getPolicyClass())->toBe('App\\Policies\\UserPolicy');
    });
});

describe('DiscoveredPage', function (): void {
    it('can be instantiated', function (): void {
        $page = new DiscoveredPage(
            fqcn: 'App\\Filament\\Pages\\Dashboard',
            title: 'Dashboard',
            panel: 'admin',
        );

        expect($page->fqcn)->toBe('App\\Filament\\Pages\\Dashboard')
            ->and($page->title)->toBe('Dashboard')
            ->and($page->panel)->toBe('admin');
    });

    it('generates permission key', function (): void {
        $page = new DiscoveredPage(
            fqcn: 'App\\Filament\\Pages\\Dashboard',
            title: 'Dashboard',
            panel: 'admin',
        );

        $key = $page->getPermissionKey();

        expect($key)->toBeString()
            ->and($key)->not->toBeEmpty();
    });

    it('converts to array', function (): void {
        $page = new DiscoveredPage(
            fqcn: 'App\\Filament\\Pages\\Dashboard',
            title: 'Dashboard',
            panel: 'admin',
        );

        $array = $page->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKey('fqcn')
            ->and($array)->toHaveKey('title')
            ->and($array)->toHaveKey('panel');
    });
});

describe('DiscoveredWidget', function (): void {
    it('can be instantiated', function (): void {
        $widget = new DiscoveredWidget(
            fqcn: 'App\\Filament\\Widgets\\StatsOverview',
            type: 'stats',
            panel: 'admin',
        );

        expect($widget->fqcn)->toBe('App\\Filament\\Widgets\\StatsOverview')
            ->and($widget->type)->toBe('stats')
            ->and($widget->panel)->toBe('admin');
    });

    it('generates permission key', function (): void {
        $widget = new DiscoveredWidget(
            fqcn: 'App\\Filament\\Widgets\\StatsOverview',
            type: 'stats',
            panel: 'admin',
        );

        $key = $widget->getPermissionKey();

        expect($key)->toBeString()
            ->and($key)->not->toBeEmpty();
    });

    it('converts to array', function (): void {
        $widget = new DiscoveredWidget(
            fqcn: 'App\\Filament\\Widgets\\StatsOverview',
            type: 'stats',
            panel: 'admin',
        );

        $array = $widget->toArray();

        expect($array)->toBeArray()
            ->and($array)->toHaveKey('fqcn')
            ->and($array)->toHaveKey('type')
            ->and($array)->toHaveKey('panel');
    });
});
