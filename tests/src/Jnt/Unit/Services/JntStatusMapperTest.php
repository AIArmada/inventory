<?php

declare(strict_types=1);

use AIArmada\Jnt\Enums\ScanTypeCode;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Shipping\Contracts\StatusMapperInterface;
use AIArmada\Shipping\Enums\TrackingStatus as NormalizedTrackingStatus;

// ============================================
// JntStatusMapper Tests
// ============================================

beforeEach(function (): void {
    $this->mapper = new JntStatusMapper;
});

describe('StatusMapperInterface implementation', function (): void {
    it('implements StatusMapperInterface', function (): void {
        expect($this->mapper)->toBeInstanceOf(StatusMapperInterface::class);
    });

    it('returns correct carrier code', function (): void {
        expect($this->mapper->getCarrierCode())->toBe('jnt');
    });

    it('maps carrier event code to normalized status', function (): void {
        $status = $this->mapper->map(ScanTypeCode::PARCEL_PICKUP->value);

        expect($status)->toBeInstanceOf(NormalizedTrackingStatus::class);
        expect($status)->toBe(NormalizedTrackingStatus::PickedUp);
    });

    it('returns OnHold for unknown event codes', function (): void {
        $status = $this->mapper->map('UNKNOWN_CODE');

        expect($status)->toBe(NormalizedTrackingStatus::OnHold);
    });
});

describe('fromScanType mapping', function (): void {
    it('maps pickup scan types correctly', function (): void {
        expect($this->mapper->fromScanType(ScanTypeCode::PARCEL_PICKUP))
            ->toBe(TrackingStatus::PickedUp);

        expect($this->mapper->fromScanType(ScanTypeCode::PICKED_UP_FROM_CARGO))
            ->toBe(TrackingStatus::PickedUp);
    });

    it('maps hub/facility scan types correctly', function (): void {
        expect($this->mapper->fromScanType(ScanTypeCode::PACKAGE_INBOUND))
            ->toBe(TrackingStatus::AtHub);

        expect($this->mapper->fromScanType(ScanTypeCode::CENTER_INBOUND))
            ->toBe(TrackingStatus::AtHub);

        expect($this->mapper->fromScanType(ScanTypeCode::ARRIVAL))
            ->toBe(TrackingStatus::AtHub);
    });

    it('maps transit scan types correctly', function (): void {
        expect($this->mapper->fromScanType(ScanTypeCode::OUTBOUND_SCAN))
            ->toBe(TrackingStatus::InTransit);

        expect($this->mapper->fromScanType(ScanTypeCode::CUSTOMS_CLEARANCE))
            ->toBe(TrackingStatus::InTransit);
    });

    it('maps delivery scan type correctly', function (): void {
        expect($this->mapper->fromScanType(ScanTypeCode::DELIVERY_SCAN))
            ->toBe(TrackingStatus::OutForDelivery);
    });

    it('maps delivered scan types correctly', function (): void {
        expect($this->mapper->fromScanType(ScanTypeCode::PARCEL_SIGNED))
            ->toBe(TrackingStatus::Delivered);

        expect($this->mapper->fromScanType(ScanTypeCode::COLLECTED))
            ->toBe(TrackingStatus::Delivered);
    });

    it('maps return scan types correctly', function (): void {
        expect($this->mapper->fromScanType(ScanTypeCode::RETURN_SCAN))
            ->toBe(TrackingStatus::ReturnInitiated);

        expect($this->mapper->fromScanType(ScanTypeCode::RETURN_SIGN))
            ->toBe(TrackingStatus::Returned);
    });

    it('maps exception scan types correctly', function (): void {
        expect($this->mapper->fromScanType(ScanTypeCode::PROBLEMATIC_SCANNING))
            ->toBe(TrackingStatus::Exception);

        expect($this->mapper->fromScanType(ScanTypeCode::DAMAGE_PARCEL))
            ->toBe(TrackingStatus::Exception);

        expect($this->mapper->fromScanType(ScanTypeCode::LOST_PARCEL))
            ->toBe(TrackingStatus::Exception);
    });
});

describe('fromCode mapping', function (): void {
    it('maps valid scan type code string', function (): void {
        $status = $this->mapper->fromCode(ScanTypeCode::PARCEL_PICKUP->value);

        expect($status)->toBe(TrackingStatus::PickedUp);
    });

    it('returns Exception for invalid code', function (): void {
        $status = $this->mapper->fromCode('INVALID_CODE');

        expect($status)->toBe(TrackingStatus::Exception);
    });
});

describe('fromString mapping', function (): void {
    it('maps pending status descriptions', function (): void {
        expect($this->mapper->fromString('Order pending'))
            ->toBe(TrackingStatus::Pending);
    });

    it('maps pickup status descriptions', function (): void {
        expect($this->mapper->fromString('Parcel picked up'))
            ->toBe(TrackingStatus::PickedUp);

        expect($this->mapper->fromString('Collected from sender'))
            ->toBe(TrackingStatus::PickedUp);
    });

    it('maps transit status descriptions', function (): void {
        expect($this->mapper->fromString('Package in transit'))
            ->toBe(TrackingStatus::InTransit);

        expect($this->mapper->fromString('Shipped'))
            ->toBe(TrackingStatus::InTransit);
    });

    it('maps hub status descriptions', function (): void {
        expect($this->mapper->fromString('Arrived at hub'))
            ->toBe(TrackingStatus::AtHub);

        expect($this->mapper->fromString('Sorting'))
            ->toBe(TrackingStatus::AtHub);
    });

    it('maps out for delivery status descriptions', function (): void {
        expect($this->mapper->fromString('Out for delivery'))
            ->toBe(TrackingStatus::OutForDelivery);

        expect($this->mapper->fromString('With courier'))
            ->toBe(TrackingStatus::OutForDelivery);
    });

    it('maps delivered status descriptions', function (): void {
        expect($this->mapper->fromString('Delivered'))
            ->toBe(TrackingStatus::Delivered);

        expect($this->mapper->fromString('Signed by customer'))
            ->toBe(TrackingStatus::Delivered);
    });

    it('maps exception status descriptions', function (): void {
        expect($this->mapper->fromString('Exception occurred'))
            ->toBe(TrackingStatus::Exception);

        expect($this->mapper->fromString('Package damaged'))
            ->toBe(TrackingStatus::Exception);
    });

    it('returns Pending for unknown descriptions', function (): void {
        expect($this->mapper->fromString('Unknown status xyz'))
            ->toBe(TrackingStatus::Pending);
    });
});

describe('resolve method', function (): void {
    it('prioritizes scan type code when both provided', function (): void {
        $status = $this->mapper->resolve(
            scanTypeCode: ScanTypeCode::PARCEL_SIGNED->value,
            statusDescription: 'Pending'
        );

        expect($status)->toBe(TrackingStatus::Delivered);
    });

    it('falls back to description when code is null', function (): void {
        $status = $this->mapper->resolve(
            scanTypeCode: null,
            statusDescription: 'Delivered'
        );

        expect($status)->toBe(TrackingStatus::Delivered);
    });

    it('falls back to description when code is invalid', function (): void {
        $status = $this->mapper->resolve(
            scanTypeCode: 'INVALID',
            statusDescription: 'Out for delivery'
        );

        expect($status)->toBe(TrackingStatus::OutForDelivery);
    });

    it('returns Pending when no input provided', function (): void {
        $status = $this->mapper->resolve();

        expect($status)->toBe(TrackingStatus::Pending);
    });
});
