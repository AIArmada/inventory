<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\SerialEventType;

test('SerialEventType enum has correct cases', function (): void {
    expect(SerialEventType::cases())->toHaveCount(17);
    expect(SerialEventType::Registered->value)->toBe('registered');
    expect(SerialEventType::Received->value)->toBe('received');
    expect(SerialEventType::Sold->value)->toBe('sold');
    expect(SerialEventType::Disposed->value)->toBe('disposed');
});

test('SerialEventType label returns correct labels', function (): void {
    expect(SerialEventType::Registered->label())->toBe('Registered');
    expect(SerialEventType::Received->label())->toBe('Received');
    expect(SerialEventType::Sold->label())->toBe('Sold');
    expect(SerialEventType::Disposed->label())->toBe('Disposed');
    expect(SerialEventType::Lost->label())->toBe('Marked as Lost');
    expect(SerialEventType::RepairStarted->label())->toBe('Repair Started');
});

test('SerialEventType icon returns correct icons', function (): void {
    expect(SerialEventType::Registered->icon())->toBe('heroicon-o-plus-circle');
    expect(SerialEventType::Received->icon())->toBe('heroicon-o-inbox-arrow-down');
    expect(SerialEventType::Sold->icon())->toBe('heroicon-o-shopping-cart');
    expect(SerialEventType::Disposed->icon())->toBe('heroicon-o-trash');
    expect(SerialEventType::Lost->icon())->toBe('heroicon-o-exclamation-triangle');
    expect(SerialEventType::Found->icon())->toBe('heroicon-o-magnifying-glass');
    expect(SerialEventType::Recalled->icon())->toBe('heroicon-o-bell-alert');
    expect(SerialEventType::WarrantyUpdated->icon())->toBe('heroicon-o-shield-check');
});

test('SerialEventType color returns correct colors', function (): void {
    expect(SerialEventType::Registered->color())->toBe('success');
    expect(SerialEventType::Transferred->color())->toBe('info');
    expect(SerialEventType::Sold->color())->toBe('primary');
    expect(SerialEventType::Disposed->color())->toBe('danger');
    expect(SerialEventType::Lost->color())->toBe('danger');
    expect(SerialEventType::Found->color())->toBe('success');
    expect(SerialEventType::Recalled->color())->toBe('danger');
    expect(SerialEventType::WarrantyUpdated->color())->toBe('gray');
    expect(SerialEventType::NoteAdded->color())->toBe('gray');
});
