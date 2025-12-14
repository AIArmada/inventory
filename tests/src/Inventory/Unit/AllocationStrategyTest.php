<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\AllocationStrategy;

test('AllocationStrategy enum has correct cases', function () {
    expect(AllocationStrategy::cases())->toHaveCount(4);
    expect(AllocationStrategy::Priority->value)->toBe('priority');
    expect(AllocationStrategy::FIFO->value)->toBe('fifo');
    expect(AllocationStrategy::LeastStock->value)->toBe('least_stock');
    expect(AllocationStrategy::SingleLocation->value)->toBe('single_location');
});

test('AllocationStrategy label returns correct labels', function () {
    expect(AllocationStrategy::Priority->label())->toBe('Priority (Highest First)');
    expect(AllocationStrategy::FIFO->label())->toBe('FIFO (First In, First Out)');
    expect(AllocationStrategy::LeastStock->label())->toBe('Least Stock (Balance Inventory)');
    expect(AllocationStrategy::SingleLocation->label())->toBe('Single Location (No Split)');
});

test('AllocationStrategy description returns correct descriptions', function () {
    expect(AllocationStrategy::Priority->description())->toBe('Allocate from locations with highest priority first');
    expect(AllocationStrategy::FIFO->description())->toBe('Allocate from locations with oldest stock first');
    expect(AllocationStrategy::LeastStock->description())->toBe('Allocate to balance inventory levels across locations');
    expect(AllocationStrategy::SingleLocation->description())->toBe('Allocate from a single location or fail if insufficient');
});

test('AllocationStrategy allowsSplit works correctly', function () {
    expect(AllocationStrategy::Priority->allowsSplit())->toBeTrue();
    expect(AllocationStrategy::FIFO->allowsSplit())->toBeTrue();
    expect(AllocationStrategy::LeastStock->allowsSplit())->toBeTrue();
    expect(AllocationStrategy::SingleLocation->allowsSplit())->toBeFalse();
});