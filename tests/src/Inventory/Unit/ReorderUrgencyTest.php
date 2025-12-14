<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\ReorderUrgency;

test('ReorderUrgency enum has correct cases', function (): void {
    expect(ReorderUrgency::cases())->toHaveCount(4);
    expect(ReorderUrgency::Low->value)->toBe('low');
    expect(ReorderUrgency::Normal->value)->toBe('normal');
    expect(ReorderUrgency::High->value)->toBe('high');
    expect(ReorderUrgency::Critical->value)->toBe('critical');
});

test('ReorderUrgency fromDaysUntilStockout calculates correctly', function (): void {
    expect(ReorderUrgency::fromDaysUntilStockout(null))->toBe(ReorderUrgency::Low);
    expect(ReorderUrgency::fromDaysUntilStockout(10, 5))->toBe(ReorderUrgency::Normal); // 10-5=5 <=7 => Normal
    expect(ReorderUrgency::fromDaysUntilStockout(5, 5))->toBe(ReorderUrgency::Critical); // 5-5=0 <=0 => Critical
    expect(ReorderUrgency::fromDaysUntilStockout(3, 5))->toBe(ReorderUrgency::Critical); // 3-5=-2 <=0 => Critical
    expect(ReorderUrgency::fromDaysUntilStockout(0, 5))->toBe(ReorderUrgency::Critical);
    expect(ReorderUrgency::fromDaysUntilStockout(-1, 5))->toBe(ReorderUrgency::Critical);
});

test('ReorderUrgency label returns correct labels', function (): void {
    expect(ReorderUrgency::Low->label())->toBe('Low');
    expect(ReorderUrgency::Normal->label())->toBe('Normal');
    expect(ReorderUrgency::High->label())->toBe('High');
    expect(ReorderUrgency::Critical->label())->toBe('Critical');
});

test('ReorderUrgency color returns correct colors', function (): void {
    expect(ReorderUrgency::Low->color())->toBe('gray');
    expect(ReorderUrgency::Normal->color())->toBe('info');
    expect(ReorderUrgency::High->color())->toBe('warning');
    expect(ReorderUrgency::Critical->color())->toBe('danger');
});

test('ReorderUrgency sortOrder returns correct order', function (): void {
    expect(ReorderUrgency::Critical->sortOrder())->toBe(1);
    expect(ReorderUrgency::High->sortOrder())->toBe(2);
    expect(ReorderUrgency::Normal->sortOrder())->toBe(3);
    expect(ReorderUrgency::Low->sortOrder())->toBe(4);
});
