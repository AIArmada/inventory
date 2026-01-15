---
title: One-off Charges
---

# One-off Charges

Process single payments without creating subscriptions.

## Simple Charges

### Charge with Default Payment Method

```php
// Charge 100.00 MYR (amounts are in cents)
$payment = $user->charge(10000);

// Check payment status
if ($payment->isSuccessful()) {
    // Payment completed
}
```

### Charge with Description

```php
$payment = $user->charge(10000, [
    'reference' => 'Product Purchase - Order #123',
]);
```

### Charge with Specific Payment Method

```php
$payment = $user->charge(10000, [
    'recurring_token' => $recurringToken,
]);
```

## Recurring Token Charges

For saved payment methods (recurring tokens):

```php
// Charge using a specific recurring token
$payment = $user->chargeWithRecurringToken(
    amount: 10000,
    recurringToken: $user->defaultPaymentMethod(),
    options: [
        'reference' => 'Monthly Service Fee',
    ]
);
```

## Checkout Sessions

For one-off charges that require the customer to enter payment details:

```php
// Create checkout session
$checkout = $user->checkout(10000, [
    'reference' => 'Premium Plan',
]);

// Redirect to CHIP checkout page
return $checkout->redirect();
```

See [Checkout Sessions](checkout.md) for detailed checkout documentation.

## Payment Object

All charge methods return a `Payment` object:

```php
$payment = $user->charge(10000);

// Get payment ID
$id = $payment->id();

// Get status
$status = $payment->status();

// Check status methods
$payment->isSuccessful();   // Payment completed
$payment->isPending();      // Awaiting payment
$payment->isFailed();       // Payment failed

// Get amount (in cents)
$amount = $payment->rawAmount();

// Get checkout URL (for redirect payments)
$url = $payment->checkoutUrl();

// Get underlying CHIP Purchase object
$purchase = $payment->asChipPurchase();
```

## Payment Statuses

| Status | Description |
|--------|-------------|
| `paid` | Payment completed successfully |
| `pending` | Awaiting customer action |
| `error` | Payment failed |
| `expired` | Payment link expired |
| `refunded` | Payment was refunded |

## Handling Failures

```php
use AIArmada\CashierChip\Exceptions\PaymentFailure;

try {
    $payment = $user->charge(10000);
} catch (PaymentFailure $e) {
    // Handle payment failure
    $message = $e->getMessage();
}
```

## Refunds

CHIP refunds are processed through the CHIP dashboard or API:

```php
// Using the CHIP package directly
use AIArmada\Chip\Facades\ChipCollect;

ChipCollect::refund($purchaseId, [
    'amount' => 5000, // Partial refund in cents
    'reason' => 'Customer request',
]);
```

## Receipts

Configure automatic receipt sending:

```php
$payment = $user->charge(10000, [
    'send_receipt' => true,
]);
```

Or send manually after payment:

```php
use AIArmada\Chip\Facades\ChipCollect;

ChipCollect::sendReceipt($purchaseId);
```

## Currency

All amounts are in the smallest currency unit (cents for MYR):

| Display Amount | Code Amount |
|----------------|-------------|
| RM 1.00 | 100 |
| RM 10.00 | 1000 |
| RM 100.00 | 10000 |

Format amounts for display:

```php
use AIArmada\CashierChip\CashierChip;

$formatted = CashierChip::formatAmount(10000, 'MYR');
// "RM 100.00"
```
