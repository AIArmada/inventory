---
title: Events
---

# Events

The Cart package dispatches events for all significant operations, enabling audit logging, analytics, and reactive integrations.

## Event List

### Cart Events

| Event | When Fired |
|-------|------------|
| `CartCreated` | First item added to empty cart |
| `CartCleared` | Cart items cleared |
| `CartDestroyed` | Cart completely removed |
| `CartMerged` | Guest cart merged with user cart on login |

### Item Events

| Event | When Fired |
|-------|------------|
| `ItemAdded` | Item added to cart |
| `ItemUpdated` | Item quantity/price/attributes changed |
| `ItemRemoved` | Item removed from cart |

### Condition Events

| Event | When Fired |
|-------|------------|
| `CartConditionAdded` | Condition added to cart |
| `CartConditionRemoved` | Condition removed from cart |
| `ItemConditionAdded` | Condition added to specific item |
| `ItemConditionRemoved` | Condition removed from specific item |

### Metadata Events

| Event | When Fired |
|-------|------------|
| `MetadataAdded` | Single metadata key set |
| `MetadataBatchAdded` | Multiple metadata keys set |
| `MetadataRemoved` | Metadata key removed |
| `MetadataCleared` | All metadata cleared |

## Event Properties

All cart events implement `CartEventInterface` and include:

```php
$event->getEventId();        // Unique UUID
$event->getOccurredAt();     // DateTimeImmutable
$event->getEventType();      // e.g., 'cart.item.added'
$event->getCartIdentifier(); // User/session ID
$event->getCartInstance();   // Instance name
$event->getCartId();         // Cart UUID (if exists)
$event->getEventMetadata();  // Request context
$event->toEventPayload();    // Full event data
```

## Listening to Events

### In Event Service Provider

```php
use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\CartCreated;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ItemAdded::class => [
            TrackAddToCart::class,
            UpdateAnalytics::class,
        ],
        CartCreated::class => [
            SendCartStartedNotification::class,
        ],
    ];
}
```

### Using Closures

```php
use Illuminate\Support\Facades\Event;
use AIArmada\Cart\Events\ItemAdded;

Event::listen(ItemAdded::class, function (ItemAdded $event) {
    logger('Item added', [
        'item_id' => $event->item->id,
        'item_name' => $event->item->name,
        'quantity' => $event->item->quantity,
        'cart' => $event->cart->getIdentifier(),
    ]);
});
```

## Event Examples

### ItemAdded

```php
use AIArmada\Cart\Events\ItemAdded;

class TrackAddToCart
{
    public function handle(ItemAdded $event): void
    {
        $item = $event->item;
        $cart = $event->cart;
        
        // Send to analytics
        Analytics::track('add_to_cart', [
            'item_id' => $item->id,
            'item_name' => $item->name,
            'price' => $item->price,
            'quantity' => $item->quantity,
            'cart_total' => $cart->getRawTotal(),
        ]);
    }
}
```

### CartMerged

```php
use AIArmada\Cart\Events\CartMerged;

class HandleCartMerge
{
    public function handle(CartMerged $event): void
    {
        logger('Cart merged', [
            'target_cart' => $event->targetCart->getIdentifier(),
            'items_merged' => $event->totalItemsMerged,
            'strategy' => $event->mergeStrategy,
            'had_conflicts' => $event->hadConflicts,
            'source_identifier' => $event->originalSourceIdentifier,
            'target_identifier' => $event->originalTargetIdentifier,
        ]);
    }
}
```

### CartConditionAdded

```php
use AIArmada\Cart\Events\CartConditionAdded;

class LogConditionApplied
{
    public function handle(CartConditionAdded $event): void
    {
        $condition = $event->condition;
        
        AuditLog::create([
            'event' => 'condition_applied',
            'condition_name' => $condition->getName(),
            'condition_type' => $condition->getType(),
            'condition_value' => $condition->getValue(),
            'cart_id' => $event->cart->getId(),
        ]);
    }
}
```

## Transaction-Aware Dispatching

Events are dispatched **after database transactions commit** to prevent lost events on rollback:

```php
// In DispatchesEvents trait
protected function dispatchEvent(object $event): void
{
    if (!config('cart.events', true)) {
        return;
    }
    
    if (DB::transactionLevel() > 0) {
        DB::afterCommit(fn() => event($event));
    } else {
        event($event);
    }
}
```

## Disabling Events

### Globally

```php
// config/cart.php
'events' => false,
```

### Per Environment

```env
CART_EVENTS_ENABLED=false
```

## Event Metadata

Events include request context for tracing:

```php
$metadata = $event->getEventMetadata();

// Returns:
[
    'event_id' => 'uuid',
    'occurred_at' => '2024-01-15T10:30:00+00:00',
    'user_agent' => 'Mozilla/5.0...',
    'ip_address' => '192.168.1.1',
    'correlation_id' => 'request-uuid', // From X-Correlation-ID header
]
```

## Broadcasting Events

Events can be broadcast for real-time updates:

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use AIArmada\Cart\Events\ItemAdded;

class ItemAddedBroadcaster implements ShouldBroadcast
{
    public function __construct(
        public ItemAdded $event
    ) {}
    
    public function broadcastOn(): array
    {
        return ['cart.' . $this->event->cart->getIdentifier()];
    }
    
    public function broadcastAs(): string
    {
        return 'item.added';
    }
}

// In listener
Event::listen(ItemAdded::class, function (ItemAdded $event) {
    broadcast(new ItemAddedBroadcaster($event));
});
```

## Queued Event Processing

For heavy operations, queue the listeners:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class SendAbandonedCartEmail implements ShouldQueue
{
    public $queue = 'emails';
    
    public function handle(CartCreated $event): void
    {
        // Schedule abandonment email
    }
}
```
