<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\PolicyType;
use AIArmada\FilamentAuthz\Services\PolicyGeneratorService;

test('policy type enum has labels', function (): void {
    expect(PolicyType::Basic->label())->toContain('Basic')
        ->and(PolicyType::Hierarchical->label())->toContain('Hierarchical')
        ->and(PolicyType::Contextual->label())->toContain('Contextual')
        ->and(PolicyType::Temporal->label())->toContain('Temporal')
        ->and(PolicyType::Abac->label())->toContain('ABAC')
        ->and(PolicyType::Composite->label())->toContain('Composite');
});

test('policy type enum has descriptions', function (): void {
    foreach (PolicyType::cases() as $type) {
        expect($type->description())->toBeString()->not->toBeEmpty();
    }
});

test('policy type provides single param methods', function (): void {
    $methods = PolicyType::singleParamMethods();

    expect($methods)->toContain('viewAny')
        ->toContain('create');
});

test('policy type provides owner aware methods', function (): void {
    $methods = PolicyType::ownerAwareMethods();

    expect($methods)->toContain('view')
        ->toContain('update')
        ->toContain('delete');
});

test('policy generator service can be instantiated', function (): void {
    $service = app(PolicyGeneratorService::class);

    expect($service)->toBeInstanceOf(PolicyGeneratorService::class);
});

test('policy generator creates basic policy', function (): void {
    $service = app(PolicyGeneratorService::class);

    $result = $service->generate(
        'App\\Models\\User',
        PolicyType::Basic,
        ['path' => storage_path('test-policy.php')]
    );

    expect($result->content)
        ->toContain('class UserPolicy')
        ->toContain('function viewAny')
        ->toContain('function view')
        ->toContain('function create')
        ->toContain('function update')
        ->toContain('function delete');
});

test('policy generator creates hierarchical policy', function (): void {
    $service = app(PolicyGeneratorService::class);

    $result = $service->generate(
        'App\\Models\\Post',
        PolicyType::Hierarchical
    );

    expect($result->content)
        ->toContain('class PostPolicy')
        ->toContain('checkWithHierarchy');
});

test('policy generator creates contextual policy', function (): void {
    $service = app(PolicyGeneratorService::class);

    $result = $service->generate(
        'App\\Models\\Order',
        PolicyType::Contextual
    );

    expect($result->content)
        ->toContain('class OrderPolicy')
        ->toContain('checkInContext');
});

test('policy generator creates temporal policy', function (): void {
    $service = app(PolicyGeneratorService::class);

    $result = $service->generate(
        'App\\Models\\Project',
        PolicyType::Temporal
    );

    expect($result->content)
        ->toContain('class ProjectPolicy')
        ->toContain('hasTemporalGrant');
});

test('policy generator creates composite policy', function (): void {
    $service = app(PolicyGeneratorService::class);

    $result = $service->generate(
        'App\\Models\\Document',
        PolicyType::Composite
    );

    expect($result->content)
        ->toContain('class DocumentPolicy')
        ->toContain('evaluatePolicy');
});

test('generated policy can detect diff on existing file', function (): void {
    $service = app(PolicyGeneratorService::class);

    $result = $service->generate(
        'App\\Models\\User',
        PolicyType::Basic,
        ['path' => __FILE__] // This file exists
    );

    expect($result->getDiff())->not->toBeNull();
});

test('generated policy returns null diff for non-existent file', function (): void {
    $service = app(PolicyGeneratorService::class);

    $result = $service->generate(
        'App\\Models\\User',
        PolicyType::Basic,
        ['path' => '/non/existent/path.php']
    );

    expect($result->getDiff())->toBeNull();
});
