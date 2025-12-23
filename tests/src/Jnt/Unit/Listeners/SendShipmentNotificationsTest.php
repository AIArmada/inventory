<?php

declare(strict_types=1);

use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Listeners\SendShipmentNotifications;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntTrackingService;
use Illuminate\Support\Facades\Notification;

describe('SendShipmentNotifications listener', function (): void {
    beforeEach(function (): void {
        config(['jnt.notifications.enabled' => true]);
        config(['jnt.notifications.queue' => false]);
    });

    it('can be instantiated', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications($trackingService);

        expect($listener)->toBeInstanceOf(SendShipmentNotifications::class);
    });

    it('should queue based on config', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications($trackingService);

        $order = new JntOrder;
        $order->forceFill(['id' => 'test-id', 'order_id' => 'ORDER123']);

        $event = JntOrderStatusChanged::fromOrder($order, TrackingStatus::Delivered);

        config(['jnt.notifications.queue' => true]);
        expect($listener->shouldQueue($event))->toBeTrue();

        config(['jnt.notifications.queue' => false]);
        expect($listener->shouldQueue($event))->toBeFalse();
    });

    it('skips processing when notifications disabled', function (): void {
        Notification::fake();
        config(['jnt.notifications.enabled' => false]);

        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications($trackingService);

        $order = new JntOrder;
        $order->forceFill(['id' => 'test-id', 'order_id' => 'ORDER123']);

        $event = JntOrderStatusChanged::fromOrder($order, TrackingStatus::Delivered);

        $listener->handle($event);

        Notification::assertNothingSent();
    });

    it('skips processing when notifiable cannot be resolved', function (): void {
        Notification::fake();

        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications($trackingService);

        $order = JntOrder::query()->create([
            'order_id' => 'ORDER123',
            'customer_code' => 'CUST',
            'metadata' => [],
        ]);

        $event = JntOrderStatusChanged::fromOrder($order, TrackingStatus::Delivered);

        $listener->handle($event);

        Notification::assertNothingSent();
    });

    it('resolves notifiable from metadata email', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications($trackingService);

        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('resolveNotifiable');
        $method->setAccessible(true);

        $order = new JntOrder;
        $order->forceFill([
            'id' => 'test-id',
            'order_id' => 'ORDER123',
            'metadata' => ['notification_email' => 'test@example.com'],
        ]);

        $notifiable = $method->invoke($listener, $order);

        expect($notifiable)->not->toBeNull();
        expect($notifiable->email)->toBe('test@example.com');
        expect($notifiable->routeNotificationForMail())->toBe(['mail' => 'test@example.com']);
    });

    it('returns null notification when tracking fails', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $trackingService->shouldReceive('track')
            ->andThrow(new Exception('Not found'));

        $listener = new SendShipmentNotifications($trackingService);

        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('createNotification');
        $method->setAccessible(true);

        $order = JntOrder::query()->create([
            'order_id' => 'ORDER123',
            'customer_code' => 'CUST',
            'tracking_number' => 'JNT123',
        ]);

        $event = JntOrderStatusChanged::fromOrder($order, TrackingStatus::PickedUp);

        $notification = $method->invoke($listener, $event, $order);

        // Since tracking fails, notification will be null
        expect($notification)->toBeNull();
    });

    it('calculates estimated delivery from config', function (): void {
        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications($trackingService);

        $reflection = new ReflectionClass($listener);
        $method = $reflection->getMethod('getEstimatedDelivery');
        $method->setAccessible(true);

        config(['jnt.shipping.default_estimated_days' => 5]);

        $order = new JntOrder;
        $order->forceFill(['id' => 'test-id']);

        $result = $method->invoke($listener, $order);

        expect($result)->toBe(now()->addDays(5)->format('Y-m-d'));
    });
});
