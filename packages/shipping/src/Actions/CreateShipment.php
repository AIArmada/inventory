<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Actions;

use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a new shipment.
 */
final class CreateShipment
{
    use AsAction;

    /**
     * Create a new shipment.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Shipment
    {
        return DB::transaction(function () use ($data): Shipment {
            $status = $data['status'] ?? ShipmentStatus::Draft;

            if (is_string($status)) {
                $status = ShipmentStatus::tryFrom($status) ?? ShipmentStatus::Draft;
            }

            $shipment = Shipment::create([
                'reference' => $data['reference'] ?? $this->generateReference(),
                'status' => $status,
                'carrier_code' => $data['carrier_code'] ?? $data['carrier'] ?? '',
                'service_code' => $data['service_code'] ?? $data['service_type'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'origin_address' => $data['origin_address'],
                'destination_address' => $data['destination_address'],
                'total_weight' => $data['weight'] ?? $data['total_weight'] ?? 0,
                'declared_value' => $data['declared_value'] ?? 0,
                'shipping_cost' => $data['rate_minor'] ?? $data['shipping_cost'] ?? 0,
                'currency' => $data['currency'] ?? config('shipping.defaults.currency', 'MYR'),
                'estimated_delivery_at' => $data['estimated_delivery_at'] ?? null,
                'shipped_at' => $data['shipped_at'] ?? null,
                'delivered_at' => $data['delivered_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'shippable_type' => $data['shippable_type'] ?? null,
                'shippable_id' => $data['shippable_id'] ?? null,
                'owner_type' => $data['owner_type'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
            ]);

            return $shipment;
        });
    }

    private function generateReference(): string
    {
        $prefix = config('shipping.defaults.reference_prefix', config('shipping.reference_prefix', 'SHP-'));

        return $prefix . Str::upper(Str::random(10));
    }
}
