<?php

declare(strict_types=1);

use AIArmada\Chip\Services\MetricsAggregator;
use Illuminate\Support\Carbon;

describe('MetricsAggregator', function (): void {
    beforeEach(function (): void {
        $this->aggregator = new MetricsAggregator();
    });

    describe('instantiation', function (): void {
        it('can be instantiated', function (): void {
            expect($this->aggregator)->toBeInstanceOf(MetricsAggregator::class);
        });
    });

    describe('method signatures', function (): void {
        it('aggregateForDate accepts Carbon date', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'aggregateForDate');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(Carbon::class);
        });

        it('aggregateTotals accepts Carbon date', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'aggregateTotals');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(1);
            expect($params[0]->getType()->getName())->toBe(Carbon::class);
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

        it('getFailureBreakdown is protected', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'getFailureBreakdown');

            expect($reflection->isProtected())->toBeTrue();
        });

        it('getFailureBreakdown accepts start, end dates and payment method', function (): void {
            $reflection = new ReflectionMethod($this->aggregator, 'getFailureBreakdown');
            $params = $reflection->getParameters();

            expect($params)->toHaveCount(3);
            expect($params[0]->getName())->toBe('startDate');
            expect($params[1]->getName())->toBe('endDate');
            expect($params[2]->getName())->toBe('paymentMethod');
        });
    });

    describe('aggregateTotals execution', function (): void {
        it('does not throw when called with date having no metrics', function (): void {
            $date = Carbon::now()->subDays(50);

            // This should not throw even with no input metrics
            $exception = null;
            try {
                $this->aggregator->aggregateTotals($date);
            } catch (Throwable $e) {
                $exception = $e;
            }

            expect($exception)->toBeNull();
        });

        it('does not throw when called with recent date', function (): void {
            $date = Carbon::now()->subDays(1);

            $exception = null;
            try {
                $this->aggregator->aggregateTotals($date);
            } catch (Throwable $e) {
                $exception = $e;
            }

            expect($exception)->toBeNull();
        });
    });
});
