<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\DemandPeriodType;

test('DemandPeriodType enum has correct cases', function (): void {
    expect(DemandPeriodType::cases())->toHaveCount(3);
    expect(DemandPeriodType::Daily->value)->toBe('daily');
    expect(DemandPeriodType::Weekly->value)->toBe('weekly');
    expect(DemandPeriodType::Monthly->value)->toBe('monthly');
});

test('DemandPeriodType label returns correct labels', function (): void {
    expect(DemandPeriodType::Daily->label())->toBe('Daily');
    expect(DemandPeriodType::Weekly->label())->toBe('Weekly');
    expect(DemandPeriodType::Monthly->label())->toBe('Monthly');
});

test('DemandPeriodType daysPerPeriod returns correct days', function (): void {
    expect(DemandPeriodType::Daily->daysPerPeriod())->toBe(1);
    expect(DemandPeriodType::Weekly->daysPerPeriod())->toBe(7);
    expect(DemandPeriodType::Monthly->daysPerPeriod())->toBe(30);
});
