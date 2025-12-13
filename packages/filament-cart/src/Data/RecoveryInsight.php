<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Data;

use Spatie\LaravelData\Data;

/**
 * Recovery insight DTO for AI-driven recommendations.
 */
class RecoveryInsight extends Data
{
    public function __construct(
        public string $type, // timing, strategy, targeting, template, discount
        public string $title,
        public string $description,
        public string $recommendation,
        public float $confidence,
        public int $potential_impact_cents,
        /** @var array<string, mixed> */
        public array $data,
    ) {}

    /**
     * Create timing insight.
     */
    public static function timing(
        string $recommendation,
        int $optimalDelayMinutes,
        float $expectedLift,
        float $confidence = 0.8,
    ): self {
        return new self(
            type: 'timing',
            title: 'Optimal Send Time',
            description: 'Analysis suggests adjusting recovery timing for better results.',
            recommendation: $recommendation,
            confidence: $confidence,
            potential_impact_cents: (int) ($expectedLift * 10000),
            data: [
                'optimal_delay_minutes' => $optimalDelayMinutes,
                'expected_lift' => $expectedLift,
            ],
        );
    }

    /**
     * Create strategy insight.
     */
    public static function strategy(
        string $recommendation,
        string $suggestedStrategy,
        float $expectedConversionRate,
        float $confidence = 0.75,
    ): self {
        return new self(
            type: 'strategy',
            title: 'Strategy Optimization',
            description: 'A different strategy may improve recovery rates.',
            recommendation: $recommendation,
            confidence: $confidence,
            potential_impact_cents: (int) ($expectedConversionRate * 5000),
            data: [
                'suggested_strategy' => $suggestedStrategy,
                'expected_conversion_rate' => $expectedConversionRate,
            ],
        );
    }

    /**
     * Create discount insight.
     */
    public static function discount(
        string $recommendation,
        int $suggestedDiscountPercent,
        float $expectedLift,
        float $confidence = 0.7,
    ): self {
        return new self(
            type: 'discount',
            title: 'Discount Optimization',
            description: 'Adjusting discount offer could improve conversions.',
            recommendation: $recommendation,
            confidence: $confidence,
            potential_impact_cents: (int) ($expectedLift * 8000),
            data: [
                'suggested_discount_percent' => $suggestedDiscountPercent,
                'expected_lift' => $expectedLift,
            ],
        );
    }

    /**
     * Create targeting insight.
     */
    public static function targeting(
        string $recommendation,
        string $segmentToFocus,
        float $segmentConversionRate,
        float $confidence = 0.85,
    ): self {
        return new self(
            type: 'targeting',
            title: 'Targeting Improvement',
            description: 'Focusing on specific segments could boost performance.',
            recommendation: $recommendation,
            confidence: $confidence,
            potential_impact_cents: (int) ($segmentConversionRate * 12000),
            data: [
                'segment_to_focus' => $segmentToFocus,
                'segment_conversion_rate' => $segmentConversionRate,
            ],
        );
    }

    /**
     * Create template insight.
     */
    public static function template(
        string $recommendation,
        string $suggestedChange,
        float $expectedLift,
        float $confidence = 0.65,
    ): self {
        return new self(
            type: 'template',
            title: 'Template Optimization',
            description: 'Template changes could improve engagement.',
            recommendation: $recommendation,
            confidence: $confidence,
            potential_impact_cents: (int) ($expectedLift * 6000),
            data: [
                'suggested_change' => $suggestedChange,
                'expected_lift' => $expectedLift,
            ],
        );
    }
}
