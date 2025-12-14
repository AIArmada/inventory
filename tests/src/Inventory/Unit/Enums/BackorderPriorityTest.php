<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BackorderPriority;

it('has correct backorder priority values', function (): void {
    expect(BackorderPriority::Low->value)->toBe('low');
    expect(BackorderPriority::Normal->value)->toBe('normal');
    expect(BackorderPriority::High->value)->toBe('high');
    expect(BackorderPriority::Urgent->value)->toBe('urgent');
});

it('can get all backorder priority values', function (): void {
    $cases = BackorderPriority::cases();

    expect($cases)->toHaveCount(4);
});

it('can create backorder priority from value', function (): void {
    $priority = BackorderPriority::from('urgent');

    expect($priority)->toBe(BackorderPriority::Urgent);
});

it('throws for invalid backorder priority value', function (): void {
    BackorderPriority::from('invalid_priority');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $priority = BackorderPriority::tryFrom('high');
    $invalid = BackorderPriority::tryFrom('invalid');

    expect($priority)->toBe(BackorderPriority::High);
    expect($invalid)->toBeNull();
});
