<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentJnt\Resources\JntOrderResource;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Models\JntWebhookLog;

uses(FilamentJntTestCase::class);

it('scopes Filament JNT resources to the resolved owner (including global)', function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner(null, function () use ($ownerA, $ownerB, &$globalOrder, &$globalLog, &$globalEvent, &$ownerAOrder, &$ownerBOrder, &$ownerALog, &$ownerBLog, &$ownerAEvent, &$ownerBEvent): void {
        $globalOrder = JntOrder::query()->create([
            'order_id' => 'ORD-GLOBAL',
            'customer_code' => 'CUST',
        ]);

        $globalLog = JntWebhookLog::query()->create([
            'order_id' => $globalOrder->id,
            'tracking_number' => 'TRK-G',
            'processing_status' => JntWebhookLog::STATUS_PENDING,
        ]);

        $globalEvent = JntTrackingEvent::query()->create([
            'order_id' => $globalOrder->id,
            'tracking_number' => 'TRK-G',
            'scan_type_name' => 'Global',
        ]);

        $ownerAOrder = JntOrder::query()->create([
            'order_id' => 'ORD-A',
            'customer_code' => 'CUST',
        ]);
        $ownerAOrder->assignOwner($ownerA)->save();

        $ownerBOrder = JntOrder::query()->create([
            'order_id' => 'ORD-B',
            'customer_code' => 'CUST',
        ]);
        $ownerBOrder->assignOwner($ownerB)->save();

        $ownerALog = JntWebhookLog::query()->create([
            'order_id' => $ownerAOrder->id,
            'tracking_number' => 'TRK-A',
            'processing_status' => JntWebhookLog::STATUS_PENDING,
        ]);

        $ownerBLog = JntWebhookLog::query()->create([
            'order_id' => $ownerBOrder->id,
            'tracking_number' => 'TRK-B',
            'processing_status' => JntWebhookLog::STATUS_PENDING,
        ]);

        $ownerAEvent = JntTrackingEvent::query()->create([
            'order_id' => $ownerAOrder->id,
            'tracking_number' => 'TRK-A',
            'scan_type_name' => 'Owner A',
        ]);

        $ownerBEvent = JntTrackingEvent::query()->create([
            'order_id' => $ownerBOrder->id,
            'tracking_number' => 'TRK-B',
            'scan_type_name' => 'Owner B',
        ]);
    });

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $orders = JntOrderResource::getEloquentQuery()->pluck('id')->all();
    expect($orders)->toContain($globalOrder->id, $ownerAOrder->id)
        ->not->toContain($ownerBOrder->id);

    $webhookLogs = JntWebhookLogResource::getEloquentQuery()->pluck('id')->all();
    expect($webhookLogs)->toContain($globalLog->id, $ownerALog->id)
        ->not->toContain($ownerBLog->id);

    $trackingEvents = JntTrackingEventResource::getEloquentQuery()->pluck('id')->all();
    expect($trackingEvents)->toContain($globalEvent->id, $ownerAEvent->id)
        ->not->toContain($ownerBEvent->id);
});

it('can exclude global records from Filament JNT resources', function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner(null, function () use ($ownerA, &$globalOrder, &$globalLog, &$globalEvent, &$ownerAOrder, &$ownerALog, &$ownerAEvent): void {
        $globalOrder = JntOrder::query()->create([
            'order_id' => 'ORD-GLOBAL',
            'customer_code' => 'CUST',
        ]);

        $globalLog = JntWebhookLog::query()->create([
            'order_id' => $globalOrder->id,
            'tracking_number' => 'TRK-G',
            'processing_status' => JntWebhookLog::STATUS_PENDING,
        ]);

        $globalEvent = JntTrackingEvent::query()->create([
            'order_id' => $globalOrder->id,
            'tracking_number' => 'TRK-G',
            'scan_type_name' => 'Global',
        ]);

        $ownerAOrder = JntOrder::query()->create([
            'order_id' => 'ORD-A',
            'customer_code' => 'CUST',
        ]);
        $ownerAOrder->assignOwner($ownerA)->save();

        $ownerALog = JntWebhookLog::query()->create([
            'order_id' => $ownerAOrder->id,
            'tracking_number' => 'TRK-A',
            'processing_status' => JntWebhookLog::STATUS_PENDING,
        ]);

        $ownerAEvent = JntTrackingEvent::query()->create([
            'order_id' => $ownerAOrder->id,
            'tracking_number' => 'TRK-A',
            'scan_type_name' => 'Owner A',
        ]);
    });

    app()->bind(OwnerResolverInterface::class, fn (): OwnerResolverInterface => new FixedOwnerResolver($ownerA));

    $orders = JntOrderResource::getEloquentQuery()->pluck('id')->all();
    expect($orders)->toContain($ownerAOrder->id)
        ->not->toContain($globalOrder->id);

    $webhookLogs = JntWebhookLogResource::getEloquentQuery()->pluck('id')->all();
    expect($webhookLogs)->toContain($ownerALog->id)
        ->not->toContain($globalLog->id);

    $trackingEvents = JntTrackingEventResource::getEloquentQuery()->pluck('id')->all();
    expect($trackingEvents)->toContain($ownerAEvent->id)
        ->not->toContain($globalEvent->id);
});
