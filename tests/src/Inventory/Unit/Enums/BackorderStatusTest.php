<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BackorderStatus;

it('has correct backorder status values', function (): void {
    expect(BackorderStatus::Pending->value)->toBe('pending');
    expect(BackorderStatus::PartiallyFulfilled->value)->toBe('partially_fulfilled');
    expect(BackorderStatus::Fulfilled->value)->toBe('fulfilled');
    expect(BackorderStatus::Cancelled->value)->toBe('cancelled');
    expect(BackorderStatus::Expired->value)->toBe('expired');
});

it('can get all backorder status values', function (): void {
    $cases = BackorderStatus::cases();

    expect($cases)->toHaveCount(5);
});

it('can create backorder status from value', function (): void {
    $status = BackorderStatus::from('fulfilled');

    expect($status)->toBe(BackorderStatus::Fulfilled);
});

it('throws for invalid backorder status value', function (): void {
    BackorderStatus::from('invalid_status');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $status = BackorderStatus::tryFrom('pending');
    $invalid = BackorderStatus::tryFrom('invalid');

    expect($status)->toBe(BackorderStatus::Pending);
    expect($invalid)->toBeNull();
});

it('has labels for all statuses', function (): void {
    expect(BackorderStatus::Pending->label())->toBe('Pending');
    expect(BackorderStatus::PartiallyFulfilled->label())->toBe('Partially Fulfilled');
    expect(BackorderStatus::Fulfilled->label())->toBe('Fulfilled');
    expect(BackorderStatus::Cancelled->label())->toBe('Cancelled');
    expect(BackorderStatus::Expired->label())->toBe('Expired');
});
