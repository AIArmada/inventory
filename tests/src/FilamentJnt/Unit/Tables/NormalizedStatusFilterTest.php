<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentJnt\FilamentJntTestCase;
use AIArmada\FilamentJnt\Resources\JntOrderResource\Tables\JntOrderTable;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Tables\JntTrackingEventTable;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;

uses(FilamentJntTestCase::class);

it('ignores invalid normalized_status values for the orders table filter', function (): void {
    JntOrder::query()->create([
        'order_id' => 'ORD-FILTER-1',
        'customer_code' => 'CUST',
        'tracking_number' => null,
        'delivered_at' => null,
        'has_problem' => false,
    ]);

    JntOrder::query()->create([
        'order_id' => 'ORD-FILTER-2',
        'customer_code' => 'CUST',
        'tracking_number' => 'TRK-FILTER-2',
        'delivered_at' => now(),
        'has_problem' => false,
    ]);

    $query = JntOrderTable::applyNormalizedStatusFilter(JntOrder::query(), 'not-a-real-status');

    expect($query->count())->toBe(2);
});

it('ignores invalid normalized_status values for the tracking events table filter', function (): void {
    $order = JntOrder::query()->create([
        'order_id' => 'ORD-FILTER-3',
        'customer_code' => 'CUST',
    ]);

    JntTrackingEvent::query()->create([
        'order_id' => $order->id,
        'tracking_number' => 'TRK-FILTER-3',
        'scan_type_code' => '100',
        'scan_type_name' => 'Delivered',
        'scan_time' => now(),
    ]);

    JntTrackingEvent::query()->create([
        'order_id' => $order->id,
        'tracking_number' => 'TRK-FILTER-3',
        'scan_type_code' => '20',
        'scan_type_name' => 'In Transit',
        'scan_time' => now(),
    ]);

    $query = JntTrackingEventTable::applyNormalizedStatusFilter(JntTrackingEvent::query(), 'not-a-real-status');

    expect($query->count())->toBe(2);
});
