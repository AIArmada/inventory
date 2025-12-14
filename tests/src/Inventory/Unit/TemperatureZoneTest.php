<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\TemperatureZone;

test('TemperatureZone enum has correct cases', function () {
    expect(TemperatureZone::cases())->toHaveCount(6);
    expect(TemperatureZone::Ambient->value)->toBe('ambient');
    expect(TemperatureZone::Chilled->value)->toBe('chilled');
    expect(TemperatureZone::Frozen->value)->toBe('frozen');
    expect(TemperatureZone::DeepFrozen->value)->toBe('deep_frozen');
});

test('TemperatureZone options returns correct array', function () {
    $options = TemperatureZone::options();
    expect($options)->toBeArray();
    expect($options)->toHaveKey('ambient');
    expect($options['ambient'])->toBe('Ambient (15-25°C)');
});

test('TemperatureZone label returns correct labels', function () {
    expect(TemperatureZone::Ambient->label())->toBe('Ambient (15-25°C)');
    expect(TemperatureZone::Chilled->label())->toBe('Chilled (2-8°C)');
    expect(TemperatureZone::Frozen->label())->toBe('Frozen (-18 to -22°C)');
    expect(TemperatureZone::DeepFrozen->label())->toBe('Deep Frozen (-25°C and below)');
});

test('TemperatureZone minTemperature returns correct temperatures', function () {
    expect(TemperatureZone::Ambient->minTemperature())->toBe(15.0);
    expect(TemperatureZone::Chilled->minTemperature())->toBe(2.0);
    expect(TemperatureZone::Frozen->minTemperature())->toBe(-22.0);
    expect(TemperatureZone::DeepFrozen->minTemperature())->toBe(-40.0);
    expect(TemperatureZone::Controlled->minTemperature())->toBe(15.0);
    expect(TemperatureZone::ClimateControlled->minTemperature())->toBe(10.0);
});

test('TemperatureZone maxTemperature returns correct temperatures', function () {
    expect(TemperatureZone::Ambient->maxTemperature())->toBe(25.0);
    expect(TemperatureZone::Chilled->maxTemperature())->toBe(8.0);
    expect(TemperatureZone::Frozen->maxTemperature())->toBe(-18.0);
    expect(TemperatureZone::DeepFrozen->maxTemperature())->toBe(-25.0);
    expect(TemperatureZone::Controlled->maxTemperature())->toBe(25.0);
    expect(TemperatureZone::ClimateControlled->maxTemperature())->toBe(25.0);
});

test('TemperatureZone color returns correct colors', function () {
    expect(TemperatureZone::Ambient->color())->toBe('gray');
    expect(TemperatureZone::Chilled->color())->toBe('info');
    expect(TemperatureZone::Frozen->color())->toBe('primary');
    expect(TemperatureZone::DeepFrozen->color())->toBe('danger');
    expect(TemperatureZone::Controlled->color())->toBe('warning');
    expect(TemperatureZone::ClimateControlled->color())->toBe('success');
});

test('TemperatureZone isCompatibleWith works correctly', function () {
    expect(TemperatureZone::Ambient->isCompatibleWith(TemperatureZone::Ambient))->toBeTrue();
    expect(TemperatureZone::Ambient->isCompatibleWith(TemperatureZone::Controlled))->toBeTrue();
    expect(TemperatureZone::Chilled->isCompatibleWith(TemperatureZone::Frozen))->toBeFalse();
    expect(TemperatureZone::Frozen->isCompatibleWith(TemperatureZone::DeepFrozen))->toBeTrue();
    expect(TemperatureZone::DeepFrozen->isCompatibleWith(TemperatureZone::Frozen))->toBeTrue();
    expect(TemperatureZone::Controlled->isCompatibleWith(TemperatureZone::Ambient))->toBeTrue();
    expect(TemperatureZone::Controlled->isCompatibleWith(TemperatureZone::ClimateControlled))->toBeTrue();
});

test('TemperatureZone isTemperatureSensitive works correctly', function () {
    expect(TemperatureZone::Ambient->isTemperatureSensitive())->toBeFalse();
    expect(TemperatureZone::Chilled->isTemperatureSensitive())->toBeTrue();
    expect(TemperatureZone::Frozen->isTemperatureSensitive())->toBeTrue();
    expect(TemperatureZone::DeepFrozen->isTemperatureSensitive())->toBeTrue();
    expect(TemperatureZone::Controlled->isTemperatureSensitive())->toBeFalse();
});