<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Actions;

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Exceptions\InvalidStatusTransitionException;
use AIArmada\Shipping\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Update the status of a shipment.
 */
final class UpdateShipmentStatus
{
    use AsAction;

    /**
     * Update the status of a shipment.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Shipment $shipment,
        ShipmentStatus $status,
        ?string $description = null,
        ?string $location = null,
        array $metadata = []
    ): Shipment {
        return DB::transaction(function () use ($shipment, $status, $description, $location, $metadata): Shipment {
            $previousStatus = $shipment->status;

            if (! $previousStatus->canTransitionTo($status)) {
                throw new InvalidStatusTransitionException($previousStatus, $status);
            }

            $shipment->status = $status;

            // Update timestamp fields based on status
            if ($status === ShipmentStatus::Shipped && $shipment->shipped_at === null) {
                $shipment->shipped_at = now();
            }

            if ($status === ShipmentStatus::Delivered && $shipment->delivered_at === null) {
                $shipment->delivered_at = now();
            }

            $shipment->save();

            // Create event record
            $shipment->events()->create([
                'normalized_status' => $status->toTrackingStatus(),
                'description' => $description,
                'location' => $location,
                'raw_data' => $metadata ?: null,
                'occurred_at' => now(),
            ]);

            return $shipment->refresh();
        });
    }
}
