<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\PaymentScopeResolver;

describe('PaymentScopeResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new PaymentScopeResolver;
    });

    it('returns payments scope', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('scope');
        $method->setAccessible(true);

        $scope = $method->invoke($this->resolver);

        expect($scope)->toBe(ConditionScope::PAYMENTS);
    });

    it('extracts base amount from array with base_amount key', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $dataset = ['base_amount' => 5000];
        $amount = $method->invoke($this->resolver, $dataset);

        expect($amount)->toBe(5000);
    });

    it('extracts base amount from array with amount key', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $dataset = ['amount' => 3000];
        $amount = $method->invoke($this->resolver, $dataset);

        expect($amount)->toBe(3000);
    });

    it('extracts base amount from object with getBaseAmount method', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $object = new class {
            public function getBaseAmount(): int
            {
                return 4000;
            }
        };

        $amount = $method->invoke($this->resolver, $object);

        expect($amount)->toBe(4000);
    });

    it('returns zero for non-extractable dataset', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $amount = $method->invoke($this->resolver, 'string');

        expect($amount)->toBe(0);
    });

    it('calculates initial amount with extra payment', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('initialAmount');
        $method->setAccessible(true);

        $datasets = [
            ['base_amount' => 8000],
            ['base_amount' => 3000],
        ];

        // 11000 payments sum - 5000 current amount = 6000 extra
        // 5000 + 6000 = 11000
        $amount = $method->invoke($this->resolver, 5000, $datasets);

        expect($amount)->toBe(11000);
    });

    it('calculates initial amount with no extra when payments <= current', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('initialAmount');
        $method->setAccessible(true);

        $datasets = [
            ['base_amount' => 2000],
            ['base_amount' => 1000],
        ];

        // 3000 payments sum - 5000 current amount = -2000, max(0) = 0
        // 5000 + 0 = 5000
        $amount = $method->invoke($this->resolver, 5000, $datasets);

        expect($amount)->toBe(5000);
    });

    it('handles empty datasets', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('initialAmount');
        $method->setAccessible(true);

        $amount = $method->invoke($this->resolver, 5000, []);

        expect($amount)->toBe(5000);
    });
});
