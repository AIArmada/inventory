<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Pages\PolicyDesignerPage;
use AIArmada\FilamentAuthz\Pages\AuthzDashboardPage;

test('policy designer page class exists', function (): void {
    expect(class_exists(PolicyDesignerPage::class))->toBeTrue();
});

test('authz dashboard page class exists', function (): void {
    expect(class_exists(AuthzDashboardPage::class))->toBeTrue();
});

test('policy designer page has required properties', function (): void {
    $page = new PolicyDesignerPage();

    expect($page->policyName)->toBeNull();
    expect($page->effect)->toBe('allow');
    expect($page->priority)->toBe(0);
    expect($page->conditions)->toBeArray();
    expect($page->combiningAlgorithm)->toBe('all');
});

test('policy designer can get condition templates', function (): void {
    $page = new PolicyDesignerPage();
    $page->mount();

    $templates = $page->getConditionTemplates();

    expect($templates)
        ->toBeArray()
        ->toHaveKey('role')
        ->toHaveKey('permission')
        ->toHaveKey('team')
        ->toHaveKey('time')
        ->toHaveKey('ip')
        ->toHaveKey('ownership');
});

test('policy designer can get operator options', function (): void {
    $page = new PolicyDesignerPage();

    $operators = $page->getOperatorOptions();

    expect($operators)
        ->toBeArray()
        ->toHaveKey('equals')
        ->toHaveKey('not_equals')
        ->toHaveKey('contains')
        ->toHaveKey('in')
        ->toHaveKey('gt')
        ->toHaveKey('gte')
        ->toHaveKey('lt')
        ->toHaveKey('lte');
});

test('policy designer can add condition', function (): void {
    $page = new PolicyDesignerPage();
    $page->mount(); // This already adds one condition

    $initialCount = count($page->conditions);
    $page->addCondition();

    expect(count($page->conditions))->toBe($initialCount + 1);
});

test('policy designer can remove condition', function (): void {
    $page = new PolicyDesignerPage();
    $page->mount();
    $page->addCondition();

    $initialCount = count($page->conditions);
    $page->removeCondition(0);

    expect(count($page->conditions))->toBe($initialCount - 1);
});

test('policy designer generates preview json', function (): void {
    $page = new PolicyDesignerPage();
    $page->policyName = 'Test Policy';
    $page->effect = 'allow';
    $page->mount();

    $json = $page->getPreviewJson();

    expect($json)->toBeJson();

    $decoded = json_decode($json, true);
    expect($decoded)
        ->toHaveKey('name')
        ->toHaveKey('effect')
        ->toHaveKey('conditions');
});

test('policy designer generates preview code', function (): void {
    $page = new PolicyDesignerPage();
    $page->policyName = 'Test Policy';
    $page->mount();

    $code = $page->getPreviewCode();

    expect($code)
        ->toContain('<?php')
        ->toContain('namespace App\\Policies')
        ->toContain('class TestPolicy');
});

test('authz dashboard can get stats', function (): void {
    $page = new AuthzDashboardPage();

    $stats = $page->getStats();

    expect($stats)
        ->toBeArray()
        ->toHaveKey('roles')
        ->toHaveKey('permissions')
        ->toHaveKey('recent_activity')
        ->toHaveKey('denials');
});

test('authz dashboard can get recent activity', function (): void {
    $page = new AuthzDashboardPage();

    $activity = $page->getRecentActivity();

    expect($activity)->toBeArray();
});

test('authz dashboard can get hourly breakdown', function (): void {
    $page = new AuthzDashboardPage();

    $breakdown = $page->getHourlyBreakdown();

    expect($breakdown)->toBeArray();
});

test('authz dashboard can get permission usage heatmap', function (): void {
    $page = new AuthzDashboardPage();

    $heatmap = $page->getPermissionUsageHeatmap();

    expect($heatmap)->toBeArray();
});

test('authz dashboard can get anomalies', function (): void {
    $page = new AuthzDashboardPage();

    $anomalies = $page->getAnomalies();

    expect($anomalies)->toBeArray();
});

test('authz dashboard can set filter mode', function (): void {
    $page = new AuthzDashboardPage();

    $page->setFilterMode('denials');
    expect($page->filterMode)->toBe('denials');

    $page->setFilterMode('all');
    expect($page->filterMode)->toBe('all');
});

test('authz dashboard can set time range', function (): void {
    $page = new AuthzDashboardPage();

    $page->setTimeRange('7d');
    expect($page->timeRange)->toBe('7d');

    $page->setTimeRange('1h');
    expect($page->timeRange)->toBe('1h');
});
