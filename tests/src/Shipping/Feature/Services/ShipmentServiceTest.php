<?php

declare(strict_types=1);

use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\LabelData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentItemData;
use AIArmada\Shipping\Data\ShipmentResultData;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Exceptions\InvalidStatusTransitionException;
use AIArmada\Shipping\Exceptions\ShipmentAlreadyShippedException;
use AIArmada\Shipping\Exceptions\ShipmentNotCancellableException;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Services\ShipmentService;
use AIArmada\Shipping\ShippingManager;

describe('ShipmentService', function (): void {
    beforeEach(function (): void {
        $this->service = app(ShipmentService::class);
        $this->manager = app(ShippingManager::class);
    });

    it('can create a shipment', function (): void {
        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US',
            city: 'Origin City',
            state: 'OS'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            address: '456 Dest St',
            postCode: '67890',
            countryCode: 'US',
            city: 'Dest City',
            state: 'DS'
        );

        $items = [
            new ShipmentItemData(
                name: 'Test Item',
                quantity: 2,
                weight: 500,
                declaredValue: 1000
            ),
        ];

        $data = new ShipmentData(
            reference: 'TEST-SHIP-001',
            carrierCode: 'null',
            serviceCode: 'standard',
            origin: $origin,
            destination: $destination,
            items: $items
        );

        $shipment = $this->service->create($data, 'test-owner-123', 'TestOwner');

        expect($shipment)->toBeInstanceOf(Shipment::class);
        expect($shipment->reference)->toBe('TEST-SHIP-001');
        expect($shipment->carrier_code)->toBe('null');
        expect($shipment->status)->toBe(ShipmentStatus::Draft);
        expect($shipment->owner_id)->toBe('test-owner-123');
        expect($shipment->owner_type)->toBe('TestOwner');
        expect($shipment->items)->toHaveCount(1);
        expect($shipment->items->first()->name)->toBe('Test Item');
        expect($shipment->total_weight)->toBe(1000); // 2 * 500
    });

    it('enforces owner context when owner scoping is enabled', function (): void {
        config(['shipping.features.owner.enabled' => true]);

        $owner = new class extends \Illuminate\Database\Eloquent\Model {
            public $incrementing = false;
            protected $keyType = 'string';
        };
        $owner->setAttribute('id', 'test-owner-123');

        \AIArmada\CommerceSupport\Support\OwnerContext::override($owner);

        $origin = new AddressData(
            name: 'Test Origin',
            phone: '123-456-7890',
            address: '123 Origin St',
            postCode: '12345',
            countryCode: 'US'
        );

        $destination = new AddressData(
            name: 'Test Destination',
            phone: '987-654-3210',
            address: '456 Dest St',
            postCode: '67890',
            countryCode: 'US'
        );

        $data = new ShipmentData(
            reference: 'TEST-SHIP-OWNER',
            carrierCode: 'null',
            serviceCode: 'standard',
            origin: $origin,
            destination: $destination,
            items: [new ShipmentItemData(name: 'Item', quantity: 1, weight: 100)]
        );

        expect(fn () => $this->service->create($data, 'other-owner', 'OtherOwner'))
            ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);

        $shipment = $this->service->create($data);

        expect($shipment->owner_id)->toBe('test-owner-123');
        expect($shipment->owner_type)->toBe($owner->getMorphClass());

        \AIArmada\CommerceSupport\Support\OwnerContext::clearOverride();
        config(['shipping.features.owner.enabled' => false]);
    });

    it('can mark shipment as pending', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-PENDING',
            'carrier_code' => 'null',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $updated = $this->service->markPending($shipment);

        expect($updated->status)->toBe(ShipmentStatus::Pending);
        expect($updated->events)->toHaveCount(1);
        expect($updated->events->first()->description)->toBe('Shipment marked as pending');
    });

    it('cannot mark non-draft shipment as pending', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-PENDING-FAIL',
            'carrier_code' => 'null',
            'status' => 'pending',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect(fn () => $this->service->markPending($shipment))
            ->toThrow(InvalidStatusTransitionException::class);
    });

    it('can update shipment status', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-STATUS',
            'carrier_code' => 'null',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $updated = $this->service->updateStatus($shipment, ShipmentStatus::Pending, 'Test status update');

        expect($updated->status)->toBe(ShipmentStatus::Pending);
        expect($updated->events)->toHaveCount(1);
        expect($updated->events->first()->description)->toBe('Test status update');
    });

    it('cannot update to invalid status', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-INVALID-STATUS',
            'carrier_code' => 'null',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect(fn () => $this->service->updateStatus($shipment, ShipmentStatus::Delivered))
            ->toThrow(InvalidStatusTransitionException::class);
    });

    it('can cancel a cancellable shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-CANCEL',
            'carrier_code' => 'null',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $cancelled = $this->service->cancel($shipment, 'Test cancellation');

        expect($cancelled->status)->toBe(ShipmentStatus::Cancelled);
        expect($cancelled->events)->toHaveCount(1);
        expect($cancelled->events->first()->description)->toBe('Test cancellation');
    });

    it('cannot cancel non-cancellable shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-CANCEL-FAIL',
            'carrier_code' => 'null',
            'status' => 'shipped',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect(fn () => $this->service->cancel($shipment))
            ->toThrow(ShipmentNotCancellableException::class);
    });

    it('can recalculate shipment weight', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-WEIGHT',
            'carrier_code' => 'null',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $shipment->items()->create([
            'name' => 'Heavy Item',
            'quantity' => 3,
            'weight' => 200,
            'declared_value' => 500,
        ]);

        $shipment->items()->create([
            'name' => 'Light Item',
            'quantity' => 1,
            'weight' => 100,
            'declared_value' => 200,
        ]);

        $updated = $this->service->recalculateWeight($shipment);

        expect($updated->total_weight)->toBe(700); // (3 * 200) + (1 * 100)
    });

    it('sets delivered_at when status changes to delivered', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-DELIVERED',
            'carrier_code' => 'null',
            'status' => 'in_transit',
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        $updated = $this->service->updateStatus($shipment, ShipmentStatus::Delivered);

        expect($updated->status)->toBe(ShipmentStatus::Delivered);
        expect($updated->delivered_at)->not->toBeNull();
    });

    it('can ship a pending shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'null',
            'status' => ShipmentStatus::Pending,
            'origin_address' => [
                'name' => 'Test Origin',
                'phone' => '123-456-7890',
                'address' => '123 Origin St',
                'postCode' => '12345',
                'countryCode' => 'US',
                'city' => 'Origin City',
                'state' => 'OS',
            ],
            'destination_address' => [
                'name' => 'Test Destination',
                'phone' => '987-654-3210',
                'address' => '456 Dest St',
                'postCode' => '67890',
                'countryCode' => 'US',
                'city' => 'Dest City',
                'state' => 'DS',
            ],
        ]);

        // Mock the shipping manager and driver
        $mockResult = new ShipmentResultData(
            success: true,
            trackingNumber: 'TRACK123',
            carrierReference: 'CARRIER123',
            labelUrl: 'https://example.com/label.pdf',
            rawResponse: ['success' => true]
        );

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('createShipment')->andReturn($mockResult);
        $mockDriver->shouldReceive('supports')->with(DriverCapability::LabelGeneration)->andReturn(false);

        $this->manager = Mockery::mock(ShippingManager::class);
        $this->manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        // Create service with mocked manager
        $service = new ShipmentService($this->manager);

        $shipped = $service->ship($shipment);

        expect($shipped->status)->toBe(ShipmentStatus::Shipped);
        expect($shipped->tracking_number)->toBe('TRACK123');
        expect($shipped->carrier_reference)->toBe('CARRIER123');
        expect($shipped->label_url)->toBe('https://example.com/label.pdf');
        expect($shipped->shipped_at)->not->toBeNull();
        expect($shipped->events)->toHaveCount(1);
        expect($shipped->events->first()->description)->toBe('Shipment created with carrier');
    });

    it('cannot ship non-pending shipment', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-SHIP',
            'carrier_code' => 'null',
            'status' => ShipmentStatus::Shipped, // Already shipped
            'origin_address' => [
                'name' => 'Test Origin',
                'phone' => '123-456-7890',
                'address' => '123 Origin St',
                'postCode' => '12345',
                'countryCode' => 'US',
                'city' => 'Origin City',
                'state' => 'OS',
            ],
            'destination_address' => [
                'name' => 'Test Destination',
                'phone' => '987-654-3210',
                'address' => '456 Dest St',
                'postCode' => '67890',
                'countryCode' => 'US',
                'city' => 'Dest City',
                'state' => 'DS',
            ],
        ]);

        expect(fn () => $this->service->ship($shipment))
            ->toThrow(ShipmentAlreadyShippedException::class);
    });

    it('can generate shipping label', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-LABEL',
            'carrier_code' => 'null',
            'status' => ShipmentStatus::Shipped,
            'tracking_number' => 'TRACK123',
            'origin_address' => [
                'name' => 'Test Origin',
                'phone' => '123-456-7890',
                'address' => '123 Origin St',
                'postCode' => '12345',
                'countryCode' => 'US',
                'city' => 'Origin City',
                'state' => 'OS',
            ],
            'destination_address' => [
                'name' => 'Test Destination',
                'phone' => '987-654-3210',
                'address' => '456 Dest St',
                'postCode' => '67890',
                'countryCode' => 'US',
                'city' => 'Dest City',
                'state' => 'DS',
            ],
        ]);

        $mockLabelData = new LabelData(
            format: 'pdf',
            url: 'https://example.com/label.pdf',
            content: base64_encode('fake pdf content'),
            size: 'a4',
            trackingNumber: 'TRACK123'
        );

        $mockDriver = Mockery::mock(ShippingDriverInterface::class);
        $mockDriver->shouldReceive('generateLabel')->with('TRACK123', [])->andReturn($mockLabelData);

        $this->manager = Mockery::mock(ShippingManager::class);
        $this->manager->shouldReceive('driver')->with('null')->andReturn($mockDriver);

        $service = new ShipmentService($this->manager);

        $label = $service->generateLabel($shipment);

        expect($label->format)->toBe('pdf');
        expect($label->url)->toBe('https://example.com/label.pdf');
        expect($label->size)->toBe('a4');
        expect($shipment->fresh()->label_url)->toBe('https://example.com/label.pdf');
        expect($shipment->fresh()->label_format)->toBe('pdf');
    });

    it('cannot generate label without tracking number', function (): void {
        $shipment = Shipment::create([
            'owner_type' => 'TestOwner',
            'owner_id' => 'test-owner-123',
            'reference' => 'TEST-LABEL',
            'carrier_code' => 'null',
            'status' => ShipmentStatus::Pending,
            'origin_address' => ['name' => 'Origin'],
            'destination_address' => ['name' => 'Dest'],
        ]);

        expect(fn () => $this->service->generateLabel($shipment))
            ->toThrow(RuntimeException::class, 'Cannot generate label for shipment without tracking number');
    });
});
