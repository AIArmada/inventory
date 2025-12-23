<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Services;

use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Events\ShipmentCancelled;
use AIArmada\Shipping\Events\ShipmentCreated;
use AIArmada\Shipping\Events\ShipmentDelivered;
use AIArmada\Shipping\Events\ShipmentShipped;
use AIArmada\Shipping\Events\ShipmentStatusChanged;
use AIArmada\Shipping\Exceptions\InvalidStatusTransitionException;
use AIArmada\Shipping\Exceptions\ShipmentAlreadyShippedException;
use AIArmada\Shipping\Exceptions\ShipmentCreationFailedException;
use AIArmada\Shipping\Exceptions\ShipmentNotCancellableException;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentEvent;
use AIArmada\Shipping\Models\ShipmentLabel;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\Support\ShippingOwnerScope;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

/**
 * Manages shipment lifecycle operations.
 */
class ShipmentService
{
    public function __construct(
        protected readonly ShippingManager $shippingManager,
        protected readonly ?RetryService $retryService = null
    ) {}

    /**
     * Create a new shipment.
     */
    public function create(ShipmentData $data, ?string $ownerId = null, ?string $ownerType = null): Shipment
    {
        if (ShippingOwnerScope::isEnabled()) {
            $owner = ShippingOwnerScope::resolveOwner();

            if ($owner === null) {
                throw new AuthorizationException('Owner context is required when shipping owner scoping is enabled.');
            }

            if (($ownerId !== null || $ownerType !== null)
                && ($ownerId !== $owner->getKey() || $ownerType !== $owner->getMorphClass())) {
                throw new AuthorizationException('Cannot create shipment outside the current owner context.');
            }

            $ownerId = (string) $owner->getKey();
            $ownerType = $owner->getMorphClass();
        }

        $shipment = Shipment::create([
            'owner_id' => $ownerId,
            'owner_type' => $ownerType,
            'reference' => $data->reference,
            'carrier_code' => $data->carrierCode,
            'service_code' => $data->serviceCode,
            'status' => ShipmentStatus::Draft,
            'origin_address' => $data->origin->toArray(),
            'destination_address' => $data->destination->toArray(),
            'total_weight' => $data->getTotalWeight(),
            'declared_value' => $data->declaredValue ?? 0,
            'currency' => $data->currency ?? 'MYR',
            'cod_amount' => $data->codAmount,
            'metadata' => $data->metadata,
        ]);

        // Create items
        foreach ($data->items as $item) {
            $shipment->items()->create([
                'sku' => $item->sku,
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'weight' => $item->weight ?? 0,
                'declared_value' => $item->declaredValue ?? 0,
                'hs_code' => $item->hsCode,
                'origin_country' => $item->originCountry,
                'shippable_item_id' => $item->shippableItemId,
                'shippable_item_type' => $item->shippableItemType,
            ]);
        }

        // Recalculate weight from items
        $this->recalculateWeight($shipment);

        event(new ShipmentCreated($shipment));

        return $shipment->refresh();
    }

    /**
     * Ship the shipment (submit to carrier).
     */
    public function ship(Shipment $shipment): Shipment
    {
        if ($shipment->status !== ShipmentStatus::Pending) {
            throw new ShipmentAlreadyShippedException($shipment);
        }

        $driver = $this->shippingManager->driver($shipment->carrier_code);

        // Use retry service for carrier API call
        $result = $this->retry()
            ->attempts(3)
            ->delay(200)
            ->backoff(2.0)
            ->execute(
                fn () => $driver->createShipment(
                    ShipmentData::from([
                        'reference' => $shipment->reference,
                        'carrierCode' => $shipment->carrier_code,
                        'serviceCode' => $shipment->service_code ?? 'standard',
                        'origin' => $shipment->origin_address,
                        'destination' => $shipment->destination_address,
                        'items' => $shipment->items->map(fn ($item) => [
                            'name' => $item->name,
                            'quantity' => $item->quantity,
                            'sku' => $item->sku,
                            'weight' => $item->weight,
                            'declaredValue' => $item->declared_value,
                        ])->toArray(),
                        'declaredValue' => $shipment->declared_value,
                        'currency' => $shipment->currency,
                        'codAmount' => $shipment->cod_amount,
                    ])
                ),
                context: "ship:{$shipment->id}"
            );

        if (! $result->isSuccessful()) {
            throw new ShipmentCreationFailedException($result->error ?? 'Unknown error');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($shipment, $result, $driver) {
            $shipment->update([
                'tracking_number' => $result->trackingNumber,
                'carrier_reference' => $result->carrierReference,
                'status' => ShipmentStatus::Shipped,
                'shipped_at' => now(),
                'label_url' => $result->labelUrl,
            ]);

            // Generate label if supported and not already provided
            if ($result->labelUrl === null && $driver->supports(DriverCapability::LabelGeneration)) {
                $this->generateLabel($shipment);
            }

            $this->recordEvent($shipment, 'shipped', 'Shipment created with carrier');
            event(new ShipmentShipped($shipment));

            return $shipment->refresh();
        });
    }

    /**
     * Update shipment status.
     */
    public function updateStatus(
        Shipment $shipment,
        ShipmentStatus $newStatus,
        ?string $note = null,
        ?array $eventData = null
    ): Shipment {
        $oldStatus = $shipment->status;

        if (! $oldStatus->canTransitionTo($newStatus)) {
            throw new InvalidStatusTransitionException($oldStatus, $newStatus);
        }

        $shipment->update(['status' => $newStatus]);

        $this->recordEvent($shipment, 'status_changed', $note, [
            'from_status' => $oldStatus->value,
            'to_status' => $newStatus->value,
            ...($eventData ?? []),
        ]);

        event(new ShipmentStatusChanged($shipment, $oldStatus, $newStatus));

        if ($newStatus === ShipmentStatus::Delivered) {
            $shipment->update(['delivered_at' => now()]);
            event(new ShipmentDelivered($shipment));
        }

        return $shipment->refresh();
    }

    /**
     * Cancel a shipment.
     */
    public function cancel(Shipment $shipment, ?string $reason = null): Shipment
    {
        if (! $shipment->isCancellable()) {
            throw new ShipmentNotCancellableException($shipment);
        }

        // If already submitted to carrier, cancel there too
        if ($shipment->tracking_number !== null) {
            $driver = $this->shippingManager->driver($shipment->carrier_code);
            $driver->cancelShipment($shipment->tracking_number);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($shipment, $reason) {
            $oldStatus = $shipment->status;
            $shipment->update(['status' => ShipmentStatus::Cancelled]);

            $this->recordEvent($shipment, 'cancelled', $reason);
            event(new ShipmentCancelled($shipment, $reason));
            event(new ShipmentStatusChanged($shipment, $oldStatus, ShipmentStatus::Cancelled));

            return $shipment->refresh();
        });
    }

    /**
     * Generate shipping label.
     *
     * @param  array<string, mixed>  $options
     */
    public function generateLabel(Shipment $shipment, array $options = []): ShipmentLabel
    {
        if ($shipment->tracking_number === null) {
            throw new RuntimeException('Cannot generate label for shipment without tracking number');
        }

        $driver = $this->shippingManager->driver($shipment->carrier_code);

        $labelData = $driver->generateLabel($shipment->tracking_number, $options);

        $label = $shipment->labels()->create([
            'format' => $labelData->format,
            'size' => $labelData->size,
            'url' => $labelData->url,
            'content' => $labelData->content,
            'generated_at' => now(),
        ]);

        if ($labelData->url !== null) {
            $shipment->update([
                'label_url' => $labelData->url,
                'label_format' => $labelData->format,
            ]);
        }

        return $label;
    }

    /**
     * Mark shipment as pending (ready for shipping).
     */
    public function markPending(Shipment $shipment): Shipment
    {
        if ($shipment->status !== ShipmentStatus::Draft) {
            throw new InvalidStatusTransitionException($shipment->status, ShipmentStatus::Pending);
        }

        return $this->updateStatus($shipment, ShipmentStatus::Pending, 'Shipment marked as pending');
    }

    /**
     * Recalculate shipment weight from items.
     */
    public function recalculateWeight(Shipment $shipment): Shipment
    {
        $totalWeight = $shipment->items->sum(fn ($item) => $item->weight * $item->quantity);

        $shipment->update(['total_weight' => $totalWeight]);

        return $shipment->refresh();
    }

    /**
     * Get the retry service instance.
     */
    protected function retry(): RetryService
    {
        return $this->retryService ?? RetryService::make();
    }

    /**
     * Record a shipment event.
     *
     * @param  array<string, mixed>  $data
     */
    protected function recordEvent(
        Shipment $shipment,
        string $type,
        ?string $note = null,
        array $data = []
    ): ShipmentEvent {
        return $shipment->events()->create([
            'carrier_event_code' => $type,
            'normalized_status' => $shipment->status->toTrackingStatus(),
            'description' => $note,
            'raw_data' => $data,
            'occurred_at' => now(),
        ]);
    }
}
