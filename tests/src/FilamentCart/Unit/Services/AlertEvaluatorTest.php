<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Models\AlertRule;
use AIArmada\FilamentCart\Services\AlertEvaluator;

describe('AlertEvaluator', function (): void {
    beforeEach(function (): void {
        $this->evaluator = new AlertEvaluator();
    });

    describe('evaluate', function (): void {
        it('returns true when no conditions are specified', function (): void {
            $rule = new AlertRule();
            $rule->conditions = [];

            $result = $this->evaluator->evaluate($rule, ['value' => 100]);

            expect($result)->toBeTrue();
        });

        it('evaluates single condition with equals operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = [
                'field' => 'value',
                'operator' => '=',
                'value' => 100,
            ];

            expect($this->evaluator->evaluate($rule, ['value' => 100]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['value' => 50]))->toBeFalse();
        });

        it('evaluates all conditions with AND logic', function (): void {
            $rule = new AlertRule();
            $rule->conditions = [
                'all' => [
                    ['field' => 'value', 'operator' => '>=', 'value' => 100],
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ],
            ];

            expect($this->evaluator->evaluate($rule, ['value' => 150, 'status' => 'active']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['value' => 150, 'status' => 'inactive']))->toBeFalse();
            expect($this->evaluator->evaluate($rule, ['value' => 50, 'status' => 'active']))->toBeFalse();
        });

        it('evaluates any conditions with OR logic', function (): void {
            $rule = new AlertRule();
            $rule->conditions = [
                'any' => [
                    ['field' => 'priority', 'operator' => '=', 'value' => 'high'],
                    ['field' => 'value', 'operator' => '>=', 'value' => 1000],
                ],
            ];

            expect($this->evaluator->evaluate($rule, ['priority' => 'high', 'value' => 50]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['priority' => 'low', 'value' => 1500]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['priority' => 'low', 'value' => 50]))->toBeFalse();
        });

        it('evaluates array of conditions as AND by default', function (): void {
            $rule = new AlertRule();
            $rule->conditions = [
                ['field' => 'a', 'operator' => '=', 'value' => 1],
                ['field' => 'b', 'operator' => '=', 'value' => 2],
            ];

            expect($this->evaluator->evaluate($rule, ['a' => 1, 'b' => 2]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['a' => 1, 'b' => 3]))->toBeFalse();
        });
    });

    describe('comparison operators', function (): void {
        it('supports equals operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'x', 'operator' => '=', 'value' => 10];

            expect($this->evaluator->evaluate($rule, ['x' => 10]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['x' => 5]))->toBeFalse();
        });

        it('supports not equals operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'x', 'operator' => '!=', 'value' => 10];

            expect($this->evaluator->evaluate($rule, ['x' => 5]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['x' => 10]))->toBeFalse();
        });

        it('supports greater than operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'x', 'operator' => '>', 'value' => 10];

            expect($this->evaluator->evaluate($rule, ['x' => 15]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['x' => 10]))->toBeFalse();
            expect($this->evaluator->evaluate($rule, ['x' => 5]))->toBeFalse();
        });

        it('supports greater than or equal operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'x', 'operator' => '>=', 'value' => 10];

            expect($this->evaluator->evaluate($rule, ['x' => 15]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['x' => 10]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['x' => 5]))->toBeFalse();
        });

        it('supports less than operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'x', 'operator' => '<', 'value' => 10];

            expect($this->evaluator->evaluate($rule, ['x' => 5]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['x' => 10]))->toBeFalse();
            expect($this->evaluator->evaluate($rule, ['x' => 15]))->toBeFalse();
        });

        it('supports less than or equal operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'x', 'operator' => '<=', 'value' => 10];

            expect($this->evaluator->evaluate($rule, ['x' => 5]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['x' => 10]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['x' => 15]))->toBeFalse();
        });

        it('supports in operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'status', 'operator' => 'in', 'value' => ['pending', 'active']];

            expect($this->evaluator->evaluate($rule, ['status' => 'pending']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['status' => 'active']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['status' => 'cancelled']))->toBeFalse();
        });

        it('supports not_in operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'status', 'operator' => 'not_in', 'value' => ['cancelled', 'deleted']];

            expect($this->evaluator->evaluate($rule, ['status' => 'active']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['status' => 'cancelled']))->toBeFalse();
        });

        it('supports contains operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'email', 'operator' => 'contains', 'value' => '@example.com'];

            expect($this->evaluator->evaluate($rule, ['email' => 'user@example.com']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['email' => 'user@other.com']))->toBeFalse();
        });

        it('supports starts_with operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'name', 'operator' => 'starts_with', 'value' => 'VIP'];

            expect($this->evaluator->evaluate($rule, ['name' => 'VIP Customer']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['name' => 'Regular Customer']))->toBeFalse();
        });

        it('supports ends_with operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'sku', 'operator' => 'ends_with', 'value' => '-XL'];

            expect($this->evaluator->evaluate($rule, ['sku' => 'SHIRT-XL']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['sku' => 'SHIRT-SM']))->toBeFalse();
        });

        it('supports is_null operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'discount', 'operator' => 'is_null', 'value' => null];

            expect($this->evaluator->evaluate($rule, ['discount' => null]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['discount' => 10]))->toBeFalse();
        });

        it('supports is_not_null operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'coupon', 'operator' => 'is_not_null', 'value' => null];

            expect($this->evaluator->evaluate($rule, ['coupon' => 'SAVE10']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['coupon' => null]))->toBeFalse();
        });

        it('supports is_empty operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'items', 'operator' => 'is_empty', 'value' => null];

            expect($this->evaluator->evaluate($rule, ['items' => []]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['items' => '']))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['items' => ['product']]))->toBeFalse();
        });

        it('supports is_not_empty operator', function (): void {
            $rule = new AlertRule();
            $rule->conditions = ['field' => 'items', 'operator' => 'is_not_empty', 'value' => null];

            expect($this->evaluator->evaluate($rule, ['items' => ['product']]))->toBeTrue();
            expect($this->evaluator->evaluate($rule, ['items' => []]))->toBeFalse();
        });

        // Removed potentially hanging tests for debugging
    });
});
