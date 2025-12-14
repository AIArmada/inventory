<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\AlertStatus;

test('AlertStatus enum has correct cases', function () {
    expect(AlertStatus::cases())->toHaveCount(7);
    expect(AlertStatus::None->value)->toBe('none');
    expect(AlertStatus::LowStock->value)->toBe('low_stock');
    expect(AlertStatus::SafetyBreached->value)->toBe('safety_breached');
    expect(AlertStatus::OutOfStock->value)->toBe('out_of_stock');
    expect(AlertStatus::OverStock->value)->toBe('over_stock');
    expect(AlertStatus::Expiring->value)->toBe('expiring');
    expect(AlertStatus::Expired->value)->toBe('expired');
});

test('AlertStatus criticalStatuses returns correct statuses', function () {
    $critical = AlertStatus::criticalStatuses();
    expect($critical)->toHaveCount(3);
    expect($critical)->toContain(AlertStatus::OutOfStock);
    expect($critical)->toContain(AlertStatus::SafetyBreached);
    expect($critical)->toContain(AlertStatus::Expired);
});

test('AlertStatus warningStatuses returns correct statuses', function () {
    $warning = AlertStatus::warningStatuses();
    expect($warning)->toHaveCount(2);
    expect($warning)->toContain(AlertStatus::LowStock);
    expect($warning)->toContain(AlertStatus::Expiring);
});

test('AlertStatus options returns correct array', function () {
    $options = AlertStatus::options();
    expect($options)->toBeArray();
    expect($options)->toHaveKey('none');
    expect($options)->toHaveKey('low_stock');
    expect($options['none'])->toBe('Normal');
    expect($options['low_stock'])->toBe('Low Stock');
});

test('AlertStatus label returns correct labels', function () {
    expect(AlertStatus::None->label())->toBe('Normal');
    expect(AlertStatus::LowStock->label())->toBe('Low Stock');
    expect(AlertStatus::SafetyBreached->label())->toBe('Safety Stock Breached');
    expect(AlertStatus::OutOfStock->label())->toBe('Out of Stock');
    expect(AlertStatus::OverStock->label())->toBe('Over Stocked');
    expect(AlertStatus::Expiring->label())->toBe('Expiring Soon');
    expect(AlertStatus::Expired->label())->toBe('Expired');
});

test('AlertStatus color returns correct colors', function () {
    expect(AlertStatus::None->color())->toBe('success');
    expect(AlertStatus::LowStock->color())->toBe('warning');
    expect(AlertStatus::SafetyBreached->color())->toBe('danger');
    expect(AlertStatus::OutOfStock->color())->toBe('danger');
    expect(AlertStatus::OverStock->color())->toBe('info');
    expect(AlertStatus::Expiring->color())->toBe('warning');
    expect(AlertStatus::Expired->color())->toBe('danger');
});

test('AlertStatus severity returns correct levels', function () {
    expect(AlertStatus::None->severity())->toBe(1);
    expect(AlertStatus::OverStock->severity())->toBe(2);
    expect(AlertStatus::LowStock->severity())->toBe(3);
    expect(AlertStatus::Expiring->severity())->toBe(3);
    expect(AlertStatus::SafetyBreached->severity())->toBe(4);
    expect(AlertStatus::Expired->severity())->toBe(5);
    expect(AlertStatus::OutOfStock->severity())->toBe(5);
});

test('AlertStatus requiresImmediateAttention works correctly', function () {
    expect(AlertStatus::None->requiresImmediateAttention())->toBeFalse();
    expect(AlertStatus::LowStock->requiresImmediateAttention())->toBeFalse();
    expect(AlertStatus::SafetyBreached->requiresImmediateAttention())->toBeTrue();
    expect(AlertStatus::OutOfStock->requiresImmediateAttention())->toBeTrue();
    expect(AlertStatus::Expired->requiresImmediateAttention())->toBeTrue();
});