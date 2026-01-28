<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentShipping\Services\CartBridge;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentItemData;

uses(TestCase::class);

// ============================================
// CartBridge Service Tests
// ============================================

beforeEach(function (): void {
    $this->bridge = new CartBridge;
});

describe('createShipmentDataFromOrder', function (): void {
    it('creates ShipmentData from order array', function (): void {
        $orderData = [
            'id' => 'order-123',
            'reference' => 'ORD-2024-001',
            'shipping_address' => [
                'name' => 'John Doe',
                'phone' => '+60123456789',
                'line1' => '123 Main Street',
                'city' => 'Kuala Lumpur',
                'state' => 'WP Kuala Lumpur',
                'postcode' => '50000',
                'country' => 'MY',
            ],
            'items' => [
                [
                    'name' => 'Product A',
                    'sku' => 'SKU-001',
                    'quantity' => 2,
                    'weight' => 500,
                    'declared_value' => 5000,
                ],
                [
                    'name' => 'Product B',
                    'sku' => 'SKU-002',
                    'quantity' => 1,
                    'weight' => 300,
                    'declared_value' => 3000,
                ],
            ],
            'carrier_code' => 'jnt',
            'service_code' => 'EZ',
            'declared_value' => 13000,
            'instructions' => 'Handle with care',
        ];

        $shipmentData = $this->bridge->createShipmentDataFromOrder($orderData);

        expect($shipmentData)->toBeInstanceOf(ShipmentData::class);
        expect($shipmentData->reference)->toBe('ORD-2024-001');
        expect($shipmentData->carrierCode)->toBe('jnt');
        expect($shipmentData->serviceCode)->toBe('EZ');
        expect($shipmentData->declaredValue)->toBe(13000);
        expect($shipmentData->instructions)->toBe('Handle with care');
    });

    it('uses order id as reference when reference not provided', function (): void {
        $orderData = [
            'id' => 'order-123',
            'carrier_code' => 'manual',
            'service_code' => 'standard',
            'shipping_address' => [
                'name' => 'John Doe',
                'phone' => '+60123456789',
                'line1' => '123 Main Street',
                'postcode' => '50000',
            ],
            'items' => [],
        ];

        $shipmentData = $this->bridge->createShipmentDataFromOrder($orderData);

        expect($shipmentData->reference)->toBe('order-123');
    });

    it('creates correct destination address', function (): void {
        $orderData = [
            'id' => 'order-123',
            'carrier_code' => 'jnt',
            'service_code' => 'EZ',
            'shipping_address' => [
                'name' => 'Jane Doe',
                'phone' => '+60198765432',
                'line1' => '456 Second Street',
                'city' => 'Petaling Jaya',
                'state' => 'Selangor',
                'postcode' => '47810',
                'country' => 'MY',
            ],
            'items' => [],
        ];

        $shipmentData = $this->bridge->createShipmentDataFromOrder($orderData);

        expect($shipmentData->destination)->toBeInstanceOf(AddressData::class);
        expect($shipmentData->destination->name)->toBe('Jane Doe');
        expect($shipmentData->destination->phone)->toBe('+60198765432');
        expect($shipmentData->destination->line1)->toBe('456 Second Street');
        expect($shipmentData->destination->city)->toBe('Petaling Jaya');
        expect($shipmentData->destination->state)->toBe('Selangor');
        expect($shipmentData->destination->postcode)->toBe('47810');
        expect($shipmentData->destination->country)->toBe('MY');
    });

    it('creates correct shipment items', function (): void {
        $orderData = [
            'id' => 'order-123',
            'carrier_code' => 'jnt',
            'service_code' => 'EZ',
            'shipping_address' => [
                'name' => 'John Doe',
                'phone' => '+60123456789',
                'line1' => '123 Main Street',
                'postcode' => '50000',
            ],
            'items' => [
                [
                    'name' => 'Product A',
                    'sku' => 'SKU-001',
                    'quantity' => 2,
                    'weight' => 500,
                    'declared_value' => 5000,
                ],
            ],
        ];

        $shipmentData = $this->bridge->createShipmentDataFromOrder($orderData);

        expect($shipmentData->items)->toHaveCount(1);
        expect($shipmentData->items[0])->toBeInstanceOf(ShipmentItemData::class);
        expect($shipmentData->items[0]->name)->toBe('Product A');
        expect($shipmentData->items[0]->sku)->toBe('SKU-001');
        expect($shipmentData->items[0]->quantity)->toBe(2);
        expect($shipmentData->items[0]->weight)->toBe(500);
        expect($shipmentData->items[0]->declaredValue)->toBe(5000);
    });

    it('calculates declared value from items when not provided', function (): void {
        $orderData = [
            'id' => 'order-123',
            'carrier_code' => 'jnt',
            'service_code' => 'EZ',
            'shipping_address' => [
                'name' => 'John Doe',
                'phone' => '+60123456789',
                'line1' => '123 Main Street',
                'postcode' => '50000',
            ],
            'items' => [
                [
                    'name' => 'Product A',
                    'quantity' => 2,
                    'declared_value' => 5000,
                ],
                [
                    'name' => 'Product B',
                    'quantity' => 1,
                    'declared_value' => 3000,
                ],
            ],
        ];

        $shipmentData = $this->bridge->createShipmentDataFromOrder($orderData);

        // 2 * 5000 + 1 * 3000 = 13000
        expect($shipmentData->declaredValue)->toBe(13000);
    });

    it('handles COD amount', function (): void {
        $orderData = [
            'id' => 'order-123',
            'carrier_code' => 'jnt',
            'service_code' => 'EZ',
            'shipping_address' => [
                'name' => 'John Doe',
                'phone' => '+60123456789',
                'address' => '123 Main Street',
                'post_code' => '50000',
            ],
            'items' => [],
            'cod_amount' => 10000,
        ];

        $shipmentData = $this->bridge->createShipmentDataFromOrder($orderData);

        expect($shipmentData->codAmount)->toBe(10000);
    });
});

describe('isCartPackageInstalled', function (): void {
    it('returns boolean', function (): void {
        $result = $this->bridge->isCartPackageInstalled();

        expect($result)->toBeBool();
    });

    it('returns true when cart package is installed', function (): void {
        // Cart package is installed in test environment
        expect($this->bridge->isCartPackageInstalled())->toBeTrue();
    });
});

describe('getOrderUrl', function (): void {
    it('returns null when Orders resource does not exist', function (): void {
        $result = $this->bridge->getOrderUrl('order-123');

        // Since FilamentOrders package may not be installed in test env
        expect($result)->toBeNull();
    });
});
