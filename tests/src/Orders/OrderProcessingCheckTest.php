<?php

declare(strict_types=1);

use AIArmada\Orders\Health\OrderProcessingCheck;

describe('OrderProcessingCheck Health Check', function (): void {
    describe('Health Check Configuration', function (): void {
        it('can be instantiated', function (): void {
            $check = new OrderProcessingCheck();
            expect($check)->toBeInstanceOf(OrderProcessingCheck::class);
        });

        it('can configure max pending hours', function (): void {
            $check = new OrderProcessingCheck();
            $result = $check->maxPendingHours(48);

            expect($result)->toBe($check);
            expect(method_exists($check, 'maxPendingHours'))->toBeTrue();
        });

        it('can configure max processing hours', function (): void {
            $check = new OrderProcessingCheck();
            $result = $check->maxProcessingHours(72);

            expect($result)->toBe($check);
            expect(method_exists($check, 'maxProcessingHours'))->toBeTrue();
        });

        it('can configure both max ages', function (): void {
            $check = new OrderProcessingCheck();
            $result = $check->maxAge(36, 60);

            expect($result)->toBe($check);
            expect(method_exists($check, 'maxAge'))->toBeTrue();
        });
    });

    describe('Health Check Execution', function (): void {
        it('can run health check', function (): void {
            $check = new OrderProcessingCheck();
            $result = $check->run();

            expect($result)->toBeInstanceOf(Spatie\Health\Checks\Result::class);
        });
    });
});
