<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BatchStatus;

test('BatchStatus enum has correct cases', function () {
    expect(BatchStatus::cases())->toHaveCount(6);
    expect(BatchStatus::Active->value)->toBe('active');
    expect(BatchStatus::Quarantined->value)->toBe('quarantined');
    expect(BatchStatus::Expired->value)->toBe('expired');
    expect(BatchStatus::Depleted->value)->toBe('depleted');
    expect(BatchStatus::Recalled->value)->toBe('recalled');
    expect(BatchStatus::OnHold->value)->toBe('on_hold');
});

test('BatchStatus movableStatuses returns correct statuses', function () {
    $movable = BatchStatus::movableStatuses();
    expect($movable)->toHaveCount(2);
    expect($movable)->toContain(BatchStatus::Active);
    expect($movable)->toContain(BatchStatus::OnHold);
});

test('BatchStatus options returns correct array', function () {
    $options = BatchStatus::options();
    expect($options)->toBeArray();
    expect($options)->toHaveKey('active');
    expect($options['active'])->toBe('Active');
});

test('BatchStatus label returns correct labels', function () {
    expect(BatchStatus::Active->label())->toBe('Active');
    expect(BatchStatus::Quarantined->label())->toBe('Quarantined');
    expect(BatchStatus::Expired->label())->toBe('Expired');
    expect(BatchStatus::Depleted->label())->toBe('Depleted');
    expect(BatchStatus::Recalled->label())->toBe('Recalled');
    expect(BatchStatus::OnHold->label())->toBe('On Hold');
});

test('BatchStatus color returns correct colors', function () {
    expect(BatchStatus::Active->color())->toBe('success');
    expect(BatchStatus::Quarantined->color())->toBe('warning');
    expect(BatchStatus::Expired->color())->toBe('danger');
    expect(BatchStatus::Depleted->color())->toBe('gray');
    expect(BatchStatus::Recalled->color())->toBe('danger');
    expect(BatchStatus::OnHold->color())->toBe('warning');
});

test('BatchStatus isAllocatable works correctly', function () {
    expect(BatchStatus::Active->isAllocatable())->toBeTrue();
    expect(BatchStatus::Quarantined->isAllocatable())->toBeFalse();
    expect(BatchStatus::Expired->isAllocatable())->toBeFalse();
    expect(BatchStatus::Depleted->isAllocatable())->toBeFalse();
    expect(BatchStatus::Recalled->isAllocatable())->toBeFalse();
    expect(BatchStatus::OnHold->isAllocatable())->toBeFalse();
});

test('BatchStatus requiresAttention works correctly', function () {
    expect(BatchStatus::Active->requiresAttention())->toBeFalse();
    expect(BatchStatus::Quarantined->requiresAttention())->toBeTrue();
    expect(BatchStatus::Expired->requiresAttention())->toBeTrue();
    expect(BatchStatus::Depleted->requiresAttention())->toBeFalse();
    expect(BatchStatus::Recalled->requiresAttention())->toBeTrue();
    expect(BatchStatus::OnHold->requiresAttention())->toBeFalse();
});