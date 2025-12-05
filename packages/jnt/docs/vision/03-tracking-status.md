# Tracking & Status Normalization

> **Document:** 03 of 05  
> **Package:** `aiarmada/jnt`  
> **Status:** Vision (API-Constrained)

---

## Overview

Normalize J&T tracking data into a unified format with enhanced tracking events storage and status mapping.

---

## J&T Tracking API

```php
// Track by tracking number
Jnt::trackParcel($trackingNumber);

// Track by order ID
Jnt::trackByOrderId($orderId);

// Batch tracking
Jnt::trackParcels([$tracking1, $tracking2]);
```

---

## Tracking Event Model

```php
/**
 * @property string $id
 * @property string $order_id
 * @property string $jnt_status_code
 * @property string $normalized_status
 * @property string $description
 * @property string|null $location
 * @property Carbon $occurred_at
 * @property array|null $raw_data
 */
class JntTrackingEvent extends Model
{
    use HasUuids;
    
    protected $casts = [
        'normalized_status' => TrackingStatus::class,
        'occurred_at' => 'datetime',
        'raw_data' => 'array',
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(JntOrder::class, 'order_id');
    }
}
```

---

## Normalized Status Enum

```php
enum TrackingStatus: string
{
    case Pending = 'pending';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case AtHub = 'at_hub';
    case OutForDelivery = 'out_for_delivery';
    case DeliveryAttempted = 'delivery_attempted';
    case Delivered = 'delivered';
    case ReturnInitiated = 'return_initiated';
    case Returned = 'returned';
    case Exception = 'exception';
    
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::PickedUp => 'heroicon-o-truck',
            self::InTransit => 'heroicon-o-arrow-path',
            self::AtHub => 'heroicon-o-building-office',
            self::OutForDelivery => 'heroicon-o-map-pin',
            self::DeliveryAttempted => 'heroicon-o-exclamation-circle',
            self::Delivered => 'heroicon-o-check-circle',
            self::ReturnInitiated => 'heroicon-o-arrow-uturn-left',
            self::Returned => 'heroicon-o-arrow-uturn-left',
            self::Exception => 'heroicon-o-x-circle',
        };
    }
    
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::PickedUp, self::InTransit, self::AtHub => 'blue',
            self::OutForDelivery => 'yellow',
            self::DeliveryAttempted => 'orange',
            self::Delivered => 'green',
            self::ReturnInitiated, self::Returned => 'purple',
            self::Exception => 'red',
        };
    }
}
```

---

## Status Mapper

```php
class JntStatusMapper
{
    /**
     * Map J&T status codes to normalized status
     */
    private array $mapping = [
        'PENDING' => TrackingStatus::Pending,
        'PICKED_UP' => TrackingStatus::PickedUp,
        'IN_TRANSIT' => TrackingStatus::InTransit,
        'ARRIVED_AT_FACILITY' => TrackingStatus::AtHub,
        'DEPARTED_FACILITY' => TrackingStatus::InTransit,
        'OUT_FOR_DELIVERY' => TrackingStatus::OutForDelivery,
        'DELIVERY_ATTEMPTED' => TrackingStatus::DeliveryAttempted,
        'DELIVERED' => TrackingStatus::Delivered,
        'RETURNING' => TrackingStatus::ReturnInitiated,
        'RETURNED' => TrackingStatus::Returned,
        'EXCEPTION' => TrackingStatus::Exception,
    ];
    
    public function map(string $jntStatus): TrackingStatus
    {
        return $this->mapping[strtoupper($jntStatus)] 
            ?? TrackingStatus::Exception;
    }
}
```

---

## Tracking Service

```php
class JntTrackingService
{
    public function __construct(
        private JntClient $client,
        private JntStatusMapper $mapper,
    ) {}
    
    public function track(string $trackingNumber): TrackingResult
    {
        $response = $this->client->trackParcel($trackingNumber);
        
        return new TrackingResult(
            trackingNumber: $trackingNumber,
            currentStatus: $this->mapper->map($response['status']),
            events: $this->parseEvents($response['history'] ?? []),
            estimatedDelivery: isset($response['eta']) 
                ? Carbon::parse($response['eta']) 
                : null,
        );
    }
    
    public function syncOrderTracking(JntOrder $order): JntOrder
    {
        $result = $this->track($order->tracking_number);
        
        // Store new events
        foreach ($result->events as $event) {
            JntTrackingEvent::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'jnt_status_code' => $event->code,
                    'occurred_at' => $event->occurredAt,
                ],
                [
                    'normalized_status' => $event->status,
                    'description' => $event->description,
                    'location' => $event->location,
                    'raw_data' => $event->rawData,
                ]
            );
        }
        
        // Update order status
        if ($result->currentStatus !== $order->status) {
            $order->update([
                'status' => $this->mapToOrderStatus($result->currentStatus),
            ]);
            
            if ($result->currentStatus === TrackingStatus::Delivered) {
                $order->update(['delivered_at' => now()]);
            }
            
            event(new JntOrderStatusChanged($order));
        }
        
        return $order->fresh();
    }
    
    private function parseEvents(array $history): array
    {
        return collect($history)
            ->map(fn ($item) => new TrackingEvent(
                code: $item['status'],
                status: $this->mapper->map($item['status']),
                description: $item['description'] ?? '',
                location: $item['location'] ?? null,
                occurredAt: Carbon::parse($item['timestamp']),
                rawData: $item,
            ))
            ->sortByDesc('occurredAt')
            ->values()
            ->all();
    }
}
```

---

## Tracking Result DTO

```php
readonly class TrackingResult
{
    public function __construct(
        public string $trackingNumber,
        public TrackingStatus $currentStatus,
        public array $events,
        public ?Carbon $estimatedDelivery,
    ) {}
    
    public function isDelivered(): bool
    {
        return $this->currentStatus === TrackingStatus::Delivered;
    }
    
    public function latestEvent(): ?TrackingEvent
    {
        return $this->events[0] ?? null;
    }
}

readonly class TrackingEvent
{
    public function __construct(
        public string $code,
        public TrackingStatus $status,
        public string $description,
        public ?string $location,
        public Carbon $occurredAt,
        public array $rawData,
    ) {}
}
```

---

## Webhook Handler

```php
class JntWebhookController extends Controller
{
    public function handle(
        Request $request,
        JntTrackingService $tracking
    ): Response {
        $payload = $request->all();
        
        $order = JntOrder::where('tracking_number', $payload['trackingNumber'])
            ->first();
        
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        
        // Sync tracking from webhook data
        $tracking->syncOrderTracking($order);
        
        return response()->json(['success' => true]);
    }
}
```

---

## Navigation

**Previous:** [02-enhanced-orders.md](02-enhanced-orders.md)  
**Next:** [04-notifications.md](04-notifications.md)
