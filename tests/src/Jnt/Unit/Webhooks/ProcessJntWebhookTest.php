<?php

declare(strict_types=1);

use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\ParcelDelivered;
use AIArmada\Jnt\Events\ParcelInTransit;
use AIArmada\Jnt\Events\ParcelOutForDelivery;
use AIArmada\Jnt\Events\ParcelPickedUp;
use AIArmada\Jnt\Events\TrackingUpdated;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Webhooks\ProcessJntWebhook;
use Illuminate\Support\Facades\Event;
use Spatie\WebhookClient\Models\WebhookCall;

describe('ProcessJntWebhook', function (): void {
    it('extracts event type from latest detail scanType when bizContent is present', function (): void {
        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('extractEventType');
        $method->setAccessible(true);

        $payload = [
            'bizContent' => json_encode([
                'billCode' => 'JNTMY123',
                'details' => [
                    ['scanType' => 'PICKUP'],
                ],
            ]),
        ];

        expect($method->invoke($processor, $payload))->toBe('PICKUP');
    });

    it('falls back to scantype/event/type fields when bizContent is missing', function (): void {
        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('extractEventType');
        $method->setAccessible(true);

        expect($method->invoke($processor, ['scantype' => 'PICKUP']))->toBe('PICKUP');
        expect($method->invoke($processor, ['event' => 'tracking.update']))->toBe('tracking.update');
        expect($method->invoke($processor, ['type' => 'DELIVERY']))->toBe('DELIVERY');
        expect($method->invoke($processor, []))->toBe('tracking.update');
    });

    it('maps scan types to tracking statuses', function (): void {
        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('mapToStatus');
        $method->setAccessible(true);

        expect($method->invoke($processor, 'PICKUP', []))->toBe(TrackingStatus::PickedUp);
        expect($method->invoke($processor, 'COLLECTED', []))->toBe(TrackingStatus::PickedUp);
        expect($method->invoke($processor, 'IN_TRANSIT', []))->toBe(TrackingStatus::InTransit);
        expect($method->invoke($processor, 'TRANSIT', []))->toBe(TrackingStatus::InTransit);
        expect($method->invoke($processor, 'ARRIVED', []))->toBe(TrackingStatus::InTransit);
        expect($method->invoke($processor, 'DEPARTED', []))->toBe(TrackingStatus::InTransit);
        expect($method->invoke($processor, 'OUT_FOR_DELIVERY', []))->toBe(TrackingStatus::OutForDelivery);
        expect($method->invoke($processor, 'DELIVERING', []))->toBe(TrackingStatus::OutForDelivery);
        expect($method->invoke($processor, 'DELIVERED', []))->toBe(TrackingStatus::Delivered);
        expect($method->invoke($processor, 'POD', []))->toBe(TrackingStatus::Delivered);
        expect($method->invoke($processor, 'FAILED', []))->toBe(TrackingStatus::Exception);
        expect($method->invoke($processor, 'UNDELIVERED', []))->toBe(TrackingStatus::Exception);
        expect($method->invoke($processor, 'RETURNED', []))->toBe(TrackingStatus::Returned);
        expect($method->invoke($processor, 'RTS', []))->toBe(TrackingStatus::Returned);
        expect($method->invoke($processor, 'UNKNOWN', []))->toBeNull();
    });

    it('dispatches PickedUp event for pickup status', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id', 'status' => 'pending']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');
        $method->setAccessible(true);

        $method->invoke($processor, $shipment, TrackingStatus::PickedUp, ['test' => 'data']);

        Event::assertDispatched(ParcelPickedUp::class);
    });

    it('dispatches InTransit event for transit status', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');
        $method->setAccessible(true);

        $method->invoke($processor, $shipment, TrackingStatus::InTransit, []);

        Event::assertDispatched(ParcelInTransit::class);
    });

    it('dispatches OutForDelivery event', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');
        $method->setAccessible(true);

        $method->invoke($processor, $shipment, TrackingStatus::OutForDelivery, []);

        Event::assertDispatched(ParcelOutForDelivery::class);
    });

    it('dispatches Delivered event', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');
        $method->setAccessible(true);

        $method->invoke($processor, $shipment, TrackingStatus::Delivered, []);

        Event::assertDispatched(ParcelDelivered::class);
    });

    it('does not dispatch event for unhandled status', function (): void {
        Event::fake();

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();

        $shipment = new JntOrder;
        $shipment->forceFill(['id' => 'test-id']);

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('dispatchStatusEvent');
        $method->setAccessible(true);

        $method->invoke($processor, $shipment, TrackingStatus::Exception, []);

        Event::assertNotDispatched(ParcelPickedUp::class);
        Event::assertNotDispatched(ParcelInTransit::class);
        Event::assertNotDispatched(ParcelOutForDelivery::class);
        Event::assertNotDispatched(ParcelDelivered::class);
    });

    it('dispatches TrackingUpdated when shipment not found', function (): void {
        Event::fake();
        config(['jnt.logging.channel' => 'stack']);

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();
        $webhookCall->payload = [
            'bizContent' => json_encode([
                'billCode' => 'UNKNOWN123',
                'details' => [],
            ]),
        ];

        $processor = new ProcessJntWebhook($webhookCall);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('processEvent');
        $method->setAccessible(true);

        // Since there's no shipment with this billCode, it should dispatch TrackingUpdated
        $method->invoke($processor, 'TRANSIT', $webhookCall->payload);

        Event::assertDispatched(TrackingUpdated::class);
    });
});
