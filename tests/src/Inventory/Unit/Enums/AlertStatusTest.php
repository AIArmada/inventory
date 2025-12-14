<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\AlertStatus;

it('has correct alert status values', function (): void {
    expect(AlertStatus::None->value)->toBe('none');
    expect(AlertStatus::LowStock->value)->toBe('low_stock');
    expect(AlertStatus::OutOfStock->value)->toBe('out_of_stock');
    expect(AlertStatus::SafetyBreached->value)->toBe('safety_breached');
    expect(AlertStatus::OverStock->value)->toBe('over_stock');
    expect(AlertStatus::Expiring->value)->toBe('expiring');
    expect(AlertStatus::Expired->value)->toBe('expired');
});

it('can get all alert status values', function (): void {
    $cases = AlertStatus::cases();

    expect($cases)->toHaveCount(7);
});

it('can create alert status from value', function (): void {
    $status = AlertStatus::from('low_stock');

    expect($status)->toBe(AlertStatus::LowStock);
});

it('throws for invalid alert status value', function (): void {
    AlertStatus::from('invalid_status');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $status = AlertStatus::tryFrom('low_stock');
    $invalid = AlertStatus::tryFrom('invalid');

    expect($status)->toBe(AlertStatus::LowStock);
    expect($invalid)->toBeNull();
});

it('can get critical statuses', function (): void {
    $critical = AlertStatus::criticalStatuses();

    expect($critical)->toContain(AlertStatus::OutOfStock);
    expect($critical)->toContain(AlertStatus::SafetyBreached);
    expect($critical)->toContain(AlertStatus::Expired);
});
