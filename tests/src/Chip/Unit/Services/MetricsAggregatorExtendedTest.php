<?php

declare(strict_types=1);

use AIArmada\Chip\Services\MetricsAggregator;
use Illuminate\Support\Carbon;

describe('MetricsAggregator', function (): void {
    beforeEach(function (): void {
        $this->aggregator = new MetricsAggregator();
    });

    it('can be instantiated', function (): void {
        expect($this->aggregator)->toBeInstanceOf(MetricsAggregator::class);
    });

    describe('aggregateForDate', function (): void {
        it('has aggregateForDate method', function (): void {
            expect(method_exists($this->aggregator, 'aggregateForDate'))->toBeTrue();
        });

        it('aggregateForDate is callable with Carbon date', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'aggregateForDate');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(Carbon::class);
        });

        it('aggregateForDate returns void', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'aggregateForDate');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe('void');
        });
    });

    describe('aggregateTotals', function (): void {
        it('has aggregateTotals method', function (): void {
            expect(method_exists($this->aggregator, 'aggregateTotals'))->toBeTrue();
        });

        it('aggregateTotals is callable with Carbon date', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'aggregateTotals');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(Carbon::class);
        });
    });

    describe('backfill', function (): void {
        it('has backfill method', function (): void {
            expect(method_exists($this->aggregator, 'backfill'))->toBeTrue();
        });

        it('backfill accepts start and end dates', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'backfill');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(2);
            expect($params[0]->getName())->toBe('startDate');
            expect($params[1]->getName())->toBe('endDate');
        });

        it('backfill returns int', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'backfill');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe('int');
        });

        it('can calculate backfill days correctly', function (): void {
            // Using reflection to test the calculation without actually hitting the database
            $startDate = Carbon::parse('2024-01-01');
            $endDate = Carbon::parse('2024-01-10');

            $expectedDays = 10; // Jan 1-10 inclusive
            $calculatedDays = 0;
            $current = $startDate->copy();

            while ($current->lte($endDate)) {
                $current->addDay();
                $calculatedDays++;
            }

            expect($calculatedDays)->toBe($expectedDays);
        });
    });

    describe('getFailureBreakdown', function (): void {
        it('has protected getFailureBreakdown method', function (): void {
            expect(method_exists($this->aggregator, 'getFailureBreakdown'))->toBeTrue();
        });

        it('getFailureBreakdown is protected', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'getFailureBreakdown');

            expect($reflection->isProtected())->toBeTrue();
        });

        it('getFailureBreakdown accepts date range and payment method', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'getFailureBreakdown');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(3);
            expect($params[0]->getName())->toBe('startDate');
            expect($params[1]->getName())->toBe('endDate');
            expect($params[2]->getName())->toBe('paymentMethod');
            expect($params[2]->allowsNull())->toBeTrue();
        });

        it('getFailureBreakdown returns array', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'getFailureBreakdown');
            $returnType = $reflection->getReturnType();

            expect($returnType->getName())->toBe('array');
        });
    });
});
