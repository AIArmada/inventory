<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Pipeline\ConditionPhaseResult;

describe('ConditionPhaseResult', function (): void {
    it('constructs with all parameters', function (): void {
        $result = new ConditionPhaseResult(
            phase: ConditionPhase::TAX,
            baseAmount: 10000,
            finalAmount: 10500,
            adjustment: 500,
            appliedConditions: 2
        );

        expect($result->phase)->toBe(ConditionPhase::TAX);
        expect($result->baseAmount)->toBe(10000);
        expect($result->finalAmount)->toBe(10500);
        expect($result->adjustment)->toBe(500);
        expect($result->appliedConditions)->toBe(2);
    });

    it('converts to array correctly', function (): void {
        $result = new ConditionPhaseResult(
            phase: ConditionPhase::ITEM_DISCOUNT,
            baseAmount: 20000,
            finalAmount: 18000,
            adjustment: -2000,
            appliedConditions: 1
        );

        $array = $result->toArray();

        expect($array)->toBe([
            'phase' => 'item_discount',
            'base_amount' => 20000,
            'final_amount' => 18000,
            'adjustment' => -2000,
            'applied_conditions' => 1,
        ]);
    });

    it('works with payment phase', function (): void {
        $result = new ConditionPhaseResult(
            phase: ConditionPhase::PAYMENT,
            baseAmount: 5000,
            finalAmount: 5500,
            adjustment: 500,
            appliedConditions: 3
        );

        expect($result->toArray()['phase'])->toBe('payment');
    });

    it('works with shipping phase', function (): void {
        $result = new ConditionPhaseResult(
            phase: ConditionPhase::SHIPPING,
            baseAmount: 0,
            finalAmount: 1000,
            adjustment: 1000,
            appliedConditions: 1
        );

        expect($result->toArray()['phase'])->toBe('shipping');
    });

    it('handles zero appliedConditions', function (): void {
        $result = new ConditionPhaseResult(
            phase: ConditionPhase::TAX,
            baseAmount: 1000,
            finalAmount: 1000,
            adjustment: 0,
            appliedConditions: 0
        );

        expect($result->appliedConditions)->toBe(0);
        expect($result->adjustment)->toBe(0);
    });

    it('handles negative adjustment', function (): void {
        $result = new ConditionPhaseResult(
            phase: ConditionPhase::ITEM_DISCOUNT,
            baseAmount: 10000,
            finalAmount: 5000,
            adjustment: -5000,
            appliedConditions: 1
        );

        expect($result->adjustment)->toBe(-5000);
        expect($result->toArray()['adjustment'])->toBe(-5000);
    });

    it('works with grand total phase', function (): void {
        $result = new ConditionPhaseResult(
            phase: ConditionPhase::GRAND_TOTAL,
            baseAmount: 50000,
            finalAmount: 50000,
            adjustment: 0,
            appliedConditions: 0
        );

        expect($result->toArray()['phase'])->toBe('grand_total');
    });
});
