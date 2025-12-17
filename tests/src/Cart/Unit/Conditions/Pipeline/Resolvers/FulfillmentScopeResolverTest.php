<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\FulfillmentScopeResolver;

describe('FulfillmentScopeResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new FulfillmentScopeResolver;
    });

    it('returns fulfillments scope', function (): void {
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('scope');
        $method->setAccessible(true);

        $scope = $method->invoke($this->resolver);

        expect($scope)->toBe(ConditionScope::FULFILLMENTS);
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
                return 2000;
            }
        };

        $amount = $method->invoke($this->resolver, $object);

        expect($amount)->toBe(2000);
    });

    it('extracts base amount from object with getAmount method', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $object = new class {
            public function getAmount(): int
            {
                return 1500;
            }
        };

        $amount = $method->invoke($this->resolver, $object);

        expect($amount)->toBe(1500);
    });

    it('extracts base amount from object with amount method', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $object = new class {
            public function amount(): int
            {
                return 1200;
            }
        };

        $amount = $method->invoke($this->resolver, $object);

        expect($amount)->toBe(1200);
    });

    it('returns zero for non-extractable dataset', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $amount = $method->invoke($this->resolver, 'string');

        expect($amount)->toBe(0);
    });

    it('returns zero for empty array', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $amount = $method->invoke($this->resolver, []);

        expect($amount)->toBe(0);
    });

    it('returns zero for object without amount methods', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $object = new class {
            public function doSomething(): void
            {
            }
        };

        $amount = $method->invoke($this->resolver, $object);

        expect($amount)->toBe(0);
    });

    it('calculates initial amount from datasets', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('initialAmount');
        $method->setAccessible(true);

        $datasets = [
            ['base_amount' => 1000],
            ['base_amount' => 2000],
        ];

        $amount = $method->invoke($this->resolver, 5000, $datasets);

        expect($amount)->toBe(8000); // 5000 + 1000 + 2000
    });

    it('handles empty datasets for initial amount', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('initialAmount');
        $method->setAccessible(true);

        $amount = $method->invoke($this->resolver, 5000, []);

        expect($amount)->toBe(5000);
    });
});
