<?php

declare(strict_types=1);

use AIArmada\Shipping\Enums\TrackingStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentEvent;

describe('ShipmentEvent Model', function (): void {
    it('can create a shipment event with required fields', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $event = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'picked_up',
            'description' => 'Package picked up',
            'occurred_at' => now(),
        ]);

        expect($event)->toBeInstanceOf(ShipmentEvent::class);
        expect($event->shipment_id)->toBe($shipment->id);
        expect($event->normalized_status)->toBeInstanceOf(TrackingStatus::class);
        expect($event->normalized_status)->toBe(TrackingStatus::PickedUp);
        expect($event->description)->toBe('Package picked up');
        expect($event->occurred_at)->not->toBeNull();
    });

    it('belongs to a shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $event = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'in_transit',
            'description' => 'In transit',
            'occurred_at' => now(),
        ]);

        expect($event->shipment)->toBeInstanceOf(Shipment::class);
        expect($event->shipment->id)->toBe($shipment->id);
    });

    it('can create events with optional fields', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $event = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'carrier_event_code' => 'PU',
            'normalized_status' => 'picked_up',
            'description' => 'Picked up from warehouse',
            'location' => 'Main Warehouse',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'postcode' => '10001',
            'occurred_at' => now(),
            'raw_data' => ['carrier_ref' => 'ABC123'],
        ]);

        expect($event->carrier_event_code)->toBe('PU');
        expect($event->location)->toBe('Main Warehouse');
        expect($event->city)->toBe('New York');
        expect($event->state)->toBe('NY');
        expect($event->country)->toBe('US');
        expect($event->postcode)->toBe('10001');
        expect($event->raw_data)->toBe(['carrier_ref' => 'ABC123']);
    });

    it('orders events by occurred_at descending', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $event1 = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'picked_up',
            'description' => 'Picked up',
            'occurred_at' => now()->subHours(2),
        ]);

        $event2 = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'in_transit',
            'description' => 'In transit',
            'occurred_at' => now()->subHour(),
        ]);

        $event3 = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'delivered',
            'description' => 'Delivered',
            'occurred_at' => now(),
        ]);

        $events = $shipment->events;

        expect($events)->toHaveCount(3);
        expect($events->first()->description)->toBe('Delivered');
        expect($events->last()->description)->toBe('Picked up');
    });

    it('cascades delete when shipment is deleted', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'picked_up',
            'description' => 'Picked up',
            'occurred_at' => now(),
        ]);

        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'delivered',
            'description' => 'Delivered',
            'occurred_at' => now(),
        ]);

        expect(ShipmentEvent::count())->toBe(2);

        $shipment->delete();

        expect(ShipmentEvent::count())->toBe(0);
    });

    it('can format location from address components', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $event = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'in_transit',
            'description' => 'In transit',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'US',
            'occurred_at' => now(),
        ]);

        expect($event->getFormattedLocation())->toBe('New York, NY, US');
    });

    it('can format location with missing components', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $event = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'in_transit',
            'description' => 'In transit',
            'city' => 'New York',
            'country' => 'US',
            'occurred_at' => now(),
        ]);

        expect($event->getFormattedLocation())->toBe('New York, US');
    });

    it('can determine if event is an exception', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $normalEvent = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'in_transit',
            'description' => 'In transit',
            'occurred_at' => now(),
        ]);

        $exceptionEvent = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'delivery_attempt_failed',
            'description' => 'Delivery failed',
            'occurred_at' => now(),
        ]);

        expect($normalEvent->isException())->toBeFalse();
        expect($exceptionEvent->isException())->toBeTrue();
    });

    it('can determine if event is terminal', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $inTransitEvent = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'in_transit',
            'description' => 'In transit',
            'occurred_at' => now(),
        ]);

        $deliveredEvent = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'normalized_status' => 'delivered',
            'description' => 'Delivered',
            'occurred_at' => now(),
        ]);

        expect($inTransitEvent->isTerminal())->toBeFalse();
        expect($deliveredEvent->isTerminal())->toBeTrue();
    });
});
