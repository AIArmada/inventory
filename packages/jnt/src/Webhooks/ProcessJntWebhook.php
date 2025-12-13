<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\ParcelDelivered;
use AIArmada\Jnt\Events\ParcelInTransit;
use AIArmada\Jnt\Events\ParcelOutForDelivery;
use AIArmada\Jnt\Events\ParcelPickedUp;
use AIArmada\Jnt\Events\TrackingUpdated;
use AIArmada\Jnt\Models\JntOrder;
use Illuminate\Support\Facades\Log;

/**
 * Process J&T Express webhook events.
 *
 * This job handles incoming J&T tracking updates and dispatches events.
 */
class ProcessJntWebhook extends CommerceWebhookProcessor
{
    /**
     * Extract the event type from J&T payload.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventType(array $payload): string
    {
        // J&T uses different field names
        return $payload['scantype'] ?? $payload['event'] ?? $payload['type'] ?? 'tracking.update';
    }

    /**
     * Process the webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $billcode = $payload['billcode'] ?? $payload['waybill'] ?? null;

        if (empty($billcode)) {
            Log::channel(config('jnt.logging.channel', 'stack'))
                ->warning('J&T webhook missing billcode', [
                    'payload' => $payload,
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

            return;
        }

        // Find the shipment
        $shipment = JntOrder::where('tracking_number', $billcode)->first();

        if (! $shipment) {
            Log::channel(config('jnt.logging.channel', 'stack'))
                ->info('J&T webhook for unknown shipment', [
                    'billcode' => $billcode,
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

            // Still dispatch generic tracking event
            TrackingUpdated::dispatch($billcode, $eventType, $payload);

            return;
        }

        // Update shipment status
        $newStatus = $this->mapToStatus($eventType, $payload);

        if ($newStatus) {
            $oldStatus = $shipment->status;
            $shipment->update(['status' => $newStatus->value]);

            // Dispatch specific events based on status
            $this->dispatchStatusEvent($shipment, $newStatus, $payload);
        }

        // Always dispatch generic tracking event
        TrackingUpdated::dispatch($billcode, $eventType, $payload);
    }

    /**
     * Map J&T scan type to TrackingStatus.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function mapToStatus(string $scanType, array $payload): ?TrackingStatus
    {
        // J&T scan types mapping
        return match (mb_strtoupper($scanType)) {
            'PICKUP', 'COLLECTED' => TrackingStatus::PickedUp,
            'IN_TRANSIT', 'TRANSIT', 'ARRIVED', 'DEPARTED' => TrackingStatus::InTransit,
            'OUT_FOR_DELIVERY', 'DELIVERING' => TrackingStatus::OutForDelivery,
            'DELIVERED', 'POD' => TrackingStatus::Delivered,
            'FAILED', 'UNDELIVERED' => TrackingStatus::Exception,
            'RETURNED', 'RTS' => TrackingStatus::Returned,
            default => null,
        };
    }

    /**
     * Dispatch status-specific events.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function dispatchStatusEvent(JntOrder $shipment, TrackingStatus $status, array $payload): void
    {
        match ($status) {
            TrackingStatus::PickedUp => ParcelPickedUp::dispatch($shipment, $payload),
            TrackingStatus::InTransit => ParcelInTransit::dispatch($shipment, $payload),
            TrackingStatus::OutForDelivery => ParcelOutForDelivery::dispatch($shipment, $payload),
            TrackingStatus::Delivered => ParcelDelivered::dispatch($shipment, $payload),
            default => null,
        };
    }
}
