<?php

declare(strict_types=1);

use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntOrderItem;
use AIArmada\Jnt\Models\JntOrderParcel;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Models\JntWebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['jnt.database.table_prefix' => 'jnt_']);
});

describe('JntOrder model', function (): void {
    it('gets table name from config', function (): void {
        $order = new JntOrder;
        expect($order->getTable())->toBe('jnt_orders');
    });

    it('gets table name from custom tables config', function (): void {
        config(['jnt.database.tables.orders' => 'custom_jnt_orders']);
        $order = new JntOrder;
        expect($order->getTable())->toBe('custom_jnt_orders');
    });

    it('has correct fillable attributes', function (): void {
        $order = new JntOrder;
        $fillable = $order->getFillable();

        expect($fillable)->toContain('order_id');
        expect($fillable)->toContain('tracking_number');
        expect($fillable)->toContain('customer_code');
        expect($fillable)->toContain('sender');
        expect($fillable)->toContain('receiver');
        expect($fillable)->toContain('metadata');
        expect($fillable)->toContain('owner_type');
        expect($fillable)->toContain('owner_id');
    });

    it('casts attributes correctly', function (): void {
        $order = new JntOrder;
        $casts = $order->getCasts();

        expect($casts['package_quantity'])->toBe('integer');
        expect($casts['has_problem'])->toBe('boolean');
        expect($casts['pickup_start_at'])->toBe('datetime');
        expect($casts['sender'])->toBe('array');
        expect($casts['receiver'])->toBe('array');
        expect($casts['metadata'])->toBe('array');
    });

    it('checks if order is delivered', function (): void {
        $order = new JntOrder;
        $order->delivered_at = null;
        expect($order->isDelivered())->toBeFalse();

        $order->delivered_at = now();
        expect($order->isDelivered())->toBeTrue();
    });

    it('checks if order has problem', function (): void {
        $order = new JntOrder;
        $order->has_problem = false;
        expect($order->hasProblem())->toBeFalse();

        $order->has_problem = true;
        expect($order->hasProblem())->toBeTrue();
    });

    it('defines items relationship', function (): void {
        $order = new JntOrder;
        $relation = $order->items();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        expect($relation->getRelated())->toBeInstanceOf(JntOrderItem::class);
    });

    it('defines parcels relationship', function (): void {
        $order = new JntOrder;
        $relation = $order->parcels();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        expect($relation->getRelated())->toBeInstanceOf(JntOrderParcel::class);
    });

    it('defines trackingEvents relationship', function (): void {
        $order = new JntOrder;
        $relation = $order->trackingEvents();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        expect($relation->getRelated())->toBeInstanceOf(JntTrackingEvent::class);
    });

    it('defines webhookLogs relationship', function (): void {
        $order = new JntOrder;
        $relation = $order->webhookLogs();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
        expect($relation->getRelated())->toBeInstanceOf(JntWebhookLog::class);
    });

    it('applies owner scope when disabled', function (): void {
        config(['jnt.owner.enabled' => false]);
        $order = new JntOrder;
        $query = $order->newQuery();

        $scopedQuery = $order->scopeForOwner($query);

        // Should return unchanged query
        expect($scopedQuery)->toBe($query);
    });
});

describe('JntOrderItem model', function (): void {
    it('gets table name from config', function (): void {
        $item = new JntOrderItem;
        expect($item->getTable())->toBe('jnt_order_items');
    });

    it('gets table name from custom tables config', function (): void {
        config(['jnt.database.tables.order_items' => 'custom_jnt_items']);
        $item = new JntOrderItem;
        expect($item->getTable())->toBe('custom_jnt_items');
    });

    it('has correct fillable attributes', function (): void {
        $item = new JntOrderItem;
        $fillable = $item->getFillable();

        expect($fillable)->toContain('order_id');
        expect($fillable)->toContain('name');
        expect($fillable)->toContain('english_name');
        expect($fillable)->toContain('description');
        expect($fillable)->toContain('quantity');
        expect($fillable)->toContain('weight_grams');
        expect($fillable)->toContain('unit_price');
        expect($fillable)->toContain('currency');
        expect($fillable)->toContain('metadata');
    });

    it('casts attributes correctly', function (): void {
        $item = new JntOrderItem;
        $casts = $item->getCasts();

        expect($casts['quantity'])->toBe('integer');
        expect($casts['weight_grams'])->toBe('integer');
        expect($casts['metadata'])->toBe('array');
    });

    it('calculates weight in kilograms', function (): void {
        $item = new JntOrderItem;
        $item->weight_grams = 1500;

        expect($item->getWeightInKilograms())->toBe(1.5);
    });

    it('calculates total price', function (): void {
        $item = new JntOrderItem;
        $item->unit_price = '25.50';
        $item->quantity = 3;

        expect($item->getTotalPrice())->toBe(76.5);
    });

    it('defines order relationship', function (): void {
        $item = new JntOrderItem;
        $relation = $item->order();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
        expect($relation->getRelated())->toBeInstanceOf(JntOrder::class);
    });
});

describe('JntOrderParcel model', function (): void {
    it('gets table name from config', function (): void {
        $parcel = new JntOrderParcel;
        expect($parcel->getTable())->toBe('jnt_order_parcels');
    });

    it('gets table name from custom tables config', function (): void {
        config(['jnt.database.tables.order_parcels' => 'custom_jnt_parcels']);
        $parcel = new JntOrderParcel;
        expect($parcel->getTable())->toBe('custom_jnt_parcels');
    });

    it('has correct fillable attributes', function (): void {
        $parcel = new JntOrderParcel;
        $fillable = $parcel->getFillable();

        expect($fillable)->toContain('order_id');
        expect($fillable)->toContain('sequence');
        expect($fillable)->toContain('tracking_number');
        expect($fillable)->toContain('actual_weight');
        expect($fillable)->toContain('length');
        expect($fillable)->toContain('width');
        expect($fillable)->toContain('height');
        expect($fillable)->toContain('metadata');
    });

    it('casts attributes correctly', function (): void {
        $parcel = new JntOrderParcel;
        $casts = $parcel->getCasts();

        expect($casts['sequence'])->toBe('integer');
        expect($casts['metadata'])->toBe('array');
    });

    it('calculates volume correctly', function (): void {
        $parcel = new JntOrderParcel;
        $parcel->length = '10';
        $parcel->width = '20';
        $parcel->height = '30';

        expect($parcel->getVolume())->toBe(6000.0);
    });

    it('returns null volume when dimensions missing', function (): void {
        $parcel = new JntOrderParcel;
        $parcel->length = '10';
        $parcel->width = null;
        $parcel->height = '30';

        expect($parcel->getVolume())->toBeNull();
    });

    it('defines order relationship', function (): void {
        $parcel = new JntOrderParcel;
        $relation = $parcel->order();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
        expect($relation->getRelated())->toBeInstanceOf(JntOrder::class);
    });
});

describe('JntTrackingEvent model', function (): void {
    it('gets table name from config', function (): void {
        $event = new JntTrackingEvent;
        expect($event->getTable())->toBe('jnt_tracking_events');
    });

    it('gets table name from custom tables config', function (): void {
        config(['jnt.database.tables.tracking_events' => 'custom_jnt_tracking']);
        $event = new JntTrackingEvent;
        expect($event->getTable())->toBe('custom_jnt_tracking');
    });

    it('has correct fillable attributes', function (): void {
        $event = new JntTrackingEvent;
        $fillable = $event->getFillable();

        expect($fillable)->toContain('order_id');
        expect($fillable)->toContain('tracking_number');
        expect($fillable)->toContain('scan_type_code');
        expect($fillable)->toContain('scan_time');
        expect($fillable)->toContain('description');
        expect($fillable)->toContain('payload');
    });

    it('casts attributes correctly', function (): void {
        $event = new JntTrackingEvent;
        $casts = $event->getCasts();

        expect($casts['scan_time'])->toBe('datetime');
        expect($casts['scan_network_id'])->toBe('integer');
        expect($casts['payload'])->toBe('array');
    });

    it('checks if event is delivered', function (): void {
        $event = new JntTrackingEvent;
        $event->scan_type_code = '100'; // PARCEL_SIGNED

        expect($event->isDelivered())->toBeTrue();

        $event->scan_type_code = '10'; // PARCEL_PICKUP
        expect($event->isDelivered())->toBeFalse();
    });

    it('checks if event is collected', function (): void {
        $event = new JntTrackingEvent;
        $event->scan_type_code = '10'; // PARCEL_PICKUP

        expect($event->isCollected())->toBeTrue();

        $event->scan_type_code = '100'; // PARCEL_SIGNED
        expect($event->isCollected())->toBeFalse();
    });

    it('checks if event has problem', function (): void {
        $event = new JntTrackingEvent;
        $event->problem_type = null;
        expect($event->hasProblem())->toBeFalse();

        $event->problem_type = 'DAMAGED';
        expect($event->hasProblem())->toBeTrue();
    });

    it('builds location string from parts', function (): void {
        $event = new JntTrackingEvent;
        $event->scan_network_area = 'Area A';
        $event->scan_network_city = 'City B';
        $event->scan_network_province = 'Province C';
        $event->scan_network_country = 'Country D';

        expect($event->getLocation())->toBe('Area A, City B, Province C, Country D');
    });

    it('filters empty location parts', function (): void {
        $event = new JntTrackingEvent;
        $event->scan_network_area = null;
        $event->scan_network_city = 'City B';
        $event->scan_network_province = null;
        $event->scan_network_country = 'Country D';

        expect($event->getLocation())->toBe('City B, Country D');
    });

    it('defines order relationship', function (): void {
        $event = new JntTrackingEvent;
        $relation = $event->order();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
        expect($relation->getRelated())->toBeInstanceOf(JntOrder::class);
    });
});

describe('JntWebhookLog model', function (): void {
    it('gets table name from config', function (): void {
        $log = new JntWebhookLog;
        expect($log->getTable())->toBe('jnt_webhook_logs');
    });

    it('gets table name from custom tables config', function (): void {
        config(['jnt.database.tables.webhook_logs' => 'custom_jnt_webhooks']);
        $log = new JntWebhookLog;
        expect($log->getTable())->toBe('custom_jnt_webhooks');
    });

    it('has correct fillable attributes', function (): void {
        $log = new JntWebhookLog;
        $fillable = $log->getFillable();

        expect($fillable)->toContain('order_id');
        expect($fillable)->toContain('tracking_number');
        expect($fillable)->toContain('digest');
        expect($fillable)->toContain('headers');
        expect($fillable)->toContain('payload');
        expect($fillable)->toContain('processing_status');
        expect($fillable)->toContain('processing_error');
        expect($fillable)->toContain('processed_at');
    });

    it('casts attributes correctly', function (): void {
        $log = new JntWebhookLog;
        $casts = $log->getCasts();

        expect($casts['headers'])->toBe('array');
        expect($casts['payload'])->toBe('array');
        expect($casts['processed_at'])->toBe('datetime');
    });

    it('checks if webhook is processed', function (): void {
        $log = new JntWebhookLog;
        $log->processing_status = JntWebhookLog::STATUS_PROCESSED;
        expect($log->isProcessed())->toBeTrue();

        $log->processing_status = JntWebhookLog::STATUS_PENDING;
        expect($log->isProcessed())->toBeFalse();
    });

    it('checks if webhook failed', function (): void {
        $log = new JntWebhookLog;
        $log->processing_status = JntWebhookLog::STATUS_FAILED;
        expect($log->isFailed())->toBeTrue();

        $log->processing_status = JntWebhookLog::STATUS_PROCESSED;
        expect($log->isFailed())->toBeFalse();
    });

    it('checks if webhook is pending', function (): void {
        $log = new JntWebhookLog;
        $log->processing_status = JntWebhookLog::STATUS_PENDING;
        expect($log->isPending())->toBeTrue();

        $log->processing_status = JntWebhookLog::STATUS_PROCESSED;
        expect($log->isPending())->toBeFalse();
    });

    it('has correct status constants', function (): void {
        expect(JntWebhookLog::STATUS_PENDING)->toBe('pending');
        expect(JntWebhookLog::STATUS_PROCESSED)->toBe('processed');
        expect(JntWebhookLog::STATUS_FAILED)->toBe('failed');
    });

    it('defines order relationship', function (): void {
        $log = new JntWebhookLog;
        $relation = $log->order();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
        expect($relation->getRelated())->toBeInstanceOf(JntOrder::class);
    });
});
