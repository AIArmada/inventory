<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\RecoveryInsight;

describe('RecoveryInsight', function (): void {
    it('can be created with constructor', function (): void {
        $insight = new RecoveryInsight(
            type: 'timing',
            title: 'Optimal Send Time',
            description: 'Test description',
            recommendation: 'Send emails at 10am',
            confidence: 0.85,
            potential_impact_cents: 10000,
            data: ['optimal_delay_minutes' => 30],
        );

        expect($insight->type)->toBe('timing');
        expect($insight->title)->toBe('Optimal Send Time');
        expect($insight->description)->toBe('Test description');
        expect($insight->recommendation)->toBe('Send emails at 10am');
        expect($insight->confidence)->toBe(0.85);
        expect($insight->potential_impact_cents)->toBe(10000);
        expect($insight->data)->toBe(['optimal_delay_minutes' => 30]);
    });

    it('creates timing insight via factory', function (): void {
        $insight = RecoveryInsight::timing(
            recommendation: 'Send recovery emails 30 minutes after abandonment',
            optimalDelayMinutes: 30,
            expectedLift: 0.15,
            confidence: 0.8,
        );

        expect($insight->type)->toBe('timing');
        expect($insight->title)->toBe('Optimal Send Time');
        expect($insight->recommendation)->toContain('30 minutes');
        expect($insight->confidence)->toBe(0.8);
        expect($insight->data['optimal_delay_minutes'])->toBe(30);
        expect($insight->data['expected_lift'])->toBe(0.15);
        expect($insight->potential_impact_cents)->toBe(1500); // 0.15 * 10000
    });

    it('creates strategy insight via factory', function (): void {
        $insight = RecoveryInsight::strategy(
            recommendation: 'Use email + SMS combo',
            suggestedStrategy: 'multi_channel',
            expectedConversionRate: 0.25,
            confidence: 0.75,
        );

        expect($insight->type)->toBe('strategy');
        expect($insight->title)->toBe('Strategy Optimization');
        expect($insight->recommendation)->toContain('email + SMS');
        expect($insight->confidence)->toBe(0.75);
        expect($insight->data['suggested_strategy'])->toBe('multi_channel');
        expect($insight->data['expected_conversion_rate'])->toBe(0.25);
    });

    it('creates discount insight via factory', function (): void {
        $insight = RecoveryInsight::discount(
            recommendation: 'Offer 15% discount',
            suggestedDiscountPercent: 15,
            expectedLift: 0.2,
            confidence: 0.7,
        );

        expect($insight->type)->toBe('discount');
        expect($insight->title)->toBe('Discount Optimization');
        expect($insight->recommendation)->toContain('15%');
        expect($insight->confidence)->toBe(0.7);
        expect($insight->data['suggested_discount_percent'])->toBe(15);
        expect($insight->data['expected_lift'])->toBe(0.2);
    });

    it('creates targeting insight via factory', function (): void {
        $insight = RecoveryInsight::targeting(
            recommendation: 'Focus on high-value customers',
            segmentToFocus: 'high_value',
            segmentConversionRate: 0.35,
            confidence: 0.85,
        );

        expect($insight->type)->toBe('targeting');
        expect($insight->title)->toBe('Targeting Improvement');
        expect($insight->recommendation)->toContain('high-value');
        expect($insight->confidence)->toBe(0.85);
        expect($insight->data['segment_to_focus'])->toBe('high_value');
        expect($insight->data['segment_conversion_rate'])->toBe(0.35);
    });

    it('creates template insight via factory', function (): void {
        $insight = RecoveryInsight::template(
            recommendation: 'Add product images',
            suggestedChange: 'images',
            expectedLift: 0.1,
            confidence: 0.65,
        );

        expect($insight->type)->toBe('template');
        expect($insight->title)->toBe('Template Optimization');
        expect($insight->recommendation)->toContain('product images');
        expect($insight->confidence)->toBe(0.65);
        expect($insight->data['suggested_change'])->toBe('images');
        expect($insight->data['expected_lift'])->toBe(0.1);
    });

    it('uses default confidence values when not specified', function (): void {
        $timingInsight = RecoveryInsight::timing('Test', 30, 0.1);
        expect($timingInsight->confidence)->toBe(0.8);

        $strategyInsight = RecoveryInsight::strategy('Test', 'email', 0.2);
        expect($strategyInsight->confidence)->toBe(0.75);

        $discountInsight = RecoveryInsight::discount('Test', 10, 0.15);
        expect($discountInsight->confidence)->toBe(0.7);

        $targetingInsight = RecoveryInsight::targeting('Test', 'vip', 0.3);
        expect($targetingInsight->confidence)->toBe(0.85);

        $templateInsight = RecoveryInsight::template('Test', 'layout', 0.1);
        expect($templateInsight->confidence)->toBe(0.65);
    });
});
