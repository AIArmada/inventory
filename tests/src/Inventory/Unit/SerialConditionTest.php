<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\SerialCondition;

test('SerialCondition enum has correct cases', function () {
    expect(SerialCondition::cases())->toHaveCount(6);
    expect(SerialCondition::New->value)->toBe('new');
    expect(SerialCondition::LikeNew->value)->toBe('like_new');
    expect(SerialCondition::Refurbished->value)->toBe('refurbished');
    expect(SerialCondition::Used->value)->toBe('used');
    expect(SerialCondition::Damaged->value)->toBe('damaged');
    expect(SerialCondition::ForParts->value)->toBe('for_parts');
});

test('SerialCondition options returns correct array', function () {
    $options = SerialCondition::options();
    expect($options)->toBeArray();
    expect($options)->toHaveKey('new');
    expect($options['new'])->toBe('New');
});

test('SerialCondition label returns correct labels', function () {
    expect(SerialCondition::New->label())->toBe('New');
    expect(SerialCondition::LikeNew->label())->toBe('Like New');
    expect(SerialCondition::Refurbished->label())->toBe('Refurbished');
    expect(SerialCondition::Used->label())->toBe('Used');
    expect(SerialCondition::Damaged->label())->toBe('Damaged');
    expect(SerialCondition::ForParts->label())->toBe('For Parts Only');
});

test('SerialCondition color returns correct colors', function () {
    expect(SerialCondition::New->color())->toBe('success');
    expect(SerialCondition::LikeNew->color())->toBe('success');
    expect(SerialCondition::Refurbished->color())->toBe('info');
    expect(SerialCondition::Used->color())->toBe('warning');
    expect(SerialCondition::Damaged->color())->toBe('danger');
    expect(SerialCondition::ForParts->color())->toBe('gray');
});

test('SerialCondition isSellable works correctly', function () {
    expect(SerialCondition::New->isSellable())->toBeTrue();
    expect(SerialCondition::LikeNew->isSellable())->toBeTrue();
    expect(SerialCondition::Refurbished->isSellable())->toBeTrue();
    expect(SerialCondition::Used->isSellable())->toBeTrue();
    expect(SerialCondition::Damaged->isSellable())->toBeFalse();
    expect(SerialCondition::ForParts->isSellable())->toBeFalse();
});

test('SerialCondition qualityScore returns correct scores', function () {
    expect(SerialCondition::New->qualityScore())->toBe(10);
    expect(SerialCondition::LikeNew->qualityScore())->toBe(9);
    expect(SerialCondition::Refurbished->qualityScore())->toBe(7);
    expect(SerialCondition::Used->qualityScore())->toBe(5);
    expect(SerialCondition::Damaged->qualityScore())->toBe(2);
    expect(SerialCondition::ForParts->qualityScore())->toBe(1);
});