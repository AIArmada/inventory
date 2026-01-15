---
title: Cart Recovery
---

# Cart Recovery

The package includes a comprehensive cart recovery system for re-engaging customers who abandoned their carts. It supports multiple channels, A/B testing, and detailed tracking.

## Overview

The recovery system consists of:

1. **Recovery Campaigns** — Define targeting rules and strategies
2. **Recovery Templates** — Email, SMS, and push notification content
3. **Recovery Attempts** — Individual message tracking
4. **Recovery Scheduler** — Schedules attempts based on campaign rules
5. **Recovery Dispatcher** — Sends messages and tracks engagement

## Enabling Recovery

Recovery features are controlled by configuration:

```php
// config/filament-cart.php
'features' => [
    'recovery' => true,           // Enable recovery system
    'ai_recovery' => true,        // Enable AI-powered recovery
    'recovery_campaigns' => true, // Enable campaign management
],
```

When enabled, the following resources are registered:
- RecoveryCampaignResource
- RecoveryTemplateResource

## Recovery Campaigns

Campaigns define the rules for when and how to contact abandoned cart customers.

### Creating a Campaign

```php
use AIArmada\FilamentCart\Models\RecoveryCampaign;

$campaign = RecoveryCampaign::create([
    'name' => 'Standard Recovery',
    'description' => 'Default recovery campaign for abandoned carts',
    'status' => 'active',
    
    // Trigger settings
    'trigger_type' => 'abandonment',
    'trigger_delay_minutes' => 60, // Wait 1 hour after abandonment
    'max_attempts' => 3,
    'attempt_interval_hours' => 24, // 1 day between attempts
    
    // Targeting
    'min_cart_value_cents' => 2500, // $25 minimum
    'max_cart_value_cents' => null, // No maximum
    'min_items' => 1,
    'max_items' => null,
    'target_segments' => ['returning'],
    'exclude_segments' => ['unsubscribed'],
    
    // Strategy
    'strategy' => 'email', // email, sms, push, multi_channel
    'offer_discount' => true,
    'discount_type' => 'percentage', // percentage, fixed
    'discount_value' => 10, // 10%
    'offer_free_shipping' => false,
    'urgency_hours' => 48, // Offer expires in 48 hours
    
    // A/B Testing
    'ab_testing_enabled' => true,
    'ab_test_split_percent' => 50, // 50% get variant
    'control_template_id' => $controlTemplate->id,
    'variant_template_id' => $variantTemplate->id,
    
    // Schedule
    'starts_at' => now(),
    'ends_at' => now()->addMonths(3),
]);
```

### Campaign Status

Campaigns have three statuses:
- `draft` — Not active, being configured
- `active` — Running and scheduling attempts
- `paused` — Temporarily stopped

```php
// Check if campaign is active
if ($campaign->isActive()) {
    // Will return true only if:
    // - status === 'active'
    // - starts_at <= now (if set)
    // - ends_at >= now (if set)
}
```

### Campaign Metrics

Campaigns track performance metrics:

```php
$campaign->total_targeted;      // Carts targeted
$campaign->total_sent;          // Messages sent
$campaign->total_opened;        // Messages opened
$campaign->total_clicked;       // Links clicked
$campaign->total_recovered;     // Carts recovered
$campaign->recovered_revenue_cents; // Revenue from recoveries

// Calculated rates
$campaign->getOpenRate();       // 0.0 - 1.0
$campaign->getClickRate();      // 0.0 - 1.0
$campaign->getConversionRate(); // 0.0 - 1.0
$campaign->getAverageRecoveredValue(); // Average order value in cents
```

## Recovery Templates

Templates define the content for recovery messages.

### Creating a Template

```php
use AIArmada\FilamentCart\Models\RecoveryTemplate;

$template = RecoveryTemplate::create([
    'name' => 'Cart Reminder Email',
    'description' => 'First recovery email with discount offer',
    'type' => 'email', // email, sms, push
    'status' => 'active',
    'is_default' => false,
    
    // Email fields
    'email_subject' => 'Hey {{customer_name}}, you forgot something!',
    'email_preheader' => 'Your cart is waiting...',
    'email_body_html' => '
        <h1>Hi {{customer_name}},</h1>
        <p>You left {{cart_item_count}} items in your cart:</p>
        <pre>{{cart_items}}</pre>
        <p>Total: {{cart_total}}</p>
        <p>Use code <strong>{{discount_code}}</strong> for {{discount_amount}} off!</p>
        <p>Offer expires: {{expiry_time}}</p>
        <a href="{{cart_url}}">Complete Your Order</a>
        {{tracking_pixel}}
    ',
    'email_body_text' => 'Hi {{customer_name}}, Complete your order: {{cart_url}}',
    'email_from_name' => 'Your Store',
    'email_from_email' => 'noreply@yourstore.com',
    
    // SMS fields (if type is sms)
    'sms_body' => 'Hi {{customer_name}}, your cart is waiting! Use {{discount_code}} for {{discount_amount}} off: {{cart_url}}',
    
    // Push fields (if type is push)
    'push_title' => 'Complete Your Order',
    'push_body' => '{{cart_item_count}} items waiting in your cart',
    'push_icon' => '/icons/cart.png',
    'push_action_url' => '{{cart_url}}',
]);
```

### Template Variables

Templates support variable substitution using `{{variable}}` syntax:

| Variable | Description |
|----------|-------------|
| `{{customer_name}}` | Customer's name or "Customer" |
| `{{cart_url}}` | Link to recover cart with tracking |
| `{{cart_items}}` | Formatted list of cart items |
| `{{cart_total}}` | Total cart value formatted |
| `{{cart_item_count}}` | Number of items in cart |
| `{{discount_code}}` | Generated discount code |
| `{{discount_amount}}` | Discount value formatted |
| `{{expiry_time}}` | When offer expires |
| `{{tracking_pixel}}` | 1x1 tracking image for opens |

### Rendering Templates

```php
$variables = [
    'customer_name' => 'John',
    'cart_url' => 'https://store.com/cart?recovery=abc123',
    'cart_total' => '$99.00',
    // ...
];

$subject = $template->renderSubject($variables);
$html = $template->renderHtmlBody($variables);
$text = $template->renderTextBody($variables);
$sms = $template->renderSmsBody($variables);
$push = $template->renderPush($variables);
// Returns: ['title' => '...', 'body' => '...', 'icon' => '...', 'action_url' => '...']
```

### Template Metrics

```php
$template->times_used;      // Times sent
$template->times_opened;    // Opens tracked
$template->times_clicked;   // Clicks tracked
$template->times_converted; // Conversions

$template->getOpenRate();       // 0.0 - 1.0
$template->getClickRate();      // 0.0 - 1.0
$template->getConversionRate(); // 0.0 - 1.0
```

## Recovery Attempts

Each scheduled or sent recovery message is tracked as an attempt.

### Attempt Lifecycle

```
scheduled → queued → sent → [delivered] → [opened] → [clicked] → [converted]
                ↓
              failed
```

### Attempt Fields

```php
$attempt->campaign_id;        // Parent campaign
$attempt->cart_id;            // Target cart
$attempt->template_id;        // Template used
$attempt->recipient_email;    // Email address
$attempt->recipient_phone;    // Phone number
$attempt->recipient_name;     // Customer name
$attempt->channel;            // email, sms, push
$attempt->status;             // scheduled, queued, sent, etc.
$attempt->attempt_number;     // 1, 2, 3...
$attempt->is_control;         // Control group (A/B)
$attempt->is_variant;         // Variant group (A/B)
$attempt->discount_code;      // Generated code
$attempt->discount_value_cents; // Discount amount
$attempt->free_shipping_offered; // Free shipping?
$attempt->offer_expires_at;   // When offer expires
$attempt->cart_value_cents;   // Cart value at time
$attempt->cart_items_count;   // Items at time
$attempt->scheduled_for;      // When to send
$attempt->sent_at;            // When sent
$attempt->opened_at;          // When opened
$attempt->clicked_at;         // When clicked
$attempt->converted_at;       // When converted
```

### Status Helpers

```php
$attempt->isScheduled();  // Waiting to send
$attempt->isSent();       // Sent (any state after)
$attempt->isOpened();     // Opened (any state after)
$attempt->isClicked();    // Clicked (any state after)
$attempt->isConverted();  // Successfully recovered
$attempt->isFailed();     // Failed or bounced
```

## RecoveryScheduler Service

The scheduler finds eligible carts and creates attempts.

```php
use AIArmada\FilamentCart\Services\RecoveryScheduler;

$scheduler = app(RecoveryScheduler::class);

// Schedule for a specific campaign
$scheduled = $scheduler->scheduleForCampaign($campaign);
echo "Scheduled {$scheduled} recovery attempts";

// Process all scheduled attempts that are due
$result = $scheduler->processScheduledAttempts();
echo "Processed {$result['processed']}, failed {$result['failed']}";

// Schedule next attempt after a previous one
$nextAttempt = $scheduler->scheduleNextAttempt($previousAttempt);

// Cancel all pending attempts for a cart
$cancelled = $scheduler->cancelAttemptsForCart($cartId);
```

### Cart Eligibility

Carts are eligible for recovery when:

1. `checkout_abandoned_at` is set
2. `recovered_at` is null
3. Has at least one item
4. Abandoned longer than `trigger_delay_minutes`
5. Cart value within `min/max_cart_value_cents`
6. Item count within `min/max_items`
7. Not already in this campaign
8. Has email or phone in metadata

## RecoveryDispatcher Service

The dispatcher sends messages and tracks engagement.

```php
use AIArmada\FilamentCart\Services\RecoveryDispatcher;

$dispatcher = app(RecoveryDispatcher::class);

// Dispatch a queued attempt
$success = $dispatcher->dispatch($attempt);

// Record engagement
$dispatcher->recordOpen($attempt);
$dispatcher->recordClick($attempt);
$dispatcher->recordConversion($attempt, $orderValueCents);

// Generate tracking URLs
$urls = $dispatcher->generateTrackingUrls($attempt);
// Returns: ['open' => '...', 'click' => '...', 'cart' => '...']
```

### Channel Dispatch

The dispatcher routes to channel-specific methods:

```php
// Email dispatch
$dispatcher->dispatchEmail($attempt, $template, $variables);

// SMS dispatch (requires provider integration)
$dispatcher->dispatchSms($attempt, $template, $variables);

// Push dispatch (requires provider integration)
$dispatcher->dispatchPush($attempt, $template, $variables);
```

### Custom Channel Integration

To integrate with SMS or push providers, extend the dispatcher:

```php
namespace App\Services;

use AIArmada\FilamentCart\Services\RecoveryDispatcher;
use Twilio\Rest\Client;

class CustomRecoveryDispatcher extends RecoveryDispatcher
{
    public function dispatchSms($attempt, $template, $variables): bool
    {
        if (! $attempt->recipient_phone) {
            $attempt->markAsFailed('No phone number');
            return false;
        }

        $body = $template->renderSmsBody($variables);
        
        $twilio = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        
        $message = $twilio->messages->create(
            $attempt->recipient_phone,
            [
                'from' => config('services.twilio.from'),
                'body' => $body,
            ]
        );
        
        $attempt->markAsSent($message->sid);
        
        return true;
    }
}
```

Register in a service provider:

```php
$this->app->singleton(
    RecoveryDispatcher::class,
    CustomRecoveryDispatcher::class
);
```

## Scheduled Commands

Recovery requires scheduled commands to run:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Schedule recovery attempts for all active campaigns
    $schedule->command('cart:schedule-recovery')
        ->everyFifteenMinutes();
    
    // Process scheduled attempts that are due
    $schedule->command('cart:process-recovery')
        ->everyMinute();
}
```

### Manual Commands

```bash
# Schedule for all active campaigns
php artisan cart:schedule-recovery

# Process scheduled attempts
php artisan cart:process-recovery
```

## Tracking Endpoints

The package can provide tracking endpoints for opens/clicks:

```php
// routes/web.php
use AIArmada\FilamentCart\Services\RecoveryDispatcher;

Route::get('/recovery/track/open/{attempt}', function (string $attempt) {
    $attempt = RecoveryAttempt::findOrFail($attempt);
    app(RecoveryDispatcher::class)->recordOpen($attempt);
    
    // Return 1x1 transparent GIF
    return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'))
        ->header('Content-Type', 'image/gif');
})->name('cart.recovery.track.open');

Route::get('/recovery/track/click/{attempt}', function (string $attempt) {
    $attempt = RecoveryAttempt::findOrFail($attempt);
    app(RecoveryDispatcher::class)->recordClick($attempt);
    
    return redirect($attempt->cart->getRecoveryUrl());
})->name('cart.recovery.track.click');
```

## Recording Conversions

When a customer completes checkout after recovery:

```php
use AIArmada\FilamentCart\Models\RecoveryAttempt;
use AIArmada\FilamentCart\Services\RecoveryDispatcher;

// In your checkout completion logic
if ($recoveryId = request('recovery')) {
    $attempt = RecoveryAttempt::find($recoveryId);
    
    if ($attempt && ! $attempt->isConverted()) {
        app(RecoveryDispatcher::class)->recordConversion(
            $attempt,
            $order->total_cents
        );
    }
}
```

## A/B Testing

Campaigns support A/B testing with control and variant templates:

```php
$campaign = RecoveryCampaign::create([
    'name' => 'A/B Test Campaign',
    'ab_testing_enabled' => true,
    'ab_test_split_percent' => 30, // 30% get variant
    'control_template_id' => $controlTemplate->id,
    'variant_template_id' => $variantTemplate->id,
    // ...
]);
```

When scheduling attempts:
- 70% receive the control template
- 30% receive the variant template
- Each attempt is marked `is_control` or `is_variant`

### Analyzing A/B Results

```php
// Compare performance
$controlStats = RecoveryAttempt::query()
    ->where('campaign_id', $campaign->id)
    ->where('is_control', true)
    ->selectRaw('
        COUNT(*) as total,
        SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
        SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
    ')
    ->first();

$variantStats = RecoveryAttempt::query()
    ->where('campaign_id', $campaign->id)
    ->where('is_variant', true)
    ->selectRaw('
        COUNT(*) as total,
        SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opens,
        SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) as conversions
    ')
    ->first();

// Calculate rates and determine winner
$controlConversionRate = $controlStats->conversions / $controlStats->total;
$variantConversionRate = $variantStats->conversions / $variantStats->total;
```

## Events

The recovery system dispatches events for integration:

```php
use AIArmada\FilamentCart\Events\RecoveryAttemptSent;
use AIArmada\FilamentCart\Events\RecoveryAttemptOpened;
use AIArmada\FilamentCart\Events\RecoveryAttemptClicked;
use AIArmada\FilamentCart\Events\CartRecovered;

// Listen for events
Event::listen(RecoveryAttemptSent::class, function ($event) {
    // $event->attempt
});

Event::listen(CartRecovered::class, function ($event) {
    // $event->attempt
    // $event->orderValueCents
});
```

## Recovery Widgets

When recovery is enabled, these widgets are available:

- **RecoveryPerformanceWidget** — Strategy breakdown and metrics
- **CampaignPerformanceWidget** — Campaign stats overview
- **RecoveryFunnelWidget** — Visual funnel from targeted to recovered
- **StrategyComparisonWidget** — Compare channel performance
- **RecoveryOptimizerWidget** — AI-powered recovery queue
- **AbandonedCartsWidget** — Table of carts to recover
