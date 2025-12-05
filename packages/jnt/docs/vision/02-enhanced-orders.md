# Enhanced Order Management

> **Document:** 02 of 05  
> **Package:** `aiarmada/jnt`  
> **Status:** Vision (API-Constrained)

---

## Overview

Enhance order lifecycle management using J&T's existing order APIs with better validation, status synchronization, and local tracking.

---

## J&T Order API Capabilities

```php
// Create single order
Jnt::createOrder($orderData);

// Create batch orders
Jnt::createBatchOrders($ordersArray);

// Cancel order
Jnt::cancelOrder($orderId, $reason);

// Query order status
Jnt::queryOrder($orderId);
```

---

## Enhanced Order Model

```php
/**
 * @property string $id
 * @property string $jnt_order_id
 * @property string $tracking_number
 * @property string $status
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array $sender
 * @property array $receiver
 * @property array $items
 * @property int $weight_grams
 * @property string|null $service_type
 * @property Carbon|null $pickup_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property array|null $metadata
 */
class JntOrder extends Model
{
    use HasUuids;
    
    protected $casts = [
        'status' => JntOrderStatus::class,
        'sender' => 'array',
        'receiver' => 'array',
        'items' => 'array',
        'pickup_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function trackingEvents(): HasMany
    {
        return $this->hasMany(JntTrackingEvent::class, 'order_id');
    }
    
    public function latestEvent(): HasOne
    {
        return $this->hasOne(JntTrackingEvent::class, 'order_id')
            ->latestOfMany();
    }
}
```

---

## Order Status Enum

```php
enum JntOrderStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
            self::PickedUp => 'Picked Up',
            self::InTransit => 'In Transit',
            self::OutForDelivery => 'Out for Delivery',
            self::Delivered => 'Delivered',
            self::Failed => 'Delivery Failed',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }
    
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Returned,
            self::Cancelled,
        ]);
    }
}
```

---

## Order Service

```php
class JntOrderService
{
    public function __construct(
        private JntClient $client,
    ) {}
    
    public function create(array $data, ?Model $owner = null): JntOrder
    {
        // Validate locally first
        $validated = $this->validate($data);
        
        // Submit to J&T
        $response = $this->client->createOrder($validated);
        
        // Store locally
        return JntOrder::create([
            'jnt_order_id' => $response['orderId'],
            'tracking_number' => $response['trackingNumber'],
            'status' => JntOrderStatus::Submitted,
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
            'sender' => $validated['sender'],
            'receiver' => $validated['receiver'],
            'items' => $validated['items'] ?? [],
            'weight_grams' => $validated['weight'] ?? 0,
            'service_type' => $validated['serviceType'] ?? null,
        ]);
    }
    
    public function cancel(JntOrder $order, string $reason): JntOrder
    {
        if ($order->status->isFinal()) {
            throw new OrderCannotBeCancelledException();
        }
        
        $this->client->cancelOrder($order->jnt_order_id, $reason);
        
        $order->update([
            'status' => JntOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
        
        event(new JntOrderCancelled($order));
        
        return $order;
    }
    
    public function syncStatus(JntOrder $order): JntOrder
    {
        $response = $this->client->queryOrder($order->jnt_order_id);
        
        $newStatus = $this->mapApiStatus($response['status']);
        
        if ($order->status !== $newStatus) {
            $order->update(['status' => $newStatus]);
            event(new JntOrderStatusChanged($order, $newStatus));
        }
        
        return $order;
    }
    
    private function mapApiStatus(string $apiStatus): JntOrderStatus
    {
        return match ($apiStatus) {
            'PENDING' => JntOrderStatus::Pending,
            'PICKED_UP' => JntOrderStatus::PickedUp,
            'IN_TRANSIT' => JntOrderStatus::InTransit,
            'OUT_FOR_DELIVERY' => JntOrderStatus::OutForDelivery,
            'DELIVERED' => JntOrderStatus::Delivered,
            'FAILED' => JntOrderStatus::Failed,
            'RETURNED' => JntOrderStatus::Returned,
            default => JntOrderStatus::Submitted,
        };
    }
}
```

---

## Order Builder

```php
class JntOrderBuilder
{
    private array $data = [];
    
    public function sender(
        string $name,
        string $phone,
        string $address,
        string $city,
        string $postcode,
        string $state = 'Malaysia'
    ): self {
        $this->data['sender'] = compact(
            'name', 'phone', 'address', 'city', 'postcode', 'state'
        );
        return $this;
    }
    
    public function receiver(
        string $name,
        string $phone,
        string $address,
        string $city,
        string $postcode,
        string $state = 'Malaysia'
    ): self {
        $this->data['receiver'] = compact(
            'name', 'phone', 'address', 'city', 'postcode', 'state'
        );
        return $this;
    }
    
    public function item(string $description, int $quantity = 1): self
    {
        $this->data['items'][] = compact('description', 'quantity');
        return $this;
    }
    
    public function weight(int $grams): self
    {
        $this->data['weight'] = $grams;
        return $this;
    }
    
    public function cod(int $amountMinor): self
    {
        $this->data['cod'] = $amountMinor;
        return $this;
    }
    
    public function create(?Model $owner = null): JntOrder
    {
        return app(JntOrderService::class)->create($this->data, $owner);
    }
}

// Usage
Jnt::order()
    ->sender('Shop', '0123456789', '123 Street', 'KL', '50000')
    ->receiver('Customer', '0198765432', '456 Road', 'PJ', '47301')
    ->item('Product A', 2)
    ->weight(500)
    ->create($order);
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-tracking-status.md](03-tracking-status.md)
