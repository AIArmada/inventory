<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a parcel is delivered.
 */
class ParcelDelivered
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Model $shipment,
        public readonly array $payload = []
    ) {}

    public function getShipmentId(): string
    {
        return $this->shipment->id;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->shipment->tracking_number;
    }
}
