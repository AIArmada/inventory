---
title: Subscriptions
---

# Subscriptions

Cashier CHIP provides local subscription management. Unlike Stripe, CHIP doesn't have native subscription support—subscriptions are managed in your database and charged using recurring tokens.

## How It Works

1. Customer subscribes → Creates local subscription record
2. Subscription stores → Price, interval, next billing date, recurring token
3. Scheduled job → Charges recurring token on billing date
4. Webhook confirms → Updates subscription status

## Creating Subscriptions

### Basic Subscription

```php
// Create a monthly subscription
$subscription = $user->newSubscription('default', 'price_monthly')
    ->monthly()
    ->create();
```

### With Trial Period

```php
// 14-day trial
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialDays(14)
    ->create();

// Trial until specific date
$subscription = $user->newSubscription('default', 'price_monthly')
    ->trialUntil(now()->addMonth())
    ->create();
```

### With Payment Method

```php
// Use specific recurring token
$subscription = $user->newSubscription('default', 'price_monthly')
    ->create($recurringToken);

// Use default payment method
$subscription = $user->newSubscription('default', 'price_monthly')
    ->create($user->defaultPaymentMethod());
```

### Via Checkout

Collect payment details during subscription:

```php
$checkout = $user->newSubscription('default', 'price_monthly')
    ->checkout([
        'success_url' => route('subscription.success'),
        'cancel_url' => route('subscription.cancel'),
    ]);

return $checkout->redirect();
```

## Billing Intervals

```php
// Daily billing
$subscription = $user->newSubscription('default', 'price_daily')
    ->daily()
    ->create();

// Weekly billing
$subscription = $user->newSubscription('default', 'price_weekly')
    ->weekly()
    ->create();

// Monthly billing
$subscription = $user->newSubscription('default', 'price_monthly')
    ->monthly()
    ->create();

// Yearly billing
$subscription = $user->newSubscription('default', 'price_yearly')
    ->yearly()
    ->create();

// Custom interval
$subscription = $user->newSubscription('default', 'price_biweekly')
    ->billingInterval('week', 2)  // Every 2 weeks
    ->create();
```

## Checking Subscription Status

### Is Subscribed

```php
// Check if user has any active subscription
if ($user->subscribed()) {
    // Has active subscription
}

// Check specific subscription type
if ($user->subscribed('default')) {
    // Has active 'default' subscription
}

// Check for specific price
if ($user->subscribedToPrice('price_premium')) {
    // Subscribed to premium price
}
```

### Subscription States

```php
$subscription = $user->subscription('default');

// Active and valid
if ($subscription->valid()) {
    // Active, on trial, or in grace period
}

// Currently active (not canceled, not expired)
if ($subscription->active()) {
    // Currently active
}

// On trial period
if ($subscription->onTrial()) {
    // In trial period
    echo $subscription->trial_ends_at;
}

// Canceled (ends_at is set)
if ($subscription->canceled()) {
    // Has been canceled
}

// In grace period (canceled but still active)
if ($subscription->onGracePeriod()) {
    // Canceled but grace period hasn't ended
    echo $subscription->ends_at;
}

// Completely ended
if ($subscription->ended()) {
    // No longer active
}

// Recurring (not on trial, not canceled)
if ($subscription->recurring()) {
    // Will renew automatically
}
```

### Other States

```php
// Payment failed
if ($subscription->pastDue()) {
    // Payment failed, needs attention
}

// Incomplete (initial payment pending)
if ($subscription->incomplete()) {
    // Waiting for first payment
}

// Has any payment issues
if ($subscription->hasIncompletePayment()) {
    // Past due or incomplete
}
```

## Managing Subscriptions

### Cancel at Period End

```php
// Cancel at end of billing period
$subscription->cancel();

// User still has access until ends_at
if ($subscription->onGracePeriod()) {
    echo "Access until: " . $subscription->ends_at->format('M d, Y');
}
```

### Cancel Immediately

```php
// Cancel immediately (no grace period)
$subscription->cancelNow();
```

### Cancel at Specific Time

```php
// Cancel at a specific date
$subscription->cancelAt(now()->addDays(7));
```

### Resume Canceled Subscription

```php
// Resume if still in grace period
if ($subscription->onGracePeriod()) {
    $subscription->resume();
}
```

## Changing Plans

### Swap Price

```php
// Swap to a different price
$subscription->swap('price_yearly');

// Swap to multiple prices
$subscription->swap([
    'price_base',
    'price_addon' => ['quantity' => 2],
]);
```

### Change Quantity

```php
// Set specific quantity
$subscription->updateQuantity(5);

// Increment
$subscription->incrementQuantity();
$subscription->incrementQuantity(3);

// Decrement
$subscription->decrementQuantity();
$subscription->decrementQuantity(2);
```

## Subscription Items

For subscriptions with multiple line items:

```php
// Check if has multiple prices
if ($subscription->hasMultiplePrices()) {
    // Has multiple items
}

// Get specific item
$item = $subscription->findItemOrFail('price_addon');

// Update item quantity
$item->updateQuantity(3);

// Swap item price
$item->swap('price_addon_v2');
```

## Trial Management

### Check Trial Status

```php
if ($subscription->onTrial()) {
    $daysRemaining = now()->diffInDays($subscription->trial_ends_at);
    echo "Trial ends in {$daysRemaining} days";
}

if ($subscription->hasExpiredTrial()) {
    // Trial has ended
}
```

### Extend Trial

```php
// Extend trial period
$subscription->extendTrial(now()->addDays(7));
```

### Skip Trial

```php
// Skip trial when creating
$subscription = $user->newSubscription('default', 'price_monthly')
    ->skipTrial()
    ->create();

// End trial on existing subscription
$subscription->endTrial();
```

## Multiple Subscriptions

Users can have multiple subscription types:

```php
// Create different subscription types
$user->newSubscription('default', 'price_monthly')->create();
$user->newSubscription('swimming', 'price_swimming_monthly')->create();
$user->newSubscription('parking', 'price_parking_monthly')->create();

// Check specific subscriptions
if ($user->subscribed('swimming')) {
    // Has swimming access
}

// Get all subscriptions
$subscriptions = $user->subscriptions;
```

## Billing Dates

```php
$subscription = $user->subscription('default');

// Current period start
$start = $subscription->currentPeriodStart();

// Current period end (next billing date)
$end = $subscription->currentPeriodEnd();

// Next billing date
echo $subscription->next_billing_at->format('M d, Y');
```

## Charging Subscriptions

Subscriptions are charged via a scheduled job:

```php
// app/Console/Kernel.php
$schedule->job(new ChargeSubscriptions)->daily();
```

Or manually:

```php
// Charge a specific subscription
$payment = $subscription->charge();

// Charge with custom amount
$payment = $subscription->charge(9900);
```

## Subscription Events

```php
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Events\SubscriptionCanceled;
use AIArmada\CashierChip\Events\SubscriptionResumed;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Events\SubscriptionPaymentFailed;

protected $listen = [
    SubscriptionCreated::class => [
        SendWelcomeEmail::class,
    ],
    SubscriptionPaymentFailed::class => [
        NotifyPaymentFailure::class,
    ],
];
```

## Scopes

Query subscriptions efficiently:

```php
use AIArmada\CashierChip\Subscription;

// Active subscriptions
$active = Subscription::active()->get();

// Canceled subscriptions
$canceled = Subscription::canceled()->get();

// On trial
$trialing = Subscription::onTrial()->get();

// Past due
$pastDue = Subscription::pastDue()->get();

// Ended (completely finished)
$ended = Subscription::ended()->get();
```

## Database Schema

### chip_subscriptions

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `user_id` | uuid | Foreign key to user |
| `type` | string | Subscription type name |
| `chip_id` | string | Local subscription ID |
| `chip_status` | string | Status (active, canceled, etc.) |
| `chip_price` | string | Price identifier |
| `quantity` | int | Quantity |
| `trial_ends_at` | timestamp | Trial end date |
| `ends_at` | timestamp | Cancellation date |
| `next_billing_at` | timestamp | Next charge date |
| `billing_interval` | string | Interval (day, week, month, year) |
| `recurring_token` | string | Payment method |

### chip_subscription_items

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `subscription_id` | uuid | Foreign key |
| `chip_id` | string | Item ID |
| `chip_product` | string | Product identifier |
| `chip_price` | string | Price identifier |
| `quantity` | int | Quantity |
| `unit_amount` | int | Unit price in cents |
