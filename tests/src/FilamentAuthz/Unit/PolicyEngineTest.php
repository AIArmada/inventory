<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\PolicyCombiningAlgorithm;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Models\AccessPolicy;
use AIArmada\FilamentAuthz\Services\PolicyEngine;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    // Drop and recreate access policies table
    Schema::dropIfExists('authz_access_policies');
    Schema::create('authz_access_policies', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->string('effect'); // allow, deny
        $table->string('target_action');
        $table->string('target_resource')->nullable();
        $table->json('conditions')->nullable();
        $table->integer('priority')->default(0);
        $table->boolean('is_active')->default(true);
        $table->timestamp('valid_from')->nullable();
        $table->timestamp('valid_until')->nullable();
        $table->json('metadata')->nullable();
        $table->string('owner_type')->nullable();
        $table->string('owner_id')->nullable();
        $table->timestamps();
    });

    // Set default config
    config(['filament-authz.policies.combining_algorithm' => 'deny_overrides']);
    config(['filament-authz.cache_ttl' => 3600]);

    $this->policyEngine = app(PolicyEngine::class);
});

afterEach(function (): void {
    Schema::dropIfExists('authz_access_policies');
    Cache::flush();
});

test('can be instantiated', function (): void {
    expect($this->policyEngine)->toBeInstanceOf(PolicyEngine::class);
});

test('evaluate returns not applicable when no policies exist', function (): void {
    $decision = $this->policyEngine->evaluate('orders.view', 'orders');

    expect($decision)->toBe(PolicyDecision::NotApplicable);
});

test('evaluate returns permit for allow policy', function (): void {
    AccessPolicy::create([
        'name' => 'Allow Orders View',
        'slug' => 'allow-orders-view',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    $decision = $this->policyEngine->evaluate('orders.view', 'orders');

    expect($decision)->toBe(PolicyDecision::Permit);
});

test('evaluate returns deny for deny policy', function (): void {
    AccessPolicy::create([
        'name' => 'Deny Orders Delete',
        'slug' => 'deny-orders-delete',
        'effect' => 'deny',
        'target_action' => 'orders.delete',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    $decision = $this->policyEngine->evaluate('orders.delete', 'orders');

    expect($decision)->toBe(PolicyDecision::Deny);
});

test('evaluate respects inactive policies', function (): void {
    AccessPolicy::create([
        'name' => 'Inactive Policy',
        'slug' => 'inactive-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => false,
        'priority' => 1,
    ]);

    $decision = $this->policyEngine->evaluate('orders.view', 'orders');

    expect($decision)->toBe(PolicyDecision::NotApplicable);
});

test('evaluate respects valid_from date', function (): void {
    AccessPolicy::create([
        'name' => 'Future Policy',
        'slug' => 'future-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'valid_from' => now()->addDay(),
        'priority' => 1,
    ]);

    $decision = $this->policyEngine->evaluate('orders.view', 'orders');

    expect($decision)->toBe(PolicyDecision::NotApplicable);
});

test('evaluate respects valid_until date', function (): void {
    AccessPolicy::create([
        'name' => 'Expired Policy',
        'slug' => 'expired-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'valid_until' => now()->subDay(),
        'priority' => 1,
    ]);

    $decision = $this->policyEngine->evaluate('orders.view', 'orders');

    expect($decision)->toBe(PolicyDecision::NotApplicable);
});

test('evaluate matches wildcard action', function (): void {
    AccessPolicy::create([
        'name' => 'Allow All Actions',
        'slug' => 'allow-all-actions',
        'effect' => 'allow',
        'target_action' => '*',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    $decision = $this->policyEngine->evaluate('orders.view', 'orders');

    expect($decision)->toBe(PolicyDecision::Permit);
});

test('evaluate matches wildcard resource', function (): void {
    AccessPolicy::create([
        'name' => 'Allow All Resources',
        'slug' => 'allow-all-resources',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => '*',
        'is_active' => true,
        'priority' => 1,
    ]);

    $decision = $this->policyEngine->evaluate('orders.view', 'anything');

    expect($decision)->toBe(PolicyDecision::Permit);
});

test('isPermitted returns true when permitted', function (): void {
    AccessPolicy::create([
        'name' => 'Allow Policy',
        'slug' => 'allow-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    expect($this->policyEngine->isPermitted('orders.view', 'orders'))->toBeTrue();
});

test('isPermitted returns false when denied', function (): void {
    AccessPolicy::create([
        'name' => 'Deny Policy',
        'slug' => 'deny-policy',
        'effect' => 'deny',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    expect($this->policyEngine->isPermitted('orders.view', 'orders'))->toBeFalse();
});

test('isDenied returns true when denied', function (): void {
    AccessPolicy::create([
        'name' => 'Deny Policy',
        'slug' => 'deny-policy',
        'effect' => 'deny',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    expect($this->policyEngine->isDenied('orders.view', 'orders'))->toBeTrue();
});

test('isDenied returns false when permitted', function (): void {
    AccessPolicy::create([
        'name' => 'Allow Policy',
        'slug' => 'allow-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    expect($this->policyEngine->isDenied('orders.view', 'orders'))->toBeFalse();
});

test('setCombiningAlgorithm changes algorithm', function (): void {
    $this->policyEngine->setCombiningAlgorithm(PolicyCombiningAlgorithm::PermitOverrides);

    expect($this->policyEngine->getCombiningAlgorithm())->toBe(PolicyCombiningAlgorithm::PermitOverrides);
});

test('getCombiningAlgorithm returns current algorithm', function (): void {
    $algorithm = $this->policyEngine->getCombiningAlgorithm();

    expect($algorithm)->toBe(PolicyCombiningAlgorithm::DenyOverrides);
});

test('getActivePolicies returns only active policies', function (): void {
    AccessPolicy::create([
        'name' => 'Active Policy',
        'slug' => 'active-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'priority' => 1,
    ]);

    AccessPolicy::create([
        'name' => 'Inactive Policy',
        'slug' => 'inactive-policy',
        'effect' => 'allow',
        'target_action' => 'orders.create',
        'is_active' => false,
        'priority' => 1,
    ]);

    $activePolicies = $this->policyEngine->getActivePolicies();

    expect($activePolicies)->toHaveCount(1)
        ->and($activePolicies->first()->name)->toBe('Active Policy');
});

test('getPoliciesByEffect returns policies with specified effect', function (): void {
    AccessPolicy::create([
        'name' => 'Allow Policy',
        'slug' => 'allow-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'is_active' => true,
        'priority' => 1,
    ]);

    AccessPolicy::create([
        'name' => 'Deny Policy',
        'slug' => 'deny-policy',
        'effect' => 'deny',
        'target_action' => 'orders.delete',
        'is_active' => true,
        'priority' => 1,
    ]);

    $allowPolicies = $this->policyEngine->getPoliciesByEffect(PolicyEffect::Allow);

    expect($allowPolicies)->toHaveCount(1)
        ->and($allowPolicies->first()->name)->toBe('Allow Policy');
});

test('explain returns decision explanation', function (): void {
    AccessPolicy::create([
        'name' => 'Allow Orders View',
        'slug' => 'allow-orders-view',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 10,
    ]);

    $explanation = $this->policyEngine->explain('orders.view', 'orders');

    expect($explanation)
        ->toHaveKey('decision')
        ->toHaveKey('matching_policies')
        ->toHaveKey('algorithm')
        ->and($explanation['decision'])->toBe(PolicyDecision::Permit)
        ->and($explanation['matching_policies'])->toHaveCount(1)
        ->and($explanation['algorithm'])->toBe('deny_overrides');
});

test('explain returns empty matching policies when no policies match', function (): void {
    $explanation = $this->policyEngine->explain('orders.view', 'orders');

    expect($explanation['decision'])->toBe(PolicyDecision::NotApplicable)
        ->and($explanation['matching_policies'])->toBeEmpty();
});

test('getApplicablePolicies caches results', function (): void {
    AccessPolicy::create([
        'name' => 'Test Policy',
        'slug' => 'test-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    // First call - should cache
    $this->policyEngine->getApplicablePolicies('orders.view', 'orders');

    // Verify cache key exists
    $cacheKey = 'permissions:policies:applicable:orders.view:orders';
    $hasKey = Cache::getStore() instanceof \Illuminate\Cache\TaggableStore
        ? Cache::tags(['filament-authz', 'policies'])->has($cacheKey)
        : Cache::has($cacheKey);

    expect($hasKey)->toBeTrue();
});

test('clearCache removes cached policies', function (): void {
    AccessPolicy::create([
        'name' => 'Test Policy',
        'slug' => 'test-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    // Populate cache
    $this->policyEngine->getApplicablePolicies('orders.view', 'orders');

    // Clear cache
    $this->policyEngine->clearCache();

    // Cache should be cleared
    $cacheKey = 'permissions:policies:applicable:orders.view:orders';
    $hasKey = Cache::getStore() instanceof \Illuminate\Cache\TaggableStore
        ? Cache::tags(['filament-authz', 'policies'])->has($cacheKey)
        : Cache::has($cacheKey);

    expect($hasKey)->toBeFalse();
});

test('deny overrides algorithm denies when any policy denies', function (): void {
    $this->policyEngine->setCombiningAlgorithm(PolicyCombiningAlgorithm::DenyOverrides);

    AccessPolicy::create([
        'name' => 'Allow Policy',
        'slug' => 'allow-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    AccessPolicy::create([
        'name' => 'Deny Policy',
        'slug' => 'deny-policy',
        'effect' => 'deny',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 2,
    ]);

    $decision = $this->policyEngine->evaluate('orders.view', 'orders');

    expect($decision)->toBe(PolicyDecision::Deny);
});

test('permit overrides algorithm permits when any policy permits', function (): void {
    $this->policyEngine->setCombiningAlgorithm(PolicyCombiningAlgorithm::PermitOverrides);

    AccessPolicy::create([
        'name' => 'Allow Policy',
        'slug' => 'allow-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    AccessPolicy::create([
        'name' => 'Deny Policy',
        'slug' => 'deny-policy',
        'effect' => 'deny',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 2,
    ]);

    $decision = $this->policyEngine->evaluate('orders.view', 'orders');

    expect($decision)->toBe(PolicyDecision::Permit);
});

test('evaluatePolicy with context conditions', function (): void {
    $policy = AccessPolicy::create([
        'name' => 'Conditional Policy',
        'slug' => 'conditional-policy',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'conditions' => [
            [
                'attribute' => 'role',
                'operator' => 'eq',
                'value' => 'admin',
            ],
        ],
        'is_active' => true,
        'priority' => 1,
    ]);

    // With matching context
    $decisionMatch = $this->policyEngine->evaluatePolicy($policy, ['role' => 'admin']);
    expect($decisionMatch)->toBe(PolicyDecision::Permit);

    // With non-matching context
    $decisionNoMatch = $this->policyEngine->evaluatePolicy($policy, ['role' => 'user']);
    expect($decisionNoMatch)->toBe(PolicyDecision::NotApplicable);
});

test('policies are ordered by priority', function (): void {
    AccessPolicy::create([
        'name' => 'Low Priority',
        'slug' => 'low-priority',
        'effect' => 'allow',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 1,
    ]);

    AccessPolicy::create([
        'name' => 'High Priority',
        'slug' => 'high-priority',
        'effect' => 'deny',
        'target_action' => 'orders.view',
        'target_resource' => 'orders',
        'is_active' => true,
        'priority' => 10,
    ]);

    $policies = $this->policyEngine->getApplicablePolicies('orders.view', 'orders');

    expect($policies->first()->name)->toBe('High Priority')
        ->and($policies->last()->name)->toBe('Low Priority');
});
