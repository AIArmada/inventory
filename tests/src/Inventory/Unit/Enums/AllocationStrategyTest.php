<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\AllocationStrategy;

it('has correct allocation strategy values', function (): void {
    expect(AllocationStrategy::Priority->value)->toBe('priority');
    expect(AllocationStrategy::FIFO->value)->toBe('fifo');
    expect(AllocationStrategy::LeastStock->value)->toBe('least_stock');
    expect(AllocationStrategy::SingleLocation->value)->toBe('single_location');
});

it('can get all allocation strategy values', function (): void {
    $cases = AllocationStrategy::cases();

    expect($cases)->toHaveCount(4);
});

it('can create allocation strategy from value', function (): void {
    $strategy = AllocationStrategy::from('fifo');

    expect($strategy)->toBe(AllocationStrategy::FIFO);
});

it('throws for invalid allocation strategy value', function (): void {
    AllocationStrategy::from('invalid_strategy');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $strategy = AllocationStrategy::tryFrom('priority');
    $invalid = AllocationStrategy::tryFrom('invalid');

    expect($strategy)->toBe(AllocationStrategy::Priority);
    expect($invalid)->toBeNull();
});

it('has labels for all strategies', function (): void {
    expect(AllocationStrategy::Priority->label())->toBe('Priority (Highest First)');
    expect(AllocationStrategy::FIFO->label())->toBe('FIFO (First In, First Out)');
    expect(AllocationStrategy::LeastStock->label())->toBe('Least Stock (Balance Inventory)');
    expect(AllocationStrategy::SingleLocation->label())->toBe('Single Location (No Split)');
});

it('has descriptions for all strategies', function (): void {
    expect(AllocationStrategy::Priority->description())->toBeString();
    expect(AllocationStrategy::FIFO->description())->toBeString();
});

it('correctly identifies split allowance', function (): void {
    expect(AllocationStrategy::Priority->allowsSplit())->toBeTrue();
    expect(AllocationStrategy::FIFO->allowsSplit())->toBeTrue();
    expect(AllocationStrategy::SingleLocation->allowsSplit())->toBeFalse();
});
