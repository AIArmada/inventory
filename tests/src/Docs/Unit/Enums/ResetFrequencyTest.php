<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\ResetFrequency;
use Carbon\CarbonImmutable;

test('reset frequency labels', function (): void {
    expect(ResetFrequency::Never->label())->toBe('Never');
    expect(ResetFrequency::Daily->label())->toBe('Daily');
    expect(ResetFrequency::Monthly->label())->toBe('Monthly');
    expect(ResetFrequency::Yearly->label())->toBe('Yearly');
});

test('reset frequency descriptions', function (): void {
    expect(ResetFrequency::Never->description())->toBe('Sequence continues indefinitely');
    expect(ResetFrequency::Daily->description())->toBe('Resets at midnight each day');
    expect(ResetFrequency::Monthly->description())->toBe('Resets on the first of each month');
    expect(ResetFrequency::Yearly->description())->toBe('Resets on January 1st each year');
});

test('reset frequency current period key for never returns all', function (): void {
    expect(ResetFrequency::Never->getCurrentPeriodKey())->toBe('all');
});

test('reset frequency current period key for daily returns date', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 10:30:00');

    expect(ResetFrequency::Daily->getCurrentPeriodKey())->toBe('2024-06-15');

    CarbonImmutable::setTestNow();
});

test('reset frequency current period key for monthly returns year-month', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 10:30:00');

    expect(ResetFrequency::Monthly->getCurrentPeriodKey())->toBe('2024-06');

    CarbonImmutable::setTestNow();
});

test('reset frequency current period key for yearly returns year', function (): void {
    CarbonImmutable::setTestNow('2024-06-15 10:30:00');

    expect(ResetFrequency::Yearly->getCurrentPeriodKey())->toBe('2024');

    CarbonImmutable::setTestNow();
});

test('reset frequency format token', function (): void {
    expect(ResetFrequency::Never->getFormatToken())->toBeNull();
    expect(ResetFrequency::Daily->getFormatToken())->toBe('{YYMMDD}');
    expect(ResetFrequency::Monthly->getFormatToken())->toBe('{YYMM}');
    expect(ResetFrequency::Yearly->getFormatToken())->toBe('{YY}');
});

test('all reset frequencies can be instantiated from value', function (): void {
    expect(ResetFrequency::from('never'))->toBe(ResetFrequency::Never);
    expect(ResetFrequency::from('daily'))->toBe(ResetFrequency::Daily);
    expect(ResetFrequency::from('monthly'))->toBe(ResetFrequency::Monthly);
    expect(ResetFrequency::from('yearly'))->toBe(ResetFrequency::Yearly);
});
