<?php

declare(strict_types=1);

use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentEvent;
use AIArmada\Shipping\Models\ShipmentItem;
use AIArmada\Shipping\Models\ShipmentLabel;
use AIArmada\Shipping\States\Draft;
use AIArmada\Shipping\States\Pending;
use AIArmada\Shipping\States\ShipmentStatus;
use AIArmada\Shipping\States\Shipped;
use Illuminate\Support\Str;

describe('Shipment Model', function (): void {

    it('can create a shipment with required fields', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-001',
            'carrier_code' => 'test-carrier',
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

        expect($shipment)->toBeInstanceOf(Shipment::class);
        expect($shipment->id)->toBeString();
        expect($shipment->ulid)->toBeString();
        expect($shipment->reference)->toBe('TEST-001');
        expect($shipment->carrier_code)->toBe('test-carrier');
        expect($shipment->status)->toBeInstanceOf(Draft::class);
        expect($shipment->package_count)->toBe(1);
        expect($shipment->total_weight)->toBe(0);
        expect($shipment->currency)->toBe('MYR');
    });

    it('generates ULID automatically on creation', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-002',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect($shipment->ulid)->toBeString();
        expect(Str::isUlid($shipment->ulid))->toBeTrue();
    });

    it('casts attributes correctly', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-003',
            'carrier_code' => 'test-carrier',
            'status' => Shipped::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
            'package_count' => '2',
            'total_weight' => '1500',
            'shipping_cost' => '500',
            'metadata' => ['key' => 'value'],
        ]);

        expect($shipment->status)->toBeInstanceOf(ShipmentStatus::class);
        expect($shipment->status)->toBeInstanceOf(Shipped::class);
        expect($shipment->package_count)->toBeInt();
        expect($shipment->total_weight)->toBeInt();
        expect($shipment->shipping_cost)->toBeInt();
        expect($shipment->metadata)->toBeArray();
        expect($shipment->metadata['key'])->toBe('value');
    });

    it('has correct relationships', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-004',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        // Test items relationship
        expect($shipment->items())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);

        // Test events relationship
        expect($shipment->events())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);

        // Test labels relationship
        expect($shipment->labels())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);

        // Test shippable relationship
        expect($shipment->shippable())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphTo::class);
    });

    it('can create shipment items', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-005',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $item = $shipment->items()->create([
            'name' => 'Test Item',
            'quantity' => 2,
            'weight' => 500,
            'declared_value' => 1000,
        ]);

        expect($item)->toBeInstanceOf(ShipmentItem::class);
        expect($shipment->items)->toHaveCount(1);
        expect($shipment->items->first()->name)->toBe('Test Item');
    });

    it('can create shipment events', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-006',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $event = $shipment->events()->create([
            'normalized_status' => 'picked_up',
            'description' => 'Package picked up',
            'location' => 'Warehouse',
            'occurred_at' => now(),
        ]);

        expect($event)->toBeInstanceOf(ShipmentEvent::class);
        expect($shipment->events)->toHaveCount(1);
        expect($shipment->getLatestEvent()->description)->toBe('Package picked up');
    });

    it('provides status helper methods', function (): void {
        $draftShipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-007',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect($draftShipment->isPending())->toBeFalse();
        expect($draftShipment->isInTransit())->toBeFalse();
        expect($draftShipment->isDelivered())->toBeFalse();
        expect($draftShipment->isCancellable())->toBeTrue();
        expect($draftShipment->isTerminal())->toBeFalse();

        $shippedShipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-008',
            'carrier_code' => 'test-carrier',
            'status' => Shipped::class,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect($shippedShipment->isPending())->toBeFalse();
        expect($shippedShipment->isInTransit())->toBeTrue();
        expect($shippedShipment->isDelivered())->toBeFalse();
        expect($shippedShipment->isCancellable())->toBeFalse();
    });

    it('provides accessors', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-009',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
            'shipping_cost' => 1234,
            'total_weight' => 2500,
            'currency' => 'USD',
        ]);

        expect($shipment->getFormattedShippingCost())->toBe('$12.34');
        expect($shipment->getTotalWeightKg())->toBe(2.5);
    });

    it('handles cash on delivery', function (): void {
        $codShipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-010',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
            'cod_amount' => 5000,
        ]);

        $regularShipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-011',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect($codShipment->isCashOnDelivery())->toBeTrue();
        expect($regularShipment->isCashOnDelivery())->toBeFalse();
    });

    it('cascades deletes to related models', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-012',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $shipment->items()->create(['name' => 'Item', 'quantity' => 1, 'weight' => 100, 'declared_value' => 500]);
        $shipment->events()->create(['normalized_status' => 'label_created', 'description' => 'Created', 'occurred_at' => now()]);
        $shipment->labels()->create(['format' => 'pdf', 'url' => 'http://example.com/label.pdf', 'generated_at' => now()]);

        expect(ShipmentItem::count())->toBe(1);
        expect(ShipmentEvent::count())->toBe(1);
        expect(ShipmentLabel::count())->toBe(1);

        $shipment->delete();

        expect(ShipmentItem::count())->toBe(0);
        expect(ShipmentEvent::count())->toBe(0);
        expect(ShipmentLabel::count())->toBe(0);
    });

    it('can transition status correctly', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-013',
            'carrier_code' => 'test-carrier',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect($shipment->canTransitionTo(Pending::class))->toBeTrue();
        expect($shipment->canTransitionTo(Shipped::class))->toBeFalse();

        $shipment->update(['status' => Pending::class]);
        expect($shipment->canTransitionTo(Shipped::class))->toBeTrue();
    });
});
