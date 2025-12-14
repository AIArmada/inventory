<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\CostingMethod;

it('has correct costing method values', function (): void {
    expect(CostingMethod::Fifo->value)->toBe('fifo');
    expect(CostingMethod::Lifo->value)->toBe('lifo');
    expect(CostingMethod::WeightedAverage->value)->toBe('weighted_average');
    expect(CostingMethod::Standard->value)->toBe('standard');
    expect(CostingMethod::SpecificIdentification->value)->toBe('specific_identification');
});

it('can get all costing method values', function (): void {
    $cases = CostingMethod::cases();

    expect($cases)->toHaveCount(5);
});

it('can create costing method from value', function (): void {
    $method = CostingMethod::from('weighted_average');

    expect($method)->toBe(CostingMethod::WeightedAverage);
});

it('throws for invalid costing method value', function (): void {
    CostingMethod::from('invalid_method');
})->throws(ValueError::class);

it('can try from value', function (): void {
    $method = CostingMethod::tryFrom('fifo');
    $invalid = CostingMethod::tryFrom('invalid');

    expect($method)->toBe(CostingMethod::Fifo);
    expect($invalid)->toBeNull();
});

it('has labels', function (): void {
    expect(CostingMethod::Fifo->label())->toBe('FIFO (First In, First Out)');
    expect(CostingMethod::Standard->label())->toBe('Standard Cost');
});

it('has short labels', function (): void {
    expect(CostingMethod::Fifo->shortLabel())->toBe('FIFO');
    expect(CostingMethod::Standard->shortLabel())->toBe('Std');
});

it('checks if perpetual', function (): void {
    expect(CostingMethod::Fifo->isPerpetual())->toBeTrue();
    expect(CostingMethod::Standard->isPerpetual())->toBeFalse();
});

it('checks if requires layer tracking', function (): void {
    expect(CostingMethod::Fifo->requiresLayerTracking())->toBeTrue();
    expect(CostingMethod::WeightedAverage->requiresLayerTracking())->toBeFalse();
});
