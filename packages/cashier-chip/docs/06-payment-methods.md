---
title: Payment Methods
---

# Payment Methods

CHIP uses **recurring tokens** as payment methods—equivalent to Stripe's PaymentMethods.

## Understanding Recurring Tokens

When a customer completes a checkout with `force_recurring = true`, CHIP returns a **recurring token**. This token can be used for:

- Subscription renewals
- One-click payments
- Automatic charges

## Retrieving Payment Methods

### All Payment Methods

```php
// Get all saved payment methods
$paymentMethods = $user->paymentMethods();

foreach ($paymentMethods as $method) {
    echo $method->token;
    echo $method->card_brand;
    echo $method->last_four;
}
```

### Default Payment Method

```php
// Get the default payment method
$default = $user->defaultPaymentMethod();

// Check if user has a default payment method
if ($user->hasDefaultPaymentMethod()) {
    // Can charge immediately
}
```

## Adding Payment Methods

### Via Setup Purchase

The recommended way to add payment methods:

```php
// Create zero-amount purchase to save card
$checkout = $user->createSetupPurchase([
    'success_url' => route('billing.methods'),
    'cancel_url' => route('billing.methods'),
]);

return redirect($checkout->checkout_url);
```

### Via Regular Checkout

Request a recurring token during any checkout:

```php
$checkout = $user->checkout(10000, [
    'recurring' => true,
]);
```

### Convenience URL

```php
// Get a URL to redirect for adding payment method
$url = $user->setupPaymentMethodUrl([
    'success_url' => route('billing.methods'),
    'cancel_url' => route('billing.methods'),
]);

return redirect($url);
```

### After Webhook

Recurring tokens are automatically saved when webhooks are received for successful payments with `force_recurring = true`.

## Managing Payment Methods

### Set Default Payment Method

```php
// Update the default payment method
$user->updateDefaultPaymentMethod($recurringToken);
```

### Delete Payment Method

```php
// Delete a specific payment method
$user->deletePaymentMethod($recurringToken);

// Note: CHIP may not support revoking recurring tokens via API
// This removes the local record only
```

### Add Payment Method Directly

```php
// Add a recurring token received from webhook
$user->addPaymentMethod($recurringToken, [
    'card_brand' => 'visa',
    'last_four' => '4242',
    'is_default' => true,
]);
```

## Payment Method Properties

Each payment method record contains:

| Property | Description |
|----------|-------------|
| `token` | The recurring token string |
| `card_brand` | Card brand (visa, mastercard, etc.) |
| `last_four` | Last 4 digits of card |
| `expiry_month` | Card expiry month |
| `expiry_year` | Card expiry year |
| `is_default` | Whether this is the default method |

## Charging with Payment Methods

### Using Default Method

```php
// Charge using default payment method
$payment = $user->charge(10000);
```

### Using Specific Method

```php
// Charge using a specific recurring token
$payment = $user->chargeWithRecurringToken(
    amount: 10000,
    recurringToken: $recurringToken,
    options: [
        'reference' => 'Order #123',
    ]
);
```

## Checking Payment Method Availability

```php
// Check if can charge immediately (has valid payment method)
if ($user->hasDefaultPaymentMethod()) {
    $payment = $user->charge(10000);
} else {
    // Redirect to add payment method
    return redirect()->route('billing.add-method');
}
```

## Payment Method Events

Listen for payment method changes:

```php
use AIArmada\CashierChip\Events\PaymentMethodAdded;
use AIArmada\CashierChip\Events\PaymentMethodRemoved;
use AIArmada\CashierChip\Events\DefaultPaymentMethodChanged;

// In EventServiceProvider
protected $listen = [
    PaymentMethodAdded::class => [
        SendPaymentMethodAddedNotification::class,
    ],
];
```

## Database Schema

Payment methods are stored in `chip_payment_methods`:

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `billable_id` | uuid | Foreign key to billable |
| `billable_type` | string | Billable model class |
| `token` | string | CHIP recurring token |
| `card_brand` | string | Card brand |
| `last_four` | string | Last 4 digits |
| `expiry_month` | int | Expiry month |
| `expiry_year` | int | Expiry year |
| `is_default` | boolean | Default flag |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
