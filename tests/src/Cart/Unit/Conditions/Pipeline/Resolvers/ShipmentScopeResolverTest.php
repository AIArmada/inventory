<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Conditions\Pipeline\Resolvers\ShipmentScopeResolver;

describe('ShipmentScopeResolver', function (): void {
    beforeEach(function (): void {
        $this->resolver = new ShipmentScopeResolver;
    });

    it('returns shipments scope', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('scope');
        $method->setAccessible(true);

        $scope = $method->invoke($this->resolver);

        expect($scope)->toBe(ConditionScope::SHIPMENTS);
    });

    it('extracts base amount from array with base_amount key', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $dataset = ['base_amount' => 2000];
        $amount = $method->invoke($this->resolver, $dataset);

        expect($amount)->toBe(2000);
    });

    it('extracts base amount from array with amount key', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $dataset = ['amount' => 1500];
        $amount = $method->invoke($this->resolver, $dataset);

        expect($amount)->toBe(1500);
    });

    it('extracts base amount from object with getBaseAmount method', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $object = new class {
            public function getBaseAmount(): int
            {
                return 2500;
            }
        };

        $amount = $method->invoke($this->resolver, $object);

        expect($amount)->toBe(2500);
    });

    it('extracts base amount from object with baseAmount method', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $object = new class {
            public function baseAmount(): int
            {
                return 3000;
            }
        };

        $amount = $method->invoke($this->resolver, $object);

        expect($amount)->toBe(3000);
    });

    it('returns zero for non-extractable dataset', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('extractBaseAmount');
        $method->setAccessible(true);

        $amount = $method->invoke($this->resolver, 123);

        expect($amount)->toBe(0);
    });

    it('calculates initial amount from datasets', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('initialAmount');
        $method->setAccessible(true);

        $datasets = [
            ['base_amount' => 1000],
            ['base_amount' => 500],
        ];

        $amount = $method->invoke($this->resolver, 5000, $datasets);

        expect($amount)->toBe(6500); // 5000 + 1000 + 500
    });

    it('handles empty datasets for initial amount', function (): void {
        $reflection = new ReflectionClass($this->resolver);
        $method = $reflection->getMethod('initialAmount');
        $method->setAccessible(true);

        $amount = $method->invoke($this->resolver, 5000, []);

        expect($amount)->toBe(5000);
    });
});
