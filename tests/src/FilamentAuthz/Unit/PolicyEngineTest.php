<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\PolicyCombiningAlgorithm;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Models\AccessPolicy;
use AIArmada\FilamentAuthz\Services\PolicyEngine;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    AccessPolicy::query()->delete();
    User::query()->delete();

    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

/**
 * Helper to create AccessPolicy with required defaults.
 *
 * @param  array<string, mixed>  $attributes
 */
function createPolicy(array $attributes): AccessPolicy
{
    return AccessPolicy::create(array_merge([
        'conditions' => [],
    ], $attributes));
}

describe('PolicyEngine', function (): void {
    describe('evaluate', function (): void {
        test('returns NotApplicable when no policies exist', function (): void {
            $engine = new PolicyEngine;

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::NotApplicable);
        });

        test('returns Permit for active allow policy', function (): void {
            createPolicy([
                'name' => 'Allow Orders',
                'slug' => 'allow-orders',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Permit);
        });

        test('returns Deny for active deny policy', function (): void {
            createPolicy([
                'name' => 'Deny Orders',
                'slug' => 'deny-orders',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Deny);
        });

        test('respects wildcard action matching', function (): void {
            createPolicy([
                'name' => 'Allow All Actions',
                'slug' => 'allow-all-actions',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => '*',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $decision = $engine->evaluate('orders.delete', 'Order');

            expect($decision)->toBe(PolicyDecision::Permit);
        });

        test('respects wildcard resource matching', function (): void {
            createPolicy([
                'name' => 'Allow All Resources',
                'slug' => 'allow-all-resources',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => '*',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Product');

            expect($decision)->toBe(PolicyDecision::Permit);
        });

        test('passes context to policy evaluation', function (): void {
            createPolicy([
                'name' => 'Allow Premium Users',
                'slug' => 'allow-premium-users',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'conditions' => [
                    [
                        'attribute' => 'user_type',
                        'operator' => 'eq',
                        'value' => 'premium',
                        'source' => 'subject',
                    ],
                ],
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $decisionWithPremium = $engine->evaluate('orders.create', 'Order', [
                'subject' => ['user_type' => 'premium'],
            ]);
            $decisionWithBasic = $engine->evaluate('orders.create', 'Order', [
                'subject' => ['user_type' => 'basic'],
            ]);

            expect($decisionWithPremium)->toBe(PolicyDecision::Permit);
            expect($decisionWithBasic)->toBe(PolicyDecision::NotApplicable);
        });
    });

    describe('isPermitted', function (): void {
        test('returns true when decision is Permit', function (): void {
            createPolicy([
                'name' => 'Allow Orders',
                'slug' => 'allow-orders',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            expect($engine->isPermitted('orders.create', 'Order'))->toBeTrue();
        });

        test('returns false when decision is Deny', function (): void {
            createPolicy([
                'name' => 'Deny Orders',
                'slug' => 'deny-orders',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            expect($engine->isPermitted('orders.create', 'Order'))->toBeFalse();
        });

        test('returns false when decision is NotApplicable', function (): void {
            $engine = new PolicyEngine;

            expect($engine->isPermitted('orders.create', 'Order'))->toBeFalse();
        });
    });

    describe('isDenied', function (): void {
        test('returns true when decision is Deny', function (): void {
            createPolicy([
                'name' => 'Deny Orders',
                'slug' => 'deny-orders',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            expect($engine->isDenied('orders.create', 'Order'))->toBeTrue();
        });

        test('returns false when decision is Permit', function (): void {
            createPolicy([
                'name' => 'Allow Orders',
                'slug' => 'allow-orders',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            expect($engine->isDenied('orders.create', 'Order'))->toBeFalse();
        });

        test('returns false when decision is NotApplicable', function (): void {
            $engine = new PolicyEngine;

            expect($engine->isDenied('orders.create', 'Order'))->toBeFalse();
        });
    });

    describe('evaluatePolicy', function (): void {
        test('returns NotApplicable for inactive policy', function (): void {
            $policy = createPolicy([
                'name' => 'Inactive Policy',
                'slug' => 'inactive-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => false,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $decision = $engine->evaluatePolicy($policy, []);

            expect($decision)->toBe(PolicyDecision::NotApplicable);
        });

        test('returns NotApplicable for policy not yet valid', function (): void {
            $policy = createPolicy([
                'name' => 'Future Policy',
                'slug' => 'future-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'valid_from' => now()->addDays(1),
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $decision = $engine->evaluatePolicy($policy, []);

            expect($decision)->toBe(PolicyDecision::NotApplicable);
        });

        test('returns NotApplicable for expired policy', function (): void {
            $policy = createPolicy([
                'name' => 'Expired Policy',
                'slug' => 'expired-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'valid_until' => now()->subDays(1),
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $decision = $engine->evaluatePolicy($policy, []);

            expect($decision)->toBe(PolicyDecision::NotApplicable);
        });

        test('returns NotApplicable when conditions fail', function (): void {
            $policy = createPolicy([
                'name' => 'Conditional Policy',
                'slug' => 'conditional-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'conditions' => [
                    [
                        'attribute' => 'amount',
                        'operator' => 'gt',
                        'value' => 100,
                        'source' => 'subject',
                    ],
                ],
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $decision = $engine->evaluatePolicy($policy, [
                'subject' => ['amount' => 50],
            ]);

            expect($decision)->toBe(PolicyDecision::NotApplicable);
        });

        test('returns Permit when conditions pass for allow policy', function (): void {
            $policy = createPolicy([
                'name' => 'Conditional Allow',
                'slug' => 'conditional-allow',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'conditions' => [
                    [
                        'attribute' => 'amount',
                        'operator' => 'gt',
                        'value' => 100,
                        'source' => 'subject',
                    ],
                ],
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $decision = $engine->evaluatePolicy($policy, [
                'subject' => ['amount' => 200],
            ]);

            expect($decision)->toBe(PolicyDecision::Permit);
        });

        test('returns Deny when conditions pass for deny policy', function (): void {
            $policy = createPolicy([
                'name' => 'Conditional Deny',
                'slug' => 'conditional-deny',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $decision = $engine->evaluatePolicy($policy, []);

            expect($decision)->toBe(PolicyDecision::Deny);
        });
    });

    describe('getApplicablePolicies', function (): void {
        test('returns policies matching action and resource', function (): void {
            createPolicy([
                'name' => 'Matching Policy',
                'slug' => 'matching-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);
            createPolicy([
                'name' => 'Non-matching Policy',
                'slug' => 'non-matching-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'products.create',
                'target_resource' => 'Product',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $policies = $engine->getApplicablePolicies('orders.create', 'Order');

            expect($policies)->toHaveCount(1);
            expect($policies->first()->name)->toBe('Matching Policy');
        });

        test('includes wildcard policies', function (): void {
            createPolicy([
                'name' => 'Wildcard Action',
                'slug' => 'wildcard-action',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => '*',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);
            createPolicy([
                'name' => 'Wildcard Resource',
                'slug' => 'wildcard-resource',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => '*',
                'is_active' => true,
                'priority' => 5,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $policies = $engine->getApplicablePolicies('orders.create', 'Order');

            expect($policies)->toHaveCount(2);
        });

        test('excludes inactive policies', function (): void {
            createPolicy([
                'name' => 'Active Policy',
                'slug' => 'active-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);
            createPolicy([
                'name' => 'Inactive Policy',
                'slug' => 'inactive-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => false,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $policies = $engine->getApplicablePolicies('orders.create', 'Order');

            expect($policies)->toHaveCount(1);
            expect($policies->first()->name)->toBe('Active Policy');
        });

        test('orders by priority descending', function (): void {
            createPolicy([
                'name' => 'Low Priority',
                'slug' => 'low-priority',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 5,
            ]);
            createPolicy([
                'name' => 'High Priority',
                'slug' => 'high-priority',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 20,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $policies = $engine->getApplicablePolicies('orders.create', 'Order');

            expect($policies->first()->name)->toBe('High Priority');
            expect($policies->last()->name)->toBe('Low Priority');
        });

        test('caches results', function (): void {
            createPolicy([
                'name' => 'Cached Policy',
                'slug' => 'cached-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            // First call - should query database
            $policies1 = $engine->getApplicablePolicies('orders.create', 'Order');

            // Second call - should return cached result
            $policies2 = $engine->getApplicablePolicies('orders.create', 'Order');

            expect($policies1->toArray())->toBe($policies2->toArray());
        });
    });

    describe('setCombiningAlgorithm', function (): void {
        test('changes the combining algorithm', function (): void {
            $engine = new PolicyEngine;

            $engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::PermitOverrides);

            expect($engine->getCombiningAlgorithm())->toBe(PolicyCombiningAlgorithm::PermitOverrides);
        });

        test('returns self for method chaining', function (): void {
            $engine = new PolicyEngine;

            $result = $engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::FirstApplicable);

            expect($result)->toBeInstanceOf(PolicyEngine::class);
        });
    });

    describe('getCombiningAlgorithm', function (): void {
        test('returns the current combining algorithm', function (): void {
            $engine = new PolicyEngine;

            $algorithm = $engine->getCombiningAlgorithm();

            expect($algorithm)->toBeInstanceOf(PolicyCombiningAlgorithm::class);
        });
    });

    describe('getActivePolicies', function (): void {
        test('returns all active policies', function (): void {
            createPolicy([
                'name' => 'Active Policy 1',
                'slug' => 'active-policy-1',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);
            createPolicy([
                'name' => 'Active Policy 2',
                'slug' => 'active-policy-2',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'products.create',
                'target_resource' => 'Product',
                'is_active' => true,
                'priority' => 5,
            ]);
            createPolicy([
                'name' => 'Inactive Policy',
                'slug' => 'inactive-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'users.create',
                'target_resource' => 'User',
                'is_active' => false,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $policies = $engine->getActivePolicies();

            expect($policies)->toHaveCount(2);
            expect($policies->pluck('name')->toArray())->toContain('Active Policy 1', 'Active Policy 2');
        });

        test('orders by priority descending', function (): void {
            createPolicy([
                'name' => 'Low Priority',
                'slug' => 'low-priority',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 1,
            ]);
            createPolicy([
                'name' => 'High Priority',
                'slug' => 'high-priority',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'products.create',
                'target_resource' => 'Product',
                'is_active' => true,
                'priority' => 100,
            ]);

            $engine = new PolicyEngine;

            $policies = $engine->getActivePolicies();

            expect($policies->first()->name)->toBe('High Priority');
        });
    });

    describe('getPoliciesByEffect', function (): void {
        test('returns policies with Allow effect', function (): void {
            createPolicy([
                'name' => 'Allow Policy',
                'slug' => 'allow-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);
            createPolicy([
                'name' => 'Deny Policy',
                'slug' => 'deny-policy',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'products.create',
                'target_resource' => 'Product',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $policies = $engine->getPoliciesByEffect(PolicyEffect::Allow);

            expect($policies)->toHaveCount(1);
            expect($policies->first()->name)->toBe('Allow Policy');
        });

        test('returns policies with Deny effect', function (): void {
            createPolicy([
                'name' => 'Allow Policy',
                'slug' => 'allow-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);
            createPolicy([
                'name' => 'Deny Policy',
                'slug' => 'deny-policy',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'products.create',
                'target_resource' => 'Product',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $policies = $engine->getPoliciesByEffect(PolicyEffect::Deny);

            expect($policies)->toHaveCount(1);
            expect($policies->first()->name)->toBe('Deny Policy');
        });

        test('excludes inactive policies', function (): void {
            createPolicy([
                'name' => 'Inactive Allow',
                'slug' => 'inactive-allow',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => false,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;

            $policies = $engine->getPoliciesByEffect(PolicyEffect::Allow);

            expect($policies)->toBeEmpty();
        });
    });

    describe('explain', function (): void {
        test('explains decision with matching policies', function (): void {
            createPolicy([
                'name' => 'Allow Orders',
                'slug' => 'allow-orders',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $explanation = $engine->explain('orders.create', 'Order');

            expect($explanation['decision'])->toBe(PolicyDecision::Permit);
            expect($explanation['matching_policies'])->toHaveCount(1);
            expect($explanation['matching_policies'][0]['name'])->toBe('Allow Orders');
            expect($explanation['matching_policies'][0]['effect'])->toBe('allow');
            expect($explanation['matching_policies'][0]['priority'])->toBe(10);
            expect($explanation['algorithm'])->toBeString();
        });

        test('explains NotApplicable when no policies match', function (): void {
            $engine = new PolicyEngine;

            $explanation = $engine->explain('orders.create', 'Order');

            expect($explanation['decision'])->toBe(PolicyDecision::NotApplicable);
            expect($explanation['matching_policies'])->toBeEmpty();
        });

        test('explains decision with multiple matching policies', function (): void {
            createPolicy([
                'name' => 'High Priority Allow',
                'slug' => 'high-priority-allow',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 20,
            ]);
            createPolicy([
                'name' => 'Low Priority Deny',
                'slug' => 'low-priority-deny',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 5,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $explanation = $engine->explain('orders.create', 'Order');

            expect($explanation['matching_policies'])->toHaveCount(2);
        });

        test('excludes policies that fail conditions', function (): void {
            createPolicy([
                'name' => 'Conditional Policy',
                'slug' => 'conditional-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'conditions' => [
                    [
                        'attribute' => 'role',
                        'operator' => 'eq',
                        'value' => 'admin',
                        'source' => 'subject',
                    ],
                ],
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $explanation = $engine->explain('orders.create', 'Order', [
                'subject' => ['role' => 'user'],
            ]);

            expect($explanation['matching_policies'])->toBeEmpty();
        });
    });

    describe('clearCache', function (): void {
        test('clears cached policy data', function (): void {
            $policy = createPolicy([
                'name' => 'Cached Policy',
                'slug' => 'cached-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            // Populate cache
            $engine->getApplicablePolicies('orders.create', 'Order');

            // Clear cache
            $engine->clearCache();

            // Update policy
            $policy->update(['effect' => PolicyEffect::Deny->value]);

            // New query should reflect the update
            Cache::flush();
            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Deny);
        });
    });

    describe('combining algorithms', function (): void {
        test('deny overrides permit with mixed policies', function (): void {
            createPolicy([
                'name' => 'Allow Policy',
                'slug' => 'allow-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);
            createPolicy([
                'name' => 'Deny Policy',
                'slug' => 'deny-policy',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 5,
            ]);

            $engine = new PolicyEngine;
            $engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::DenyOverrides);
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Deny);
        });

        test('permit overrides deny with mixed policies', function (): void {
            createPolicy([
                'name' => 'Allow Policy',
                'slug' => 'allow-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);
            createPolicy([
                'name' => 'Deny Policy',
                'slug' => 'deny-policy',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 5,
            ]);

            $engine = new PolicyEngine;
            $engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::PermitOverrides);
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Permit);
        });

        test('first applicable uses priority ordering', function (): void {
            createPolicy([
                'name' => 'High Priority Deny',
                'slug' => 'high-priority-deny',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 20,
            ]);
            createPolicy([
                'name' => 'Low Priority Allow',
                'slug' => 'low-priority-allow',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 5,
            ]);

            $engine = new PolicyEngine;
            $engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::FirstApplicable);
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Deny);
        });

        test('permit unless deny returns permit when no deny', function (): void {
            createPolicy([
                'name' => 'Allow Policy',
                'slug' => 'allow-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            $engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::PermitUnlessDeny);
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Permit);
        });

        test('deny unless permit returns deny when no permit', function (): void {
            createPolicy([
                'name' => 'Deny Policy',
                'slug' => 'deny-policy',
                'effect' => PolicyEffect::Deny->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            $engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::DenyUnlessPermit);
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Deny);
        });
    });

    describe('temporal validity', function (): void {
        test('includes policies within validity period', function (): void {
            createPolicy([
                'name' => 'Valid Policy',
                'slug' => 'valid-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'valid_from' => now()->subDays(1),
                'valid_until' => now()->addDays(1),
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::Permit);
        });

        test('excludes future policies from evaluation', function (): void {
            createPolicy([
                'name' => 'Future Policy',
                'slug' => 'future-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'valid_from' => now()->addDays(1),
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            // Policy is applicable (returned by query) but evaluatePolicy returns NotApplicable
            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::NotApplicable);
        });

        test('excludes expired policies from evaluation', function (): void {
            createPolicy([
                'name' => 'Expired Policy',
                'slug' => 'expired-policy',
                'effect' => PolicyEffect::Allow->value,
                'target_action' => 'orders.create',
                'target_resource' => 'Order',
                'is_active' => true,
                'valid_until' => now()->subDays(1),
                'priority' => 10,
            ]);

            $engine = new PolicyEngine;
            Cache::flush();

            // Policy is applicable (returned by query) but evaluatePolicy returns NotApplicable
            $decision = $engine->evaluate('orders.create', 'Order');

            expect($decision)->toBe(PolicyDecision::NotApplicable);
        });
    });
});
