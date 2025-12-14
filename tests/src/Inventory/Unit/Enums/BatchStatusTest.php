<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BatchStatus;

it('has correct batch status values', function (): void {
    expect(BatchStatus::Active->value)->toBe('active');
    expect(BatchStatus::Quarantined->value)->toBe('quarantined');
    expect(BatchStatus::Expired->value)->toBe('expired');
    expect(BatchStatus::Depleted->value)->toBe('depleted');
    expect(BatchStatus::Recalled->value)->toBe('recalled');
    expect(BatchStatus::OnHold->value)->toBe('on_hold');
});

it('can get all batch status values', function (): void {
    $cases = BatchStatus::cases();

    expect($cases)->toHaveCount(6);
});

it('can create batch status from value', function (): void {
    $status = BatchStatus::from('quarantined');

    expect($status)->toBe(BatchStatus::Quarantined);
});

it('throws for invalid batch status value', function (): void {
    BatchStatus::from('invalid_status');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $status = BatchStatus::tryFrom('expired');
    $invalid = BatchStatus::tryFrom('invalid');

    expect($status)->toBe(BatchStatus::Expired);
    expect($invalid)->toBeNull();
});

it('can get movable statuses', function (): void {
    $movable = BatchStatus::movableStatuses();

    expect($movable)->toContain(BatchStatus::Active);
});
