<?php

declare(strict_types=1);

use AIArmada\FilamentCart\Data\AbandonmentAnalysis;

describe('AbandonmentAnalysis', function (): void {
    it('can be created with constructor', function (): void {
        $analysis = new AbandonmentAnalysis(
            by_hour: [10 => 5, 14 => 10],
            by_day_of_week: [1 => 15, 5 => 20],
            by_cart_value_range: ['$0-50' => 5, '$50-100' => 10],
            by_items_count: ['1-3 items' => 8, '4-6 items' => 12],
            common_exit_points: ['checkout' => 25, 'cart' => 15],
            total_abandonments: 50,
            peak_abandonment_hour: '14:00',
            peak_abandonment_day: 'Friday',
        );

        expect($analysis->by_hour)->toBe([10 => 5, 14 => 10]);
        expect($analysis->by_day_of_week)->toBe([1 => 15, 5 => 20]);
        expect($analysis->by_cart_value_range)->toBe(['$0-50' => 5, '$50-100' => 10]);
        expect($analysis->by_items_count)->toBe(['1-3 items' => 8, '4-6 items' => 12]);
        expect($analysis->common_exit_points)->toBe(['checkout' => 25, 'cart' => 15]);
        expect($analysis->total_abandonments)->toBe(50);
        expect($analysis->peak_abandonment_hour)->toBe('14:00');
        expect($analysis->peak_abandonment_day)->toBe('Friday');
    });

    it('can be created with fromData factory method', function (): void {
        $analysis = AbandonmentAnalysis::fromData(
            byHour: [10 => 5, 14 => 10],
            byDayOfWeek: [1 => 15, 5 => 20],
            byCartValueRange: ['$0-50' => 5, '$50-100' => 10],
            byItemsCount: ['1-3 items' => 8],
            commonExitPoints: ['checkout' => 25],
            totalAbandonments: 50,
        );

        expect($analysis->total_abandonments)->toBe(50);
        expect($analysis->peak_abandonment_hour)->toBe('14:00'); // Hour with max count
        expect($analysis->peak_abandonment_day)->toBe('Friday'); // Day 5 = Friday
    });

    it('returns null peak values when arrays are empty', function (): void {
        $analysis = AbandonmentAnalysis::fromData(
            byHour: [],
            byDayOfWeek: [],
            byCartValueRange: [],
            byItemsCount: [],
            commonExitPoints: [],
            totalAbandonments: 0,
        );

        expect($analysis->peak_abandonment_hour)->toBeNull();
        expect($analysis->peak_abandonment_day)->toBeNull();
    });

    it('correctly identifies peak abandonment day', function (): void {
        $analysis = AbandonmentAnalysis::fromData(
            byHour: [8 => 10],
            byDayOfWeek: [0 => 5, 1 => 10, 2 => 3], // Sunday, Monday, Tuesday
            byCartValueRange: [],
            byItemsCount: [],
            commonExitPoints: [],
            totalAbandonments: 18,
        );

        expect($analysis->peak_abandonment_day)->toBe('Monday'); // Day 1 has max count
    });

    it('correctly formats peak hour with leading zero', function (): void {
        $analysis = AbandonmentAnalysis::fromData(
            byHour: [8 => 20, 14 => 10],
            byDayOfWeek: [],
            byCartValueRange: [],
            byItemsCount: [],
            commonExitPoints: [],
            totalAbandonments: 30,
        );

        expect($analysis->peak_abandonment_hour)->toBe('08:00'); // Hour 8 formatted as 08:00
    });
});
