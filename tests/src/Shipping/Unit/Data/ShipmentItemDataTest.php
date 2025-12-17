<?php

declare(strict_types=1);

use AIArmada\Shipping\Data\ShipmentItemData;

describe('ShipmentItemData', function (): void {
    it('can create shipment item data with required fields', function (): void {
        $item = new ShipmentItemData(
            name: 'Test Item',
            quantity: 2
        );

        expect($item->name)->toBe('Test Item');
        expect($item->quantity)->toBe(2);
        expect($item->sku)->toBeNull();
        expect($item->weight)->toBeNull();
        expect($item->declaredValue)->toBeNull();
        expect($item->description)->toBeNull();
        expect($item->hsCode)->toBeNull();
        expect($item->originCountry)->toBeNull();
        expect($item->shippableItemId)->toBeNull();
        expect($item->shippableItemType)->toBeNull();
    });

    it('can create shipment item data with all fields', function (): void {
        $item = new ShipmentItemData(
            name: 'Premium Widget',
            quantity: 5,
            sku: 'WIDGET-001',
            weight: 250, // 250g
            declaredValue: 1500, // $15.00
            description: 'A premium widget for testing',
            hsCode: '1234.56.78',
            originCountry: 'US',
            shippableItemId: '123',
            shippableItemType: 'App\\Models\\Product'
        );

        expect($item->name)->toBe('Premium Widget');
        expect($item->quantity)->toBe(5);
        expect($item->sku)->toBe('WIDGET-001');
        expect($item->weight)->toBe(250);
        expect($item->declaredValue)->toBe(1500);
        expect($item->description)->toBe('A premium widget for testing');
        expect($item->hsCode)->toBe('1234.56.78');
        expect($item->originCountry)->toBe('US');
        expect($item->shippableItemId)->toBe('123');
        expect($item->shippableItemType)->toBe('App\\Models\\Product');
    });
});
