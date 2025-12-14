<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\SerialStatus;

test('SerialStatus enum has correct cases', function () {
    expect(SerialStatus::cases())->toHaveCount(9);
    expect(SerialStatus::Available->value)->toBe('available');
    expect(SerialStatus::Reserved->value)->toBe('reserved');
    expect(SerialStatus::Sold->value)->toBe('sold');
    expect(SerialStatus::Disposed->value)->toBe('disposed');
});

test('SerialStatus options returns correct array', function () {
    $options = SerialStatus::options();
    expect($options)->toBeArray();
    expect($options)->toHaveKey('available');
    expect($options['available'])->toBe('Available');
});

test('SerialStatus label returns correct labels', function () {
    expect(SerialStatus::Available->label())->toBe('Available');
    expect(SerialStatus::Reserved->label())->toBe('Reserved');
    expect(SerialStatus::Sold->label())->toBe('Sold');
    expect(SerialStatus::Disposed->label())->toBe('Disposed');
    expect(SerialStatus::InRepair->label())->toBe('In Repair');
});

test('SerialStatus color returns correct colors', function () {
    expect(SerialStatus::Available->color())->toBe('success');
    expect(SerialStatus::Reserved->color())->toBe('warning');
    expect(SerialStatus::Sold->color())->toBe('info');
    expect(SerialStatus::Disposed->color())->toBe('danger');
});

test('SerialStatus isAllocatable works correctly', function () {
    expect(SerialStatus::Available->isAllocatable())->toBeTrue();
    expect(SerialStatus::Reserved->isAllocatable())->toBeFalse();
    expect(SerialStatus::Sold->isAllocatable())->toBeFalse();
});

test('SerialStatus isInStock works correctly', function () {
    expect(SerialStatus::Available->isInStock())->toBeTrue();
    expect(SerialStatus::Reserved->isInStock())->toBeTrue();
    expect(SerialStatus::Sold->isInStock())->toBeFalse();
    expect(SerialStatus::Disposed->isInStock())->toBeFalse();
});

test('SerialStatus allowedTransitions returns correct transitions', function () {
    expect(SerialStatus::Available->allowedTransitions())->toContain(SerialStatus::Reserved);
    expect(SerialStatus::Available->allowedTransitions())->toContain(SerialStatus::Sold);
    expect(SerialStatus::Sold->allowedTransitions())->toContain(SerialStatus::Shipped);
    expect(SerialStatus::Sold->allowedTransitions())->toContain(SerialStatus::Returned);
    expect(SerialStatus::Disposed->allowedTransitions())->toBeEmpty();
    expect(SerialStatus::Lost->allowedTransitions())->toContain(SerialStatus::Available);
});

test('SerialStatus canTransitionTo works correctly', function () {
    expect(SerialStatus::Available->canTransitionTo(SerialStatus::Reserved))->toBeTrue();
    expect(SerialStatus::Available->canTransitionTo(SerialStatus::Disposed))->toBeTrue();
    expect(SerialStatus::Sold->canTransitionTo(SerialStatus::Available))->toBeFalse();
    expect(SerialStatus::Disposed->canTransitionTo(SerialStatus::Available))->toBeFalse();
});