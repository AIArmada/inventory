<?php

declare(strict_types=1);

use AIArmada\Shipping\Actions\CreateShipment;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;

describe('CreateShipment Action', function (): void {
    it('can create a shipment with minimal data', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'reference' => 'TEST-CREATE-001',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ];

        $shipment = $action->handle($data);

        expect($shipment)->toBeInstanceOf(Shipment::class);
        expect($shipment->reference)->toBe('TEST-CREATE-001');
        expect($shipment->carrier_code)->toBe('test-carrier');
        expect($shipment->status)->toBe(ShipmentStatus::Draft);
    });

    it('can create a shipment with full data', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'reference' => 'TEST-CREATE-002',
            'carrier_code' => 'test-carrier',
            'service_code' => 'express',
            'status' => 'pending',
            'tracking_number' => 'TRACK123',
            'origin_address' => ['name' => 'Origin', 'city' => 'Origin City'],
            'destination_address' => ['name' => 'Dest', 'city' => 'Dest City'],
            'total_weight' => 1500,
            'declared_value' => 5000,
            'shipping_cost' => 2500,
            'currency' => 'USD',
            'estimated_delivery_at' => now()->addDays(2),
            'metadata' => ['test' => 'data'],
            'shippable_type' => 'Order',
            'shippable_id' => 123,
        ];

        $shipment = $action->handle($data);

        expect($shipment)->toBeInstanceOf(Shipment::class);
        expect($shipment->reference)->toBe('TEST-CREATE-002');
        expect($shipment->carrier_code)->toBe('test-carrier');
        expect($shipment->service_code)->toBe('express');
        expect($shipment->status)->toBe(ShipmentStatus::Pending);
        expect($shipment->tracking_number)->toBe('TRACK123');
        expect($shipment->total_weight)->toBe(1500);
        expect($shipment->declared_value)->toBe(5000);
        expect($shipment->shipping_cost)->toBe(2500);
        expect($shipment->currency)->toBe('USD');
        expect($shipment->metadata)->toBe(['test' => 'data']);
        expect($shipment->shippable_type)->toBe('Order');
        expect($shipment->shippable_id)->toBe(123);
    });

    it('generates reference automatically when not provided', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ];

        $shipment = $action->handle($data);

        expect($shipment->reference)->toBeString();
        expect($shipment->reference)->toContain('SHP-');
    });

    it('handles status as string', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'reference' => 'TEST-STATUS',
            'carrier_code' => 'test-carrier',
            'status' => 'shipped',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ];

        $shipment = $action->handle($data);

        expect($shipment->status)->toBe(ShipmentStatus::Shipped);
    });

    it('defaults to draft status when invalid status provided', function (): void {
        $action = app(CreateShipment::class);

        $data = [
            'reference' => 'TEST-INVALID-STATUS',
            'carrier_code' => 'test-carrier',
            'status' => 'invalid-status',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ];

        $shipment = $action->handle($data);

        expect($shipment->status)->toBe(ShipmentStatus::Draft);
    });

    it('requires owner context when owner scoping is enabled', function (): void {
        config(['shipping.features.owner.enabled' => true]);

        try {
            // Force OwnerContext::resolve() to return null even if a resolver is bound.
            \AIArmada\CommerceSupport\Support\OwnerContext::override(null);

            $action = app(CreateShipment::class);

            $data = [
                'reference' => 'TEST-OWNER-CONTEXT',
                'carrier_code' => 'test-carrier',
                'origin_address' => ['name' => 'Origin'],
                'destination_address' => ['name' => 'Dest'],
            ];

            expect(fn () => $action->handle($data))
                ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
        } finally {
            \AIArmada\CommerceSupport\Support\OwnerContext::clearOverride();
            config(['shipping.features.owner.enabled' => false]);
        }
    });
});
