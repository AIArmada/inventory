<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Models\AccessPolicy;
use Illuminate\Support\Carbon;

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

describe('AccessPolicy Model', function (): void {
    describe('getEffectEnum', function (): void {
        it('returns PolicyEffect enum from effect string', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Allow Orders',
                'slug' => 'allow-orders',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
            ]);

            expect($policy->getEffectEnum())->toBe(PolicyEffect::Allow);
        });

        it('returns Deny for invalid effect', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Invalid',
                'slug' => 'invalid',
                'effect' => 'invalid_effect',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
            ]);

            expect($policy->getEffectEnum())->toBe(PolicyEffect::Deny);
        });
    });

    describe('isValid', function (): void {
        it('returns false when inactive', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Inactive Policy',
                'slug' => 'inactive-policy',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => false, 'conditions' => [],
            ]);

            expect($policy->isValid())->toBeFalse();
        });

        it('returns true when active with no date restrictions', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Active Policy',
                'slug' => 'active-policy',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
            ]);

            expect($policy->isValid())->toBeTrue();
        });

        it('returns false when valid_from is in the future', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Future Policy',
                'slug' => 'future-policy',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'valid_from' => Carbon::now()->addDay(),
            ]);

            expect($policy->isValid())->toBeFalse();
        });

        it('returns false when valid_until is in the past', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Expired Policy',
                'slug' => 'expired-policy',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'valid_until' => Carbon::now()->subDay(),
            ]);

            expect($policy->isValid())->toBeFalse();
        });

        it('returns true when within valid date range', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Valid Range Policy',
                'slug' => 'valid-range-policy',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'valid_from' => Carbon::now()->subDay(),
                'valid_until' => Carbon::now()->addDay(),
            ]);

            expect($policy->isValid())->toBeTrue();
        });
    });

    describe('appliesTo', function (): void {
        it('matches exact action', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Exact Match',
                'slug' => 'exact-match',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
            ]);

            expect($policy->appliesTo('orders.view'))->toBeTrue()
                ->and($policy->appliesTo('orders.create'))->toBeFalse();
        });

        it('matches wildcard action', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Wildcard Match',
                'slug' => 'wildcard-match',
                'effect' => 'allow',
                'target_action' => '*',
                'is_active' => true, 'conditions' => [],
            ]);

            expect($policy->appliesTo('orders.view'))->toBeTrue()
                ->and($policy->appliesTo('products.create'))->toBeTrue();
        });

        it('matches prefix wildcard action', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Prefix Match',
                'slug' => 'prefix-match',
                'effect' => 'allow',
                'target_action' => 'orders.*',
                'is_active' => true, 'conditions' => [],
            ]);

            expect($policy->appliesTo('orders.view'))->toBeTrue()
                ->and($policy->appliesTo('orders.create'))->toBeTrue()
                ->and($policy->appliesTo('products.view'))->toBeFalse();
        });

        it('matches with resource', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Resource Match',
                'slug' => 'resource-match',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'target_resource' => 'App\\Models\\Order',
                'is_active' => true, 'conditions' => [],
            ]);

            expect($policy->appliesTo('orders.view', 'App\\Models\\Order'))->toBeTrue()
                ->and($policy->appliesTo('orders.view', 'App\\Models\\Product'))->toBeFalse();
        });

        it('matches wildcard resource', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Wildcard Resource',
                'slug' => 'wildcard-resource',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'target_resource' => '*',
                'is_active' => true, 'conditions' => [],
            ]);

            expect($policy->appliesTo('orders.view', 'App\\Models\\Order'))->toBeTrue()
                ->and($policy->appliesTo('orders.view', 'App\\Models\\Product'))->toBeTrue();
        });
    });

    describe('evaluate', function (): void {
        it('returns NotApplicable when policy is invalid', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Inactive',
                'slug' => 'inactive',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => false, 'conditions' => [],
            ]);

            $result = $policy->evaluate([]);

            expect($result)->toBe(PolicyDecision::NotApplicable);
        });

        it('returns Permit when conditions pass with allow effect', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Allow All',
                'slug' => 'allow-all',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'conditions' => [],
            ]);

            $result = $policy->evaluate([]);

            expect($result)->toBe(PolicyDecision::Permit);
        });

        it('returns Deny when conditions pass with deny effect', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Deny All',
                'slug' => 'deny-all',
                'effect' => 'deny',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'conditions' => [],
            ]);

            $result = $policy->evaluate([]);

            expect($result)->toBe(PolicyDecision::Deny);
        });

        it('evaluates conditions correctly', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Conditional Allow',
                'slug' => 'conditional-allow',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'conditions' => [
                    ['attribute' => 'role', 'operator' => 'eq', 'value' => 'admin'],
                ],
            ]);

            $permitResult = $policy->evaluate(['subject' => ['role' => 'admin']]);
            $denyResult = $policy->evaluate(['subject' => ['role' => 'user']]);

            expect($permitResult)->toBe(PolicyDecision::Permit)
                ->and($denyResult)->toBe(PolicyDecision::NotApplicable);
        });
    });

    describe('evaluateConditions', function (): void {
        it('returns true for empty conditions', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'No Conditions',
                'slug' => 'no-conditions',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'conditions' => [],
            ]);

            expect($policy->evaluateConditions([]))->toBeTrue();
        });

        it('returns true when condition attribute is null', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Null Attribute',
                'slug' => 'null-attribute',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'conditions' => [
                    ['operator' => 'eq', 'value' => 'test'],
                ],
            ]);

            expect($policy->evaluateConditions([]))->toBeTrue();
        });

        it('returns false when any condition fails', function (): void {
            $policy = AccessPolicy::create([
                'name' => 'Multiple Conditions',
                'slug' => 'multiple-conditions',
                'effect' => 'allow',
                'target_action' => 'orders.view',
                'is_active' => true, 'conditions' => [],
                'conditions' => [
                    ['attribute' => 'role', 'operator' => 'eq', 'value' => 'admin'],
                    ['attribute' => 'active', 'operator' => 'eq', 'value' => true],
                ],
            ]);

            $result = $policy->evaluateConditions(['subject' => ['role' => 'admin', 'active' => false]]);

            expect($result)->toBeFalse();
        });
    });

    describe('scopes', function (): void {
        it('scopeActive filters active policies', function (): void {
            AccessPolicy::create(['name' => 'Active', 'slug' => 'active', 'effect' => 'allow', 'target_action' => '*', 'is_active' => true, 'conditions' => []]);
            AccessPolicy::create(['name' => 'Inactive', 'slug' => 'inactive', 'effect' => 'allow', 'target_action' => '*', 'is_active' => false, 'conditions' => []]);

            $count = AccessPolicy::active()->count();

            expect($count)->toBe(1);
        });

        it('scopeCurrentlyValid filters valid policies', function (): void {
            AccessPolicy::create(['name' => 'Valid', 'slug' => 'valid', 'effect' => 'allow', 'target_action' => '*', 'is_active' => true, 'conditions' => []]);
            AccessPolicy::create(['name' => 'Expired', 'slug' => 'expired', 'effect' => 'allow', 'target_action' => '*', 'is_active' => true, 'conditions' => [], 'valid_until' => Carbon::now()->subDay()]);
            AccessPolicy::create(['name' => 'Future', 'slug' => 'future', 'effect' => 'allow', 'target_action' => '*', 'is_active' => true, 'conditions' => [], 'valid_from' => Carbon::now()->addDay()]);

            $count = AccessPolicy::currentlyValid()->count();

            expect($count)->toBe(1);
        });

        it('scopeForAction filters by action', function (): void {
            AccessPolicy::create(['name' => 'Orders', 'slug' => 'orders', 'effect' => 'allow', 'target_action' => 'orders.view', 'is_active' => true, 'conditions' => []]);
            AccessPolicy::create(['name' => 'Products', 'slug' => 'products', 'effect' => 'allow', 'target_action' => 'products.view', 'is_active' => true, 'conditions' => []]);
            AccessPolicy::create(['name' => 'All', 'slug' => 'all', 'effect' => 'allow', 'target_action' => '*', 'is_active' => true, 'conditions' => []]);

            $count = AccessPolicy::forAction('orders.view')->count();

            expect($count)->toBe(2); // Exact match + wildcard
        });

        it('scopeForResource filters by resource', function (): void {
            AccessPolicy::create(['name' => 'Order Resource', 'slug' => 'order-resource', 'effect' => 'allow', 'target_action' => '*', 'target_resource' => 'App\\Models\\Order', 'is_active' => true, 'conditions' => []]);
            AccessPolicy::create(['name' => 'All Resources', 'slug' => 'all-resources', 'effect' => 'allow', 'target_action' => '*', 'target_resource' => '*', 'is_active' => true, 'conditions' => []]);
            AccessPolicy::create(['name' => 'No Resource', 'slug' => 'no-resource', 'effect' => 'allow', 'target_action' => '*', 'is_active' => true, 'conditions' => []]);

            $count = AccessPolicy::forResource('App\\Models\\Order')->count();

            expect($count)->toBe(3); // Exact + wildcard + null
        });

        it('scopeOrderByPriority orders correctly', function (): void {
            AccessPolicy::create(['name' => 'Low', 'slug' => 'low', 'effect' => 'allow', 'target_action' => '*', 'priority' => 1, 'is_active' => true, 'conditions' => []]);
            AccessPolicy::create(['name' => 'High', 'slug' => 'high', 'effect' => 'allow', 'target_action' => '*', 'priority' => 100, 'is_active' => true, 'conditions' => []]);
            AccessPolicy::create(['name' => 'Medium', 'slug' => 'medium', 'effect' => 'allow', 'target_action' => '*', 'priority' => 50, 'is_active' => true, 'conditions' => []]);

            $policies = AccessPolicy::orderByPriority('desc')->pluck('name')->toArray();

            expect($policies)->toBe(['High', 'Medium', 'Low']);
        });
    });

    describe('getTable', function (): void {
        it('returns table name from config', function (): void {
            $policy = new AccessPolicy;

            expect($policy->getTable())->toBe('authz_access_policies');
        });
    });
});
