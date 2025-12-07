<?php

declare(strict_types=1);

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Exceptions\InvalidStatusTransitionException;
use AIArmada\Shipping\Exceptions\ShipmentAlreadyShippedException;
use AIArmada\Shipping\Exceptions\ShipmentCreationFailedException;
use AIArmada\Shipping\Exceptions\ShipmentNotCancellableException;
use AIArmada\Shipping\Models\Shipment;

// ============================================
// InvalidStatusTransitionException Tests
// ============================================

describe('InvalidStatusTransitionException', function (): void {
    it('contains from and to statuses', function (): void {
        $exception = new InvalidStatusTransitionException(
            ShipmentStatus::Delivered,
            ShipmentStatus::InTransit
        );

        expect($exception->from)->toBe(ShipmentStatus::Delivered);
        expect($exception->to)->toBe(ShipmentStatus::InTransit);
    });

    it('has descriptive message', function (): void {
        $exception = new InvalidStatusTransitionException(
            ShipmentStatus::Cancelled,
            ShipmentStatus::Pending
        );

        expect($exception->getMessage())->toContain('cancelled');
        expect($exception->getMessage())->toContain('pending');
        expect($exception->getMessage())->toContain('Cannot transition');
    });
});

// ============================================
// ShipmentAlreadyShippedException Tests
// ============================================

describe('ShipmentAlreadyShippedException', function (): void {
    it('contains shipment reference in message', function (): void {
        $shipment = Mockery::mock(Shipment::class);
        $shipment->shouldReceive('getAttribute')->with('reference')->andReturn('TEST-001');
        $shipment->shouldReceive('__get')->with('reference')->andReturn('TEST-001');

        $exception = new ShipmentAlreadyShippedException($shipment);

        expect($exception->getMessage())->toContain('TEST-001');
        expect($exception->getMessage())->toContain('already been shipped');
        expect($exception->shipment)->toBe($shipment);
    });
});

// ============================================
// ShipmentCreationFailedException Tests
// ============================================

describe('ShipmentCreationFailedException', function (): void {
    it('contains reason in message', function (): void {
        $exception = new ShipmentCreationFailedException('Invalid address format');

        expect($exception->getMessage())->toContain('Invalid address format');
        expect($exception->getMessage())->toContain('Failed to create shipment');
    });
});

// ============================================
// ShipmentNotCancellableException Tests
// ============================================

describe('ShipmentNotCancellableException', function (): void {
    it('contains shipment and status in message', function (): void {
        $shipment = Mockery::mock(Shipment::class);
        $shipment->shouldReceive('getAttribute')->with('reference')->andReturn('TEST-002');
        $shipment->shouldReceive('getAttribute')->with('status')->andReturn(ShipmentStatus::Delivered);
        $shipment->shouldReceive('__get')->with('reference')->andReturn('TEST-002');
        $shipment->shouldReceive('__get')->with('status')->andReturn(ShipmentStatus::Delivered);

        $exception = new ShipmentNotCancellableException($shipment);

        expect($exception->getMessage())->toContain('TEST-002');
        expect($exception->getMessage())->toContain('delivered');
        expect($exception->getMessage())->toContain('cannot be cancelled');
        expect($exception->shipment)->toBe($shipment);
    });
});
