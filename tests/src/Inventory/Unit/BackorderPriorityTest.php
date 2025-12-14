<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BackorderPriority;

test('BackorderPriority enum has correct cases', function (): void {
    expect(BackorderPriority::cases())->toHaveCount(4);
    expect(BackorderPriority::Low->value)->toBe('low');
    expect(BackorderPriority::Normal->value)->toBe('normal');
    expect(BackorderPriority::High->value)->toBe('high');
    expect(BackorderPriority::Urgent->value)->toBe('urgent');
});

test('BackorderPriority label returns correct labels', function (): void {
    expect(BackorderPriority::Low->label())->toBe('Low');
    expect(BackorderPriority::Normal->label())->toBe('Normal');
    expect(BackorderPriority::High->label())->toBe('High');
    expect(BackorderPriority::Urgent->label())->toBe('Urgent');
});

test('BackorderPriority color returns correct colors', function (): void {
    expect(BackorderPriority::Low->color())->toBe('gray');
    expect(BackorderPriority::Normal->color())->toBe('info');
    expect(BackorderPriority::High->color())->toBe('warning');
    expect(BackorderPriority::Urgent->color())->toBe('danger');
});

test('BackorderPriority sortOrder returns correct order', function (): void {
    expect(BackorderPriority::Urgent->sortOrder())->toBe(1);
    expect(BackorderPriority::High->sortOrder())->toBe(2);
    expect(BackorderPriority::Normal->sortOrder())->toBe(3);
    expect(BackorderPriority::Low->sortOrder())->toBe(4);
});

test('BackorderPriority isElevated works correctly', function (): void {
    expect(BackorderPriority::Low->isElevated())->toBeFalse();
    expect(BackorderPriority::Normal->isElevated())->toBeFalse();
    expect(BackorderPriority::High->isElevated())->toBeTrue();
    expect(BackorderPriority::Urgent->isElevated())->toBeTrue();
});
