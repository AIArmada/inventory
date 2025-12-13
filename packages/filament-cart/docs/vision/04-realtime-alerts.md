---
title: Phase 3 - Real-time & Alerts
---

# Phase 3: Real-time Monitoring & Alerts

> **Status:** Not Started  
> **Priority:** Medium  
> **Estimated Effort:** 1 Sprint

---

## Overview

Add real-time dashboard capabilities and proactive alerting for high-value events like large cart abandonments, fraud detection, and recovery opportunities.

---

## Components

### 1. AlertRule Model

Configurable alert rules for admin notifications.

```php
// Migration: create_cart_alert_rules_table
Schema::create('cart_alert_rules', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description')->nullable();
    
    // Trigger conditions
    $table->string('event_type'); // abandonment, fraud, high_value, recovery
    $table->json('conditions'); // Flexible condition rules
    
    // Channels
    $table->boolean('notify_email')->default(true);
    $table->boolean('notify_slack')->default(false);
    $table->boolean('notify_webhook')->default(false);
    $table->boolean('notify_database')->default(true);
    
    // Recipients
    $table->json('email_recipients')->nullable();
    $table->string('slack_webhook_url')->nullable();
    $table->string('webhook_url')->nullable();
    
    // Throttling
    $table->unsignedInteger('cooldown_minutes')->default(60);
    $table->timestamp('last_triggered_at')->nullable();
    
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2. AlertLog Model

Track triggered alerts.

```php
// Migration: create_cart_alert_logs_table
Schema::create('cart_alert_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('alert_rule_id');
    
    $table->string('event_type');
    $table->json('event_data');
    $table->json('channels_notified');
    
    $table->boolean('is_read')->default(false);
    $table->timestamp('read_at')->nullable();
    $table->foreignUuid('read_by')->nullable();
    
    $table->timestamps();
});
```

### 3. CartMonitor Service

Real-time cart monitoring service.

```php
class CartMonitor
{
    // Live stats
    public function getLiveStats(): LiveStats;
    public function getActiveCartsCount(): int;
    public function getRecentAbandonments(int $minutes = 30): Collection;
    public function getHighValueCarts(int $threshold = 10000): Collection;
    
    // Event detection
    public function detectAbandonments(): Collection;
    public function detectFraudSignals(): Collection;
    public function detectRecoveryOpportunities(): Collection;
    
    // Subscriptions (for WebSocket)
    public function subscribeToCartUpdates(callable $callback): void;
    public function subscribeToAbandonments(callable $callback): void;
    public function subscribeToFraudAlerts(callable $callback): void;
}
```

### 4. AlertDispatcher Service

Dispatch alerts across multiple channels.

```php
class AlertDispatcher
{
    public function dispatch(AlertRule $rule, array $eventData): void;
    public function dispatchEmail(AlertRule $rule, array $eventData): void;
    public function dispatchSlack(AlertRule $rule, array $eventData): void;
    public function dispatchWebhook(AlertRule $rule, array $eventData): void;
    public function dispatchDatabase(AlertRule $rule, array $eventData): void;
}
```

### 5. AlertEvaluator Service

Evaluate alert conditions against events.

```php
class AlertEvaluator
{
    public function evaluate(AlertRule $rule, array $eventData): bool;
    public function getMatchingRules(string $eventType, array $eventData): Collection;
    public function shouldThrottle(AlertRule $rule): bool;
}
```

---

## DTOs

```php
// LiveStats
class LiveStats extends Data
{
    public int $active_carts;
    public int $carts_with_items;
    public int $checkouts_in_progress;
    public int $recent_abandonments;
    public int $pending_alerts;
    public int $total_value_cents;
    public Carbon $updated_at;
}

// AlertEvent
class AlertEvent extends Data
{
    public string $event_type;
    public string $severity; // info, warning, critical
    public string $title;
    public string $message;
    public ?string $cart_id;
    public array $data;
    public Carbon $occurred_at;
}
```

---

## Filament Components

### LiveDashboardPage

Real-time dashboard with auto-refresh.

```php
class LiveDashboardPage extends Page
{
    protected static ?string $slug = 'cart-live';
    protected static ?string $title = 'Live Cart Monitor';
    
    // Livewire polling for real-time updates
    public $pollingInterval = 5; // seconds
    
    protected function getHeaderWidgets(): array
    {
        return [
            LiveStatsWidget::class,
        ];
    }
    
    protected function getFooterWidgets(): array
    {
        return [
            RecentActivityWidget::class,
            PendingAlertsWidget::class,
        ];
    }
}
```

### AlertRuleResource

Manage alert rules.

```php
class AlertRuleResource extends Resource
{
    // Create/edit alert rules
    // Preview conditions
    // Test alert functionality
}
```

### New Widgets

1. **LiveStatsWidget** - Real-time cart stats with polling
2. **RecentActivityWidget** - Live feed of cart events
3. **PendingAlertsWidget** - Unread alerts requiring attention
4. **AlertHistoryWidget** - Recent alert history

### Alert Notification

Custom Filament notification for alerts.

```php
// In-app notification when alerts trigger
Notification::make()
    ->title('High-Value Cart Abandoned')
    ->body('A $500+ cart was abandoned 5 minutes ago.')
    ->warning()
    ->actions([
        Action::make('view')
            ->url(route('filament.admin.resources.carts.view', $cartId)),
        Action::make('recover')
            ->action(fn () => $this->initiateRecovery($cartId)),
    ])
    ->send();
```

---

## Broadcasting (Optional)

For true real-time with WebSockets:

```php
// Events for broadcasting
class CartUpdated implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('cart-monitor');
    }
}

class CartAbandoned implements ShouldBroadcast
{
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('cart-alerts');
    }
}
```

---

## Alert Condition DSL

Flexible condition rules in JSON:

```json
{
    "event_type": "abandonment",
    "conditions": {
        "all": [
            {"field": "cart_value_cents", "operator": ">=", "value": 10000},
            {"field": "items_count", "operator": ">=", "value": 3},
            {"field": "time_since_abandonment_minutes", "operator": "<=", "value": 30}
        ]
    }
}
```

---

## Commands

### MonitorCartsCommand

```bash
php artisan cart:monitor              # Start monitoring daemon
php artisan cart:monitor --once       # Single monitoring pass
```

### ProcessAlertsCommand

```bash
php artisan cart:process-alerts       # Evaluate and dispatch alerts
php artisan cart:process-alerts --rule=uuid
```

---

## Configuration

```php
// config/filament-cart.php
'monitoring' => [
    'enabled' => true,
    'polling_interval_seconds' => 10,
    'abandonment_detection_minutes' => 30,
],

'alerts' => [
    'enabled' => true,
    'default_cooldown_minutes' => 60,
    'channels' => [
        'email' => true,
        'slack' => false,
        'webhook' => false,
        'database' => true,
    ],
    'slack_webhook_url' => env('CART_SLACK_WEBHOOK'),
],
```

---

## Files to Create

| File | Type | Description |
|------|------|-------------|
| `database/migrations/..._create_cart_alert_rules_table.php` | Migration | Alert rules |
| `database/migrations/..._create_cart_alert_logs_table.php` | Migration | Alert logs |
| `src/Models/AlertRule.php` | Model | Alert rule model |
| `src/Models/AlertLog.php` | Model | Alert log model |
| `src/Services/CartMonitor.php` | Service | Real-time monitoring |
| `src/Services/AlertDispatcher.php` | Service | Alert dispatch |
| `src/Services/AlertEvaluator.php` | Service | Condition evaluation |
| `src/Data/LiveStats.php` | DTO | Live statistics |
| `src/Data/AlertEvent.php` | DTO | Alert event data |
| `src/Resources/AlertRuleResource.php` | Resource | Alert management |
| `src/Pages/LiveDashboardPage.php` | Page | Live monitoring |
| `src/Widgets/LiveStatsWidget.php` | Widget | Real-time stats |
| `src/Widgets/RecentActivityWidget.php` | Widget | Activity feed |
| `src/Widgets/PendingAlertsWidget.php` | Widget | Pending alerts |
| `src/Commands/MonitorCartsCommand.php` | Command | Monitoring daemon |
| `src/Commands/ProcessAlertsCommand.php` | Command | Alert processing |

---

## Tests

- `CartMonitorTest` - Monitoring service tests
- `AlertDispatcherTest` - Dispatch tests
- `AlertEvaluatorTest` - Condition evaluation tests
- `LiveDashboardTest` - Page integration tests
