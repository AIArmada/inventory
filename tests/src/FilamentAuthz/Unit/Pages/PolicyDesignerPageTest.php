<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Models\AccessPolicy;
use AIArmada\FilamentAuthz\Pages\PolicyDesignerPage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PolicyDesignerPage', function () {
    it('has navigation icon', function () {
        expect(PolicyDesignerPage::getNavigationIcon())->toBe('heroicon-o-paint-brush');
    });

    it('has navigation label', function () {
        expect(PolicyDesignerPage::getNavigationLabel())->toBe('Policy Designer');
    });

    it('has navigation sort', function () {
        expect(PolicyDesignerPage::getNavigationSort())->toBe(50);
    });

    it('gets navigation group from config', function () {
        config(['filament-authz.navigation.group' => 'Test Group']);
        expect(PolicyDesignerPage::getNavigationGroup())->toBe('Test Group');
    });

    it('can access when access_policies feature is enabled', function () {
        config(['filament-authz.features.access_policies' => true]);
        expect(PolicyDesignerPage::canAccess())->toBeTrue();
    });

    it('cannot access when access_policies feature is disabled', function () {
        config(['filament-authz.features.access_policies' => false]);
        expect(PolicyDesignerPage::canAccess())->toBeFalse();
    });
});

describe('PolicyDesignerPage::mount', function () {
    it('initializes with one condition', function () {
        $page = new PolicyDesignerPage();
        $page->mount();

        expect($page->conditions)->toHaveCount(1);
    });

    it('initializes default values', function () {
        $page = new PolicyDesignerPage();
        $page->mount();

        expect($page->effect)->toBe('allow');
        expect($page->priority)->toBe(0);
        expect($page->combiningAlgorithm)->toBe('all');
    });
});

describe('PolicyDesignerPage::addCondition', function () {
    it('adds a new condition', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [];

        $page->addCondition();

        expect($page->conditions)->toHaveCount(1);
        expect($page->conditions[0])->toHaveKeys(['id', 'type', 'field', 'operator', 'value']);
    });

    it('adds conditions with unique ids', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [];

        $page->addCondition();
        $page->addCondition();

        expect($page->conditions)->toHaveCount(2);
        expect($page->conditions[0]['id'])->not->toBe($page->conditions[1]['id']);
    });

    it('sets default condition type to role', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [];

        $page->addCondition();

        expect($page->conditions[0]['type'])->toBe('role');
        expect($page->conditions[0]['field'])->toBe('role');
        expect($page->conditions[0]['operator'])->toBe('equals');
    });
});

describe('PolicyDesignerPage::removeCondition', function () {
    it('removes condition at specified index', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [
            ['id' => '1', 'type' => 'role', 'field' => 'role', 'operator' => 'equals', 'value' => 'admin'],
            ['id' => '2', 'type' => 'permission', 'field' => 'permission', 'operator' => 'equals', 'value' => 'view'],
        ];

        $page->removeCondition(0);

        expect($page->conditions)->toHaveCount(1);
        expect($page->conditions[0]['id'])->toBe('2');
    });

    it('reindexes array after removal', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [
            ['id' => '1', 'type' => 'role', 'field' => 'role', 'operator' => 'equals', 'value' => 'admin'],
            ['id' => '2', 'type' => 'permission', 'field' => 'permission', 'operator' => 'equals', 'value' => 'view'],
            ['id' => '3', 'type' => 'team', 'field' => 'team_id', 'operator' => 'equals', 'value' => '1'],
        ];

        $page->removeCondition(1);

        expect($page->conditions)->toHaveCount(2);
        expect(array_keys($page->conditions))->toBe([0, 1]);
    });
});

describe('PolicyDesignerPage::updateConditionType', function () {
    it('updates condition type and related fields', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [
            ['id' => '1', 'type' => 'role', 'field' => 'role', 'operator' => 'equals', 'value' => 'admin'],
        ];

        $page->updateConditionType(0, 'permission');

        expect($page->conditions[0]['type'])->toBe('permission');
        expect($page->conditions[0]['field'])->toBe('permission');
        expect($page->conditions[0]['operator'])->toBe('equals');
        expect($page->conditions[0]['value'])->toBe('');
    });

    it('updates to team condition type', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [
            ['id' => '1', 'type' => 'role', 'field' => 'role', 'operator' => 'equals', 'value' => 'admin'],
        ];

        $page->updateConditionType(0, 'team');

        expect($page->conditions[0]['type'])->toBe('team');
        expect($page->conditions[0]['field'])->toBe('team_id');
    });

    it('updates to time condition type', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [
            ['id' => '1', 'type' => 'role', 'field' => 'role', 'operator' => 'equals', 'value' => 'admin'],
        ];

        $page->updateConditionType(0, 'time');

        expect($page->conditions[0]['type'])->toBe('time');
        expect($page->conditions[0]['field'])->toBe('time');
        expect($page->conditions[0]['operator'])->toBe('between');
    });

    it('does not update for unknown type', function () {
        $page = new PolicyDesignerPage();
        $page->conditions = [
            ['id' => '1', 'type' => 'role', 'field' => 'role', 'operator' => 'equals', 'value' => 'admin'],
        ];

        $page->updateConditionType(0, 'unknown_type');

        expect($page->conditions[0]['type'])->toBe('role');
    });
});

describe('PolicyDesignerPage::getConditionTemplates', function () {
    it('returns condition templates array', function () {
        $page = new PolicyDesignerPage();
        $templates = $page->getConditionTemplates();

        expect($templates)->toBeArray();
        expect($templates)->toHaveKeys(['role', 'permission', 'team', 'time', 'ip', 'resource_type', 'ownership', 'attribute', 'department', 'clearance']);
    });

    it('each template has required keys', function () {
        $page = new PolicyDesignerPage();
        $templates = $page->getConditionTemplates();

        foreach ($templates as $template) {
            expect($template)->toHaveKeys(['label', 'icon', 'field', 'operator']);
        }
    });
});

describe('PolicyDesignerPage::getOperatorOptions', function () {
    it('returns operator options array', function () {
        $page = new PolicyDesignerPage();
        $options = $page->getOperatorOptions();

        expect($options)->toBeArray();
        expect($options)->toHaveKeys(['equals', 'not_equals', 'contains', 'in', 'not_in', 'gt', 'gte', 'lt', 'lte', 'between', 'regex']);
    });
});

describe('PolicyDesignerPage::getPreviewJson', function () {
    it('returns valid json', function () {
        $page = new PolicyDesignerPage();
        $page->policyName = 'Test Policy';
        $page->policyDescription = 'Test description';
        $page->effect = 'allow';
        $page->priority = 10;
        $page->combiningAlgorithm = 'all';
        $page->conditions = [];

        $json = $page->getPreviewJson();

        expect($json)->toBeString();
        expect(json_decode($json, true))->toBeArray();
    });

    it('contains policy data', function () {
        $page = new PolicyDesignerPage();
        $page->policyName = 'Admin Policy';
        $page->policyDescription = 'Admin access';
        $page->effect = 'allow';
        $page->priority = 100;
        $page->combiningAlgorithm = 'any';
        $page->conditions = [
            ['id' => '1', 'type' => 'role', 'field' => 'role', 'operator' => 'equals', 'value' => 'admin'],
        ];

        $data = json_decode($page->getPreviewJson(), true);

        expect($data['name'])->toBe('Admin Policy');
        expect($data['description'])->toBe('Admin access');
        expect($data['effect'])->toBe('allow');
        expect($data['priority'])->toBe(100);
        expect($data['combining_algorithm'])->toBe('any');
        expect($data['conditions'])->toHaveCount(1);
    });
});

describe('PolicyDesignerPage::getPreviewCode', function () {
    it('returns php code string', function () {
        $page = new PolicyDesignerPage();
        $page->policyName = 'Admin Policy';
        $page->conditions = [];

        $code = $page->getPreviewCode();

        expect($code)->toBeString();
        expect($code)->toContain('<?php');
        expect($code)->toContain('namespace App\\Policies');
        expect($code)->toContain('class AdminPolicy');
    });

    it('generates class name from policy name', function () {
        $page = new PolicyDesignerPage();
        $page->policyName = 'Super Admin Access';
        $page->conditions = [];

        $code = $page->getPreviewCode();

        expect($code)->toContain('class SuperAdminAccess');
    });

    it('uses default class name when policy name is null', function () {
        $page = new PolicyDesignerPage();
        $page->policyName = null;
        $page->conditions = [];

        $code = $page->getPreviewCode();

        expect($code)->toContain('class CustomPolicy');
    });
});

describe('PolicyDesignerPage::savePolicy', function () {
    it('does not save without policy name', function () {
        $page = new PolicyDesignerPage();
        $page->policyName = null;

        $page->savePolicy();

        expect(AccessPolicy::count())->toBe(0);
    });

    it('creates new policy', function () {
        $page = new PolicyDesignerPage();
        $page->policyName = 'Test Policy';
        $page->policyDescription = 'Test description';
        $page->effect = 'allow';
        $page->priority = 10;
        $page->combiningAlgorithm = 'all';
        $page->conditions = [];

        $page->savePolicy();

        $policy = AccessPolicy::where('name', 'Test Policy')->first();
        expect($policy)->not->toBeNull();
        expect($policy->description)->toBe('Test description');
        expect($policy->effect)->toBe('allow');
        expect($policy->priority)->toBe(10);
        expect($policy->is_active)->toBeTrue();
    });

    it('updates existing policy', function () {
        AccessPolicy::create([
            'name' => 'Existing Policy',
            'slug' => 'existing-policy',
            'description' => 'Old description',
            'effect' => 'deny',
            'target_action' => '*',
            'priority' => 5,
            'is_active' => false,
            'conditions' => [],
        ]);

        $page = new PolicyDesignerPage();
        $page->policyName = 'Existing Policy';
        $page->policyDescription = 'Updated description';
        $page->effect = 'allow';
        $page->priority = 20;
        $page->combiningAlgorithm = 'any';
        $page->conditions = [];

        $page->savePolicy();

        $policy = AccessPolicy::where('name', 'Existing Policy')->first();
        expect($policy->description)->toBe('Updated description');
        expect($policy->effect)->toBe('allow');
        expect($policy->priority)->toBe(20);
        expect($policy->is_active)->toBeTrue();
    });
});

describe('PolicyDesignerPage::testPolicy', function () {
    it('does not throw exception', function () {
        $page = new PolicyDesignerPage();

        expect(fn () => $page->testPolicy())->not->toThrow(Exception::class);
    });
});
