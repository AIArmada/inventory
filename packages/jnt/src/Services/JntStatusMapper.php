<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Services;

use AIArmada\Jnt\Enums\ScanTypeCode;
use AIArmada\Jnt\Enums\TrackingStatus;

/**
 * Maps J&T Express scan type codes to normalized tracking statuses
 */
class JntStatusMapper
{
    /**
     * Map ScanTypeCode enum to TrackingStatus
     */
    public function fromScanType(ScanTypeCode $scanType): TrackingStatus
    {
        return match ($scanType) {
            // Pickup
            ScanTypeCode::PARCEL_PICKUP => TrackingStatus::PickedUp,
            ScanTypeCode::PICKED_UP_FROM_CARGO => TrackingStatus::PickedUp,

            // Hub/Facility
            ScanTypeCode::PACKAGE_INBOUND => TrackingStatus::AtHub,
            ScanTypeCode::CENTER_INBOUND => TrackingStatus::AtHub,
            ScanTypeCode::DELIVERED_TO_HUB => TrackingStatus::AtHub,
            ScanTypeCode::ARRIVAL => TrackingStatus::AtHub,

            // In Transit
            ScanTypeCode::OUTBOUND_SCAN => TrackingStatus::InTransit,
            ScanTypeCode::CUSTOMS_CLEARANCE_IN_PROCESS => TrackingStatus::InTransit,
            ScanTypeCode::CUSTOMS_CLEARANCE => TrackingStatus::InTransit,

            // Out for Delivery
            ScanTypeCode::DELIVERY_SCAN => TrackingStatus::OutForDelivery,

            // Delivered
            ScanTypeCode::PARCEL_SIGNED => TrackingStatus::Delivered,
            ScanTypeCode::COLLECTED => TrackingStatus::Delivered,
            ScanTypeCode::COLLECTED_ALT => TrackingStatus::Delivered,

            // Returns
            ScanTypeCode::RETURN_SCAN => TrackingStatus::ReturnInitiated,
            ScanTypeCode::RETURN_SIGN => TrackingStatus::Returned,

            // Exceptions/Problems
            ScanTypeCode::PROBLEMATIC_SCANNING => TrackingStatus::Exception,
            ScanTypeCode::DAMAGE_PARCEL => TrackingStatus::Exception,
            ScanTypeCode::LOST_PARCEL => TrackingStatus::Exception,
            ScanTypeCode::DISPOSE_PARCEL => TrackingStatus::Exception,
            ScanTypeCode::REJECT_PARCEL => TrackingStatus::Exception,
            ScanTypeCode::CUSTOMS_CONFISCATED => TrackingStatus::Exception,
            ScanTypeCode::EXCEED_LIFE_CYCLE => TrackingStatus::Exception,
            ScanTypeCode::CROSSBORDER_DISPOSE => TrackingStatus::Exception,
        };
    }

    /**
     * Map scan type code string to TrackingStatus
     */
    public function fromCode(string $scanTypeCode): TrackingStatus
    {
        $scanType = ScanTypeCode::tryFrom($scanTypeCode);

        if ($scanType === null) {
            return TrackingStatus::Exception;
        }

        return $this->fromScanType($scanType);
    }

    /**
     * Map a raw status string (from API descriptions) to TrackingStatus
     *
     * @param  string  $statusString  Status description from API
     */
    public function fromString(string $statusString): TrackingStatus
    {
        $normalized = strtoupper(trim($statusString));

        return match (true) {
            str_contains($normalized, 'PENDING') => TrackingStatus::Pending,
            str_contains($normalized, 'PICKED UP'),
            str_contains($normalized, 'PICKUP'),
            str_contains($normalized, 'COLLECTED FROM SENDER') => TrackingStatus::PickedUp,

            str_contains($normalized, 'DEPARTED'),
            str_contains($normalized, 'OUTBOUND'),
            str_contains($normalized, 'IN TRANSIT'),
            str_contains($normalized, 'ON THE WAY'),
            str_contains($normalized, 'SHIPPED') => TrackingStatus::InTransit,

            str_contains($normalized, 'ARRIVED'),
            str_contains($normalized, 'INBOUND'),
            str_contains($normalized, 'AT FACILITY'),
            str_contains($normalized, 'AT HUB'),
            str_contains($normalized, 'SORTING') => TrackingStatus::AtHub,

            str_contains($normalized, 'OUT FOR DELIVERY'),
            str_contains($normalized, 'DELIVERY SCAN'),
            str_contains($normalized, 'WITH COURIER') => TrackingStatus::OutForDelivery,

            str_contains($normalized, 'DELIVERY ATTEMPTED'),
            str_contains($normalized, 'ATTEMPT'),
            str_contains($normalized, 'FAILED DELIVERY') => TrackingStatus::DeliveryAttempted,

            str_contains($normalized, 'DELIVERED'),
            str_contains($normalized, 'SIGNED'),
            str_contains($normalized, 'RECEIVED BY') => TrackingStatus::Delivered,

            str_contains($normalized, 'RETURN'),
            str_contains($normalized, 'RETURNING') => TrackingStatus::ReturnInitiated,

            str_contains($normalized, 'RETURNED'),
            str_contains($normalized, 'RETURN COMPLETED') => TrackingStatus::Returned,

            str_contains($normalized, 'EXCEPTION'),
            str_contains($normalized, 'PROBLEM'),
            str_contains($normalized, 'DAMAGE'),
            str_contains($normalized, 'LOST'),
            str_contains($normalized, 'REJECTED'),
            str_contains($normalized, 'DISPOSED'),
            str_contains($normalized, 'CONFISCATED') => TrackingStatus::Exception,

            default => TrackingStatus::Pending,
        };
    }

    /**
     * Get the best TrackingStatus from multiple possible inputs
     */
    public function resolve(?string $scanTypeCode = null, ?string $statusDescription = null): TrackingStatus
    {
        if ($scanTypeCode !== null) {
            $scanType = ScanTypeCode::tryFrom($scanTypeCode);

            if ($scanType !== null) {
                return $this->fromScanType($scanType);
            }
        }

        if ($statusDescription !== null) {
            return $this->fromString($statusDescription);
        }

        return TrackingStatus::Pending;
    }
}
