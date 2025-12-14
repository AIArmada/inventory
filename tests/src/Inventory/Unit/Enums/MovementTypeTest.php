<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\MovementType;

test('movement type has correct values', function (): void {
    expect(MovementType::Receipt->value)->toBe('receipt');
    expect(MovementType::Shipment->value)->toBe('shipment');
    expect(MovementType::Transfer->value)->toBe('transfer');
    expect(MovementType::Adjustment->value)->toBe('adjustment');
    expect(MovementType::Allocation->value)->toBe('allocation');
    expect(MovementType::Release->value)->toBe('release');
});

test('movement type has correct labels', function (): void {
    expect(MovementType::Receipt->label())->toBe('Receipt');
    expect(MovementType::Shipment->label())->toBe('Shipment');
    expect(MovementType::Transfer->label())->toBe('Transfer');
    expect(MovementType::Adjustment->label())->toBe('Adjustment');
    expect(MovementType::Allocation->label())->toBe('Allocation');
    expect(MovementType::Release->label())->toBe('Release');
});

test('movement type has correct colors', function (): void {
    expect(MovementType::Receipt->color())->toBe('success');
    expect(MovementType::Shipment->color())->toBe('danger');
    expect(MovementType::Transfer->color())->toBe('info');
    expect(MovementType::Adjustment->color())->toBe('warning');
    expect(MovementType::Allocation->color())->toBe('primary');
    expect(MovementType::Release->color())->toBe('gray');
});

test('movement type identifies inbound correctly', function (): void {
    expect(MovementType::Receipt->isInbound())->toBeTrue();
    expect(MovementType::Release->isInbound())->toBeTrue();
    expect(MovementType::Shipment->isInbound())->toBeFalse();
    expect(MovementType::Allocation->isInbound())->toBeFalse();
    expect(MovementType::Transfer->isInbound())->toBeFalse();
    expect(MovementType::Adjustment->isInbound())->toBeFalse();
});

test('movement type identifies outbound correctly', function (): void {
    expect(MovementType::Shipment->isOutbound())->toBeTrue();
    expect(MovementType::Allocation->isOutbound())->toBeTrue();
    expect(MovementType::Receipt->isOutbound())->toBeFalse();
    expect(MovementType::Release->isOutbound())->toBeFalse();
    expect(MovementType::Transfer->isOutbound())->toBeFalse();
    expect(MovementType::Adjustment->isOutbound())->toBeFalse();
});
