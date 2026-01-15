---
title: Monitoring & Alerts
---

# Monitoring & Alerts

The package provides real-time cart monitoring and a configurable alerting system for detecting abandonment, fraud, and other significant events.

## Enabling Monitoring

Monitoring features are controlled by configuration:

```php
// config/filament-cart.php
'features' => [
    'monitoring' => true,         // Enable monitoring
    'real_time_alerts' => true,   // Enable alert system
    'fraud_detection' => true,    // Enable fraud detection
],

'monitoring' => [
    'polling_interval' => 10,     // Seconds
    'abandonment_detection_minutes' => 30,
    'high_value_alert_threshold' => 50000, // $500
    'alert_cooldown_minutes' => 15,
],
```

## CartMonitor Service

The core monitoring service provides real-time insights.

```php
use AIArmada\FilamentCart\Services\CartMonitor;

$monitor = app(CartMonitor::class);
```

### Live Statistics

Get current cart statistics for dashboards:

```php
$stats = $monitor->getLiveStats();

$stats->active_carts;           // Total active carts
$stats->carts_with_items;       // Carts with items
$stats->checkouts_in_progress;  // Currently checking out
$stats->recent_abandonments;    // Abandoned in last 30 min
$stats->pending_alerts;         // Unread alerts
$stats->total_value_cents;      // Total cart value
$stats->high_value_carts;       // Carts above threshold
$stats->fraud_signals;          // Fraud alerts (24h)
$stats->updated_at;             // Timestamp

// Formatted money value
echo $stats->getFormattedTotalValue(); // "$12,345.00"
```

### Active Cart Count

```php
$count = $monitor->getActiveCartsCount();
```

### Recent Abandonments

Get carts that appear abandoned (no activity within configured minutes):

```php
// Default: 30 minutes
$abandonments = $monitor->getRecentAbandonments();

// Custom timeframe
$abandonments = $monitor->getRecentAbandonments(60); // 1 hour

foreach ($abandonments as $cart) {
    echo "{$cart->identifier}: \${$cart->total / 100}";
}
```

### High Value Carts

Get carts above a value threshold:

```php
// Use configured threshold
$highValue = $monitor->getHighValueCarts();

// Custom threshold ($200)
$highValue = $monitor->getHighValueCarts(20000);
```

### Abandonment Detection

Detect abandonments that haven't been alerted yet:

```php
$newAbandonments = $monitor->detectAbandonments();

foreach ($newAbandonments as $cart) {
    // Create alert, send notification, etc.
}
```

### Fraud Signal Detection

Detect potentially fraudulent cart activity:

```php
$fraudSignals = $monitor->detectFraudSignals();

foreach ($fraudSignals as $cart) {
    // Cart shows suspicious patterns
    // - High value + very recent creation
    // - Multiple high-quantity items
    // - Unusual patterns
}
```

### Recovery Opportunities

Find carts that are good candidates for recovery:

```php
$opportunities = $monitor->detectRecoveryOpportunities();

// Returns carts that:
// - Have items ($20+ value)
// - Abandoned 30min to 2 hours ago
// - Not yet marked as abandoned
```

### Recent Activity Feed

Get a real-time activity feed:

```php
$activity = $monitor->getRecentActivity(20);

foreach ($activity as $event) {
    echo "{$event->session_id}: {$event->status} - {$event->items_count} items";
    // status: 'active', 'checkout', 'abandoned'
}
```

## Alert Rules

Alert rules define conditions that trigger notifications.

### Creating an Alert Rule

```php
use AIArmada\FilamentCart\Models\AlertRule;

$rule = AlertRule::create([
    'name' => 'High Value Abandonment',
    'description' => 'Alert when carts over $100 are abandoned',
    'event_type' => 'abandonment', // abandonment, fraud, high_value, custom
    'conditions' => [
        'min_value_cents' => 10000, // $100+
        'min_items' => 1,
        'inactivity_minutes' => 30,
    ],
    'severity' => 'warning', // info, warning, critical
    'priority' => 1,
    'is_active' => true,
    
    // Notification channels
    'notify_email' => true,
    'notify_slack' => true,
    'notify_webhook' => false,
    'notify_database' => true,
    
    // Channel configuration
    'email_recipients' => ['alerts@example.com', 'manager@example.com'],
    'slack_webhook_url' => 'https://hooks.slack.com/services/...',
    'webhook_url' => null,
    
    // Cooldown to prevent spam
    'cooldown_minutes' => 15,
]);
```

### Event Types

| Event Type | Description |
|------------|-------------|
| `abandonment` | Cart was abandoned |
| `fraud` | Suspicious activity detected |
| `high_value` | High-value cart activity |
| `checkout_stuck` | Checkout not progressing |
| `recovery` | Recovery attempt needed |
| `custom` | Custom rule |

### Condition Examples

```php
// High-value abandonment
'conditions' => [
    'min_value_cents' => 10000,
    'inactivity_minutes' => 30,
]

// Fraud: rapid high-value
'conditions' => [
    'min_value_cents' => 50000,
    'creation_within_minutes' => 10,
]

// Large cart
'conditions' => [
    'min_items' => 10,
    'min_value_cents' => 20000,
]

// Checkout stuck
'conditions' => [
    'checkout_started' => true,
    'checkout_inactive_minutes' => 15,
]
```

### Cooldown Management

Prevent alert spam with cooldowns:

```php
// Check if rule is in cooldown
if ($rule->isInCooldown()) {
    $minutes = $rule->getCooldownRemainingMinutes();
    echo "Rule is in cooldown for {$minutes} more minutes";
}

// Mark as triggered (resets cooldown)
$rule->markTriggered();

// Get enabled notification channels
$channels = $rule->getEnabledChannels();
// ['email', 'slack', 'database']
```

## Alert Logs

Every triggered alert is logged for tracking and action.

### Alert Log Fields

```php
use AIArmada\FilamentCart\Models\AlertLog;

$log = AlertLog::create([
    'alert_rule_id' => $rule->id,
    'event_type' => 'abandonment',
    'severity' => 'warning',
    'title' => 'High-value cart abandoned',
    'message' => 'Cart #abc123 worth $150 was abandoned after 30 minutes of inactivity.',
    'event_data' => [
        'cart_id' => 'uuid-here',
        'cart_value' => 15000,
        'items_count' => 5,
        'inactivity_minutes' => 35,
    ],
    'channels_notified' => ['email', 'database'],
    'cart_id' => 'uuid-here',
    'session_id' => 'session-id',
]);
```

### Managing Alert Logs

```php
// Mark as read
$log->markAsRead(auth()->id());

// Mark as unread
$log->markAsUnread();

// Record action taken
$log->recordAction('sent_recovery_email');
$log->recordAction('blocked_cart');
$log->recordAction('dismissed');

// Check severity
if ($log->isCritical()) {
    // Escalate
}

// Get display color
$color = $log->getSeverityColor(); // 'danger', 'warning', 'info'
```

### Querying Alerts

```php
// Unread alerts
$unread = AlertLog::query()->forOwner()
    ->where('is_read', false)
    ->orderByDesc('created_at')
    ->get();

// Critical alerts
$critical = AlertLog::query()->forOwner()
    ->where('severity', 'critical')
    ->where('is_read', false)
    ->get();

// Alerts for a specific cart
$cartAlerts = AlertLog::query()->forOwner()
    ->where('cart_id', $cartId)
    ->orderByDesc('created_at')
    ->get();

// Alerts by type
$fraudAlerts = AlertLog::query()->forOwner()
    ->where('event_type', 'fraud')
    ->where('created_at', '>=', now()->subHours(24))
    ->get();
```

## Live Dashboard Page

The `LiveDashboardPage` provides real-time monitoring with auto-refresh.

### Features

- 10-second polling interval
- Live statistics overview
- Pending alerts table
- Recent activity feed
- High-value cart tracking

### Widgets

The live dashboard includes:

- **LiveStatsWidget** — Key metrics with 10s refresh
- **PendingAlertsWidget** — Table of unread alerts (15s refresh)
- **RecentActivityWidget** — Activity feed (15s refresh)

## Scheduled Commands

Monitoring requires scheduled commands:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Run cart monitoring (creates alerts)
    $schedule->command('cart:monitor')
        ->everyFiveMinutes();
    
    // Process pending alerts (send notifications)
    $schedule->command('cart:process-alerts')
        ->everyMinute();
}
```

### Manual Commands

```bash
# Run monitoring check
php artisan cart:monitor

# Process pending alerts
php artisan cart:process-alerts
```

## Alert Evaluation

The alert evaluation process:

1. **Detection** — `CartMonitor` detects events (abandonments, fraud, etc.)
2. **Rule Matching** — Match events against active `AlertRule` records
3. **Cooldown Check** — Skip rules in cooldown period
4. **Condition Evaluation** — Check if conditions are met
5. **Alert Creation** — Create `AlertLog` record
6. **Notification Dispatch** — Send to configured channels

### Custom Alert Evaluator

```php
namespace App\Services;

use AIArmada\FilamentCart\Models\AlertRule;
use AIArmada\FilamentCart\Models\Cart;

class CustomAlertEvaluator
{
    public function evaluate(Cart $cart): void
    {
        $rules = AlertRule::query()->forOwner()
            ->where('is_active', true)
            ->where('event_type', 'custom')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->isInCooldown()) {
                continue;
            }

            if ($this->matchesConditions($cart, $rule->conditions)) {
                $this->createAlert($rule, $cart);
            }
        }
    }

    private function matchesConditions(Cart $cart, array $conditions): bool
    {
        // Your custom logic
        return true;
    }

    private function createAlert(AlertRule $rule, Cart $cart): void
    {
        AlertLog::create([
            'alert_rule_id' => $rule->id,
            'event_type' => 'custom',
            'severity' => $rule->severity,
            'title' => "Custom alert: {$rule->name}",
            'message' => "Cart {$cart->identifier} triggered custom alert.",
            'event_data' => ['cart_value' => $cart->subtotal],
            'channels_notified' => $rule->getEnabledChannels(),
            'cart_id' => $cart->id,
        ]);

        $rule->markTriggered();
    }
}
```

## Alert Dispatcher

Send notifications for alerts:

```php
namespace App\Services;

use AIArmada\FilamentCart\Models\AlertLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class AlertNotifier
{
    public function notify(AlertLog $alert): void
    {
        $rule = $alert->alertRule;
        $channels = $alert->channels_notified ?? [];

        foreach ($channels as $channel) {
            match ($channel) {
                'email' => $this->sendEmail($alert, $rule),
                'slack' => $this->sendSlack($alert, $rule),
                'webhook' => $this->sendWebhook($alert, $rule),
                default => null,
            };
        }
    }

    private function sendEmail(AlertLog $alert, AlertRule $rule): void
    {
        foreach ($rule->email_recipients ?? [] as $recipient) {
            Mail::raw($alert->message, function ($message) use ($alert, $recipient) {
                $message->to($recipient)
                    ->subject("[{$alert->severity}] {$alert->title}");
            });
        }
    }

    private function sendSlack(AlertLog $alert, AlertRule $rule): void
    {
        if (! $rule->slack_webhook_url) {
            return;
        }

        $color = match ($alert->severity) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'good',
        };

        Http::post($rule->slack_webhook_url, [
            'attachments' => [[
                'color' => $color,
                'title' => $alert->title,
                'text' => $alert->message,
                'fields' => [
                    ['title' => 'Severity', 'value' => $alert->severity, 'short' => true],
                    ['title' => 'Type', 'value' => $alert->event_type, 'short' => true],
                ],
                'ts' => $alert->created_at->timestamp,
            ]],
        ]);
    }

    private function sendWebhook(AlertLog $alert, AlertRule $rule): void
    {
        if (! $rule->webhook_url) {
            return;
        }

        Http::post($rule->webhook_url, [
            'alert_id' => $alert->id,
            'event_type' => $alert->event_type,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'event_data' => $alert->event_data,
            'cart_id' => $alert->cart_id,
            'created_at' => $alert->created_at->toISOString(),
        ]);
    }
}
```

## Monitoring Widgets

When monitoring is enabled, these widgets are available:

- **LiveStatsWidget** — Real-time cart metrics
- **PendingAlertsWidget** — Unread alerts table
- **RecentActivityWidget** — Activity feed
- **FraudDetectionWidget** — Fraud risk alerts table

## Fraud Detection

The package includes basic fraud detection based on cart patterns:

### Detection Signals

1. **High Value + New Session** — Cart over $500 created within 10 minutes
2. **Multiple High-Quantity Items** — 10+ items with $1000+ total
3. **Unusual Patterns** — Configurable thresholds

### Fraud Configuration

```php
// config/filament-cart.php
'ai' => [
    'fraud_detection_enabled' => true,
    'fraud_score_threshold' => 0.7,
    'high_value_threshold_cents' => 10000, // $100
    'suspicious_patterns' => [
        'rapid_high_value' => [
            'min_value' => 50000,
            'max_minutes' => 10,
        ],
        'bulk_items' => [
            'min_items' => 10,
            'min_value' => 100000,
        ],
    ],
],
```

### Cart Fraud Scoring

Carts can have fraud scores:

```php
$cart = Cart::find($id);

$cart->fraud_score;      // 0.0 - 1.0
$cart->fraud_risk_level; // null, 'low', 'medium', 'high', 'reviewed'

// In FraudDetectionWidget
if ($cart->fraud_risk_level === 'high') {
    // Show block action
}
```

### Blocking Suspicious Carts

```php
// Mark cart as blocked
$metadata = $cart->metadata ?? [];
$metadata['blocked'] = true;
$metadata['blocked_at'] = now()->toISOString();
$metadata['blocked_reason'] = 'fraud_detection';

$cart->update(['metadata' => $metadata]);
```

### Marking as Reviewed

```php
// Mark cart as reviewed (false positive)
$cart->update([
    'fraud_risk_level' => 'reviewed',
    'metadata' => array_merge($cart->metadata ?? [], [
        'fraud_reviewed' => true,
        'fraud_reviewed_at' => now()->toISOString(),
    ]),
]);
```
