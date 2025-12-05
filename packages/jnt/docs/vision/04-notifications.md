# App-Layer Notifications

> **Document:** 04 of 05  
> **Package:** `aiarmada/jnt`  
> **Status:** Vision (API-Constrained)

---

## Overview

Build customer notification system using **Laravel events** (not J&T API). J&T does not provide notification APIs - all notifications must be app-layer.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  APP-LAYER NOTIFICATIONS                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  J&T Webhook ──► Status Update ──► Laravel Event             │
│                                          │                   │
│                                          ▼                   │
│                              ┌───────────────────┐           │
│                              │   Event Listener  │           │
│                              └─────────┬─────────┘           │
│                                        │                     │
│                   ┌────────────────────┼────────────────┐    │
│                   ▼                    ▼                ▼    │
│              [Email]              [SMS]            [Push]    │
│                                                              │
│  Local: Event dispatch, notification channels               │
│  J&T: Status data only                                       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Events

```php
// Order submitted to J&T
class JntOrderSubmitted
{
    public function __construct(
        public JntOrder $order,
    ) {}
}

// Status changed (from webhook or sync)
class JntOrderStatusChanged
{
    public function __construct(
        public JntOrder $order,
        public JntOrderStatus $previousStatus,
        public JntOrderStatus $newStatus,
    ) {}
}

// Order delivered
class JntOrderDelivered
{
    public function __construct(
        public JntOrder $order,
    ) {}
}

// Delivery failed
class JntOrderDeliveryFailed
{
    public function __construct(
        public JntOrder $order,
        public string $reason,
    ) {}
}
```

---

## Notification Classes

```php
class JntShipmentNotification extends Notification
{
    public function __construct(
        private JntOrder $order,
        private string $type,
    ) {}
    
    public function via(object $notifiable): array
    {
        return config('jnt.notifications.channels', ['mail']);
    }
    
    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->type) {
            'shipped' => $this->shippedMail(),
            'out_for_delivery' => $this->outForDeliveryMail(),
            'delivered' => $this->deliveredMail(),
            'failed' => $this->failedMail(),
            default => $this->genericMail(),
        };
    }
    
    private function shippedMail(): MailMessage
    {
        return (new MailMessage)
            ->subject('Your order has been shipped!')
            ->greeting("Hello {$this->order->receiver['name']}")
            ->line('Your order is on its way.')
            ->line("Tracking Number: {$this->order->tracking_number}")
            ->action('Track Your Order', $this->trackingUrl())
            ->line('Thank you for your order!');
    }
    
    private function outForDeliveryMail(): MailMessage
    {
        return (new MailMessage)
            ->subject('Your order is out for delivery!')
            ->line('Your order will be delivered today.')
            ->line("Tracking: {$this->order->tracking_number}")
            ->action('Track Your Order', $this->trackingUrl());
    }
    
    private function deliveredMail(): MailMessage
    {
        return (new MailMessage)
            ->subject('Your order has been delivered!')
            ->line('Your order has been successfully delivered.')
            ->line('Thank you for shopping with us!');
    }
    
    private function trackingUrl(): string
    {
        return config('jnt.notifications.tracking_url')
            . '?tracking=' . $this->order->tracking_number;
    }
}
```

---

## Event Listeners

```php
class SendShipmentNotifications
{
    public function handle(JntOrderStatusChanged $event): void
    {
        if (!config('jnt.notifications.enabled', true)) {
            return;
        }
        
        $order = $event->order;
        $notifiable = $this->getNotifiable($order);
        
        if (!$notifiable) {
            return;
        }
        
        $type = match ($event->newStatus) {
            JntOrderStatus::PickedUp => 'shipped',
            JntOrderStatus::OutForDelivery => 'out_for_delivery',
            JntOrderStatus::Delivered => 'delivered',
            JntOrderStatus::Failed => 'failed',
            default => null,
        };
        
        if ($type && $this->shouldNotify($type)) {
            $notifiable->notify(new JntShipmentNotification($order, $type));
        }
    }
    
    private function getNotifiable(JntOrder $order): ?object
    {
        // Try to get from owner relationship
        if ($order->owner && method_exists($order->owner, 'notify')) {
            return $order->owner;
        }
        
        // Create anonymous notifiable from receiver data
        $receiver = $order->receiver;
        if (isset($receiver['email'])) {
            return Notification::route('mail', $receiver['email']);
        }
        
        return null;
    }
    
    private function shouldNotify(string $type): bool
    {
        $enabledTypes = config('jnt.notifications.types', [
            'shipped', 'out_for_delivery', 'delivered', 'failed'
        ]);
        
        return in_array($type, $enabledTypes);
    }
}
```

---

## Configuration

```php
// config/jnt.php
return [
    'notifications' => [
        'enabled' => env('JNT_NOTIFICATIONS_ENABLED', true),
        
        'channels' => ['mail'],
        
        'types' => [
            'shipped',
            'out_for_delivery', 
            'delivered',
            'failed',
        ],
        
        'tracking_url' => env('JNT_TRACKING_URL', '/tracking'),
    ],
];
```

---

## Service Provider Registration

```php
class JntServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            JntOrderStatusChanged::class,
            SendShipmentNotifications::class,
        );
    }
}
```

---

## Important Note

**All notifications are app-layer.** J&T Express API does not provide:
- Email sending
- SMS sending  
- Push notifications
- Notification templates

These must be implemented in your Laravel application using Laravel's notification system.

---

## Navigation

**Previous:** [03-tracking-status.md](03-tracking-status.md)  
**Next:** [05-implementation-roadmap.md](05-implementation-roadmap.md)
