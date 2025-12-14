<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\BackorderStatus;

test('BackorderStatus enum has correct cases', function (): void {
    expect(BackorderStatus::cases())->toHaveCount(5);
    expect(BackorderStatus::Pending->value)->toBe('pending');
    expect(BackorderStatus::PartiallyFulfilled->value)->toBe('partially_fulfilled');
    expect(BackorderStatus::Fulfilled->value)->toBe('fulfilled');
    expect(BackorderStatus::Cancelled->value)->toBe('cancelled');
    expect(BackorderStatus::Expired->value)->toBe('expired');
});

test('BackorderStatus label returns correct labels', function (): void {
    expect(BackorderStatus::Pending->label())->toBe('Pending');
    expect(BackorderStatus::PartiallyFulfilled->label())->toBe('Partially Fulfilled');
    expect(BackorderStatus::Fulfilled->label())->toBe('Fulfilled');
    expect(BackorderStatus::Cancelled->label())->toBe('Cancelled');
    expect(BackorderStatus::Expired->label())->toBe('Expired');
});

test('BackorderStatus color returns correct colors', function (): void {
    expect(BackorderStatus::Pending->color())->toBe('warning');
    expect(BackorderStatus::PartiallyFulfilled->color())->toBe('info');
    expect(BackorderStatus::Fulfilled->color())->toBe('success');
    expect(BackorderStatus::Cancelled->color())->toBe('danger');
    expect(BackorderStatus::Expired->color())->toBe('gray');
});

test('BackorderStatus isOpen works correctly', function (): void {
    expect(BackorderStatus::Pending->isOpen())->toBeTrue();
    expect(BackorderStatus::PartiallyFulfilled->isOpen())->toBeTrue();
    expect(BackorderStatus::Fulfilled->isOpen())->toBeFalse();
    expect(BackorderStatus::Cancelled->isOpen())->toBeFalse();
    expect(BackorderStatus::Expired->isOpen())->toBeFalse();
});

test('BackorderStatus isClosed works correctly', function (): void {
    expect(BackorderStatus::Pending->isClosed())->toBeFalse();
    expect(BackorderStatus::PartiallyFulfilled->isClosed())->toBeFalse();
    expect(BackorderStatus::Fulfilled->isClosed())->toBeTrue();
    expect(BackorderStatus::Cancelled->isClosed())->toBeTrue();
    expect(BackorderStatus::Expired->isClosed())->toBeTrue();
});

test('BackorderStatus canFulfill works correctly', function (): void {
    expect(BackorderStatus::Pending->canFulfill())->toBeTrue();
    expect(BackorderStatus::PartiallyFulfilled->canFulfill())->toBeTrue();
    expect(BackorderStatus::Fulfilled->canFulfill())->toBeFalse();
    expect(BackorderStatus::Cancelled->canFulfill())->toBeFalse();
    expect(BackorderStatus::Expired->canFulfill())->toBeFalse();
});

test('BackorderStatus canCancel works correctly', function (): void {
    expect(BackorderStatus::Pending->canCancel())->toBeTrue();
    expect(BackorderStatus::PartiallyFulfilled->canCancel())->toBeTrue();
    expect(BackorderStatus::Fulfilled->canCancel())->toBeFalse();
    expect(BackorderStatus::Cancelled->canCancel())->toBeFalse();
    expect(BackorderStatus::Expired->canCancel())->toBeFalse();
});
