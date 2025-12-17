<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\Pipeline\ConditionPipelineResult;
use AIArmada\Cart\Conditions\Pipeline\ConditionPhaseResult;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;

describe('ConditionPipelineResult', function (): void {
    it('constructs with all parameters', function (): void {
        $phases = [
            ConditionPhase::ITEM_DISCOUNT->value => new ConditionPhaseResult(ConditionPhase::ITEM_DISCOUNT, 1000, 900, -100, 1),
            ConditionPhase::TAX->value => new ConditionPhaseResult(ConditionPhase::TAX, 900, 990, 90, 1),
        ];

        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 990,
            phases: $phases
        );

        expect($result->initialAmount)->toBe(1000);
        expect($result->finalAmount)->toBe(990);
    });

    it('returns phases array', function (): void {
        $phases = [
            ConditionPhase::ITEM_DISCOUNT->value => new ConditionPhaseResult(ConditionPhase::ITEM_DISCOUNT, 1000, 900, -100, 1),
        ];

        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 900,
            phases: $phases
        );

        expect($result->phases())->toHaveCount(1);
        expect($result->phases())->toHaveKey('item_discount');
    });

    it('gets phase result by enum', function (): void {
        $phases = [
            ConditionPhase::TAX->value => new ConditionPhaseResult(ConditionPhase::TAX, 1000, 1100, 100, 1),
        ];

        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 1100,
            phases: $phases
        );

        $taxResult = $result->getPhaseResult(ConditionPhase::TAX);

        expect($taxResult)->not->toBeNull();
        expect($taxResult->adjustment)->toBe(100);
    });

    it('gets phase result by string', function (): void {
        $phases = [
            'shipping' => new ConditionPhaseResult(ConditionPhase::SHIPPING, 0, 500, 500, 1),
        ];

        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 1500,
            phases: $phases
        );

        $shippingResult = $result->getPhaseResult('shipping');

        expect($shippingResult)->not->toBeNull();
        expect($shippingResult->adjustment)->toBe(500);
    });

    it('returns null for missing phase', function (): void {
        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 1000,
            phases: []
        );

        expect($result->getPhaseResult(ConditionPhase::PAYMENT))->toBeNull();
    });

    it('returns subtotal from cart_subtotal phase', function (): void {
        $phases = [
            ConditionPhase::CART_SUBTOTAL->value => new ConditionPhaseResult(ConditionPhase::CART_SUBTOTAL, 1000, 800, -200, 1),
        ];

        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 800,
            phases: $phases
        );

        expect($result->subtotal())->toBe(800);
    });

    it('returns initial amount when no cart_subtotal phase', function (): void {
        $result = new ConditionPipelineResult(
            initialAmount: 2000,
            finalAmount: 2000,
            phases: []
        );

        expect($result->subtotal())->toBe(2000);
    });

    it('returns total from grand_total phase', function (): void {
        $phases = [
            ConditionPhase::GRAND_TOTAL->value => new ConditionPhaseResult(ConditionPhase::GRAND_TOTAL, 1000, 1100, 100, 1),
        ];

        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 1100,
            phases: $phases
        );

        expect($result->total())->toBe(1100);
    });

    it('returns final amount when no grand_total phase', function (): void {
        $result = new ConditionPipelineResult(
            initialAmount: 3000,
            finalAmount: 3500,
            phases: []
        );

        expect($result->total())->toBe(3500);
    });

    it('converts to array correctly', function (): void {
        $phases = [
            ConditionPhase::TAX->value => new ConditionPhaseResult(ConditionPhase::TAX, 1000, 1100, 100, 1),
        ];

        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 1100,
            phases: $phases
        );

        $array = $result->toArray();

        expect($array)->toHaveKey('initial_amount');
        expect($array)->toHaveKey('final_amount');
        expect($array)->toHaveKey('phases');
        expect($array['initial_amount'])->toBe(1000);
        expect($array['final_amount'])->toBe(1100);
    });

    it('handles empty phase results', function (): void {
        $result = new ConditionPipelineResult(
            initialAmount: 1000,
            finalAmount: 1000,
            phases: []
        );

        expect($result->phases())->toBeEmpty();
    });
});
