<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\CostingMethod;

test('CostingMethod enum has correct cases', function (): void {
    expect(CostingMethod::cases())->toHaveCount(5);
    expect(CostingMethod::Fifo->value)->toBe('fifo');
    expect(CostingMethod::Lifo->value)->toBe('lifo');
    expect(CostingMethod::WeightedAverage->value)->toBe('weighted_average');
    expect(CostingMethod::Standard->value)->toBe('standard');
    expect(CostingMethod::SpecificIdentification->value)->toBe('specific_identification');
});

test('CostingMethod perpetualMethods returns correct methods', function (): void {
    $perpetual = CostingMethod::perpetualMethods();
    expect($perpetual)->toHaveCount(3);
    expect($perpetual)->toHaveKey('fifo');
    expect($perpetual)->toHaveKey('lifo');
    expect($perpetual)->toHaveKey('weighted_average');
});

test('CostingMethod label returns correct labels', function (): void {
    expect(CostingMethod::Fifo->label())->toBe('FIFO (First In, First Out)');
    expect(CostingMethod::Lifo->label())->toBe('LIFO (Last In, First Out)');
    expect(CostingMethod::WeightedAverage->label())->toBe('Weighted Average');
    expect(CostingMethod::Standard->label())->toBe('Standard Cost');
    expect(CostingMethod::SpecificIdentification->label())->toBe('Specific Identification');
});

test('CostingMethod shortLabel returns correct labels', function (): void {
    expect(CostingMethod::Fifo->shortLabel())->toBe('FIFO');
    expect(CostingMethod::Lifo->shortLabel())->toBe('LIFO');
    expect(CostingMethod::WeightedAverage->shortLabel())->toBe('Avg');
    expect(CostingMethod::Standard->shortLabel())->toBe('Std');
    expect(CostingMethod::SpecificIdentification->shortLabel())->toBe('Specific');
});

test('CostingMethod color returns correct colors', function (): void {
    expect(CostingMethod::Fifo->color())->toBe('info');
    expect(CostingMethod::Lifo->color())->toBe('primary');
    expect(CostingMethod::WeightedAverage->color())->toBe('success');
    expect(CostingMethod::Standard->color())->toBe('warning');
    expect(CostingMethod::SpecificIdentification->color())->toBe('gray');
});

test('CostingMethod description returns correct descriptions', function (): void {
    expect(CostingMethod::Fifo->description())->toContain('Oldest inventory sold first');
    expect(CostingMethod::Lifo->description())->toContain('Newest inventory sold first');
    expect(CostingMethod::WeightedAverage->description())->toContain('Average cost of all units');
    expect(CostingMethod::Standard->description())->toContain('Predetermined fixed cost');
    expect(CostingMethod::SpecificIdentification->description())->toContain('Tracks actual cost per unit');
});

test('CostingMethod isPerpetual works correctly', function (): void {
    expect(CostingMethod::Fifo->isPerpetual())->toBeTrue();
    expect(CostingMethod::Lifo->isPerpetual())->toBeTrue();
    expect(CostingMethod::WeightedAverage->isPerpetual())->toBeTrue();
    expect(CostingMethod::Standard->isPerpetual())->toBeFalse();
    expect(CostingMethod::SpecificIdentification->isPerpetual())->toBeFalse();
});

test('CostingMethod requiresLayerTracking works correctly', function (): void {
    expect(CostingMethod::Fifo->requiresLayerTracking())->toBeTrue();
    expect(CostingMethod::Lifo->requiresLayerTracking())->toBeTrue();
    expect(CostingMethod::WeightedAverage->requiresLayerTracking())->toBeFalse();
    expect(CostingMethod::Standard->requiresLayerTracking())->toBeFalse();
    expect(CostingMethod::SpecificIdentification->requiresLayerTracking())->toBeTrue();
});
