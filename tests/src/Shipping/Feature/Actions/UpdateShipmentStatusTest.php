<?php

declare(strict_types=1);

use AIArmada\Shipping\Actions\UpdateShipmentStatus;
use AIArmada\Shipping\Enums\TrackingStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentEvent;
use AIArmada\Shipping\States\Delivered;
use AIArmada\Shipping\States\Draft;
use AIArmada\Shipping\States\InTransit;
use AIArmada\Shipping\States\Pending;
use AIArmada\Shipping\States\Shipped;

describe('UpdateShipmentStatus', function (): void {
    it('can update shipment status to shipped', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-001',
            'carrier_code' => 'test-carrier',
            'status' => Pending::class,
            'origin_address' => [
                'name' => 'Test Origin',
                'line1' => '123 Origin St',
                'city' => 'Origin City',
                'state' => 'OS',
                'postcode' => '12345',
                'country' => 'US',
            ],
            'destination_address' => [
                'name' => 'Test Destination',
                'line1' => '456 Dest St',
                'city' => 'Dest City',
                'state' => 'DS',
                'postcode' => '67890',
                'country' => 'US',
            ],
        ]);

        $action = new UpdateShipmentStatus;
        $updatedShipment = $action->handle($shipment, Shipped::class, 'Package shipped', 'Warehouse A');

        expect($updatedShipment->status)->toBeInstanceOf(Shipped::class);
        expect($updatedShipment->shipped_at)->not->toBeNull();
        expect($updatedShipment->delivered_at)->toBeNull();

        $event = ShipmentEvent::where('shipment_id', $shipment->id)->first();
        expect($event)->not->toBeNull();
        expect($event->normalized_status)->toBe(TrackingStatus::PickedUp);
        expect($event->description)->toBe('Package shipped');
        expect($event->location)->toBe('Warehouse A');
    });

    it('can update shipment status to delivered', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-002',
            'carrier_code' => 'test-carrier',
            'status' => InTransit::class,
            'shipped_at' => now()->subDay(),
            'origin_address' => [
                'name' => 'Test Origin',
                'line1' => '123 Origin St',
                'city' => 'Origin City',
                'state' => 'OS',
                'postcode' => '12345',
                'country' => 'US',
            ],
            'destination_address' => [
                'name' => 'Test Destination',
                'line1' => '456 Dest St',
                'city' => 'Dest City',
                'state' => 'DS',
                'postcode' => '67890',
                'country' => 'US',
            ],
        ]);

        $action = new UpdateShipmentStatus;
        $updatedShipment = $action->handle($shipment, Delivered::class, 'Package delivered', 'Customer doorstep');

        expect($updatedShipment->status)->toBeInstanceOf(Delivered::class);
        expect($updatedShipment->delivered_at)->not->toBeNull();

        $event = ShipmentEvent::where('shipment_id', $shipment->id)->latest()->first();
        expect($event)->not->toBeNull();
        expect($event->normalized_status)->toBe(TrackingStatus::Delivered);
        expect($event->description)->toBe('Package delivered');
        expect($event->location)->toBe('Customer doorstep');
    });

    it('can update shipment status with metadata', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-003',
            'carrier_code' => 'test-carrier',
            'status' => Shipped::class,
            'origin_address' => [
                'name' => 'Test Origin',
                'line1' => '123 Origin St',
                'city' => 'Origin City',
                'state' => 'OS',
                'postcode' => '12345',
                'country' => 'US',
            ],
            'destination_address' => [
                'name' => 'Test Destination',
                'line1' => '456 Dest St',
                'city' => 'Dest City',
                'state' => 'DS',
                'postcode' => '67890',
                'country' => 'US',
            ],
        ]);

        $metadata = ['tracking_number' => '1Z999AA1234567890', 'carrier' => 'UPS'];

        $action = new UpdateShipmentStatus;
        $updatedShipment = $action->handle($shipment, InTransit::class, 'In transit', null, $metadata);

        expect($updatedShipment->status)->toBeInstanceOf(InTransit::class);

        $event = ShipmentEvent::where('shipment_id', $shipment->id)->first();
        expect($event)->not->toBeNull();
        expect($event->normalized_status)->toBe(TrackingStatus::InTransit);
        expect($event->raw_data)->toBe($metadata);
    });

    it('rejects invalid status transitions', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-004',
            'carrier_code' => 'test-carrier',
            'status' => Draft::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $action = new UpdateShipmentStatus;

        expect(fn () => $action->handle($shipment, Delivered::class))
            ->toThrow(AIArmada\Shipping\Exceptions\InvalidStatusTransitionException::class);
    });
});
