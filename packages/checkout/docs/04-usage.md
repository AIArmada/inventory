---
title: Usage
---

# Usage

## Basic Checkout Flow

### Starting a Checkout

```php
use AIArmada\Checkout\Facades\Checkout;

// Start checkout from a cart
$session = Checkout::startCheckout($cartId);

// With customer ID
$session = Checkout::startCheckout($cartId, $customerId);
```

### Processing Checkout

Process the entire checkout flow in one call:

```php
$result = Checkout::processCheckout($session);

if ($result->success) {
    return redirect()->route('orders.show', $result->orderId);
}

if ($result->requiresRedirect()) {
    return redirect($result->redirectUrl);
}

return back()->withErrors($result->errors);
```

### Step-by-Step Processing

Process individual steps for more control:

```php
// Get current step
$currentStep = Checkout::getCurrentStep($session);

// Process a specific step
$session = Checkout::processStep($session, 'validate_cart');
$session = Checkout::processStep($session, 'calculate_pricing');

// Check if can proceed
if (Checkout::canProceed($session)) {
    $session = Checkout::processStep($session, 'process_payment');
}
```

## Working with Sessions

### Resuming Checkout

```php
// Resume an existing session
$session = Checkout::resumeCheckout($sessionId);
```

### Canceling Checkout

```php
$session = Checkout::cancelCheckout($session);
```

### Session Properties

```php
$session->id;               // Session ID
$session->cart_id;          // Associated cart
$session->order_id;         // Created order (after completion)
$session->status;           // Current status (CheckoutState state object)
$session->current_step;     // Current step name
$session->completed_steps;  // Array of completed step names
$session->shipping_address; // Shipping address data
$session->billing_address;  // Billing address data
$session->payment_method;   // Selected payment method
$session->payment_gateway;  // Payment gateway used
$session->expires_at;       // Session expiration
```

### Cart Snapshot Schema

The checkout session stores a normalized cart snapshot in `cart_snapshot`:

```json
{
    "items": [
        {
            "id": "SKU-001",
            "name": "Product Name",
            "price": 4999,
            "quantity": 2,
            "attributes": {
                "weight": 250,
                "dimensions": {"length": 20, "width": 10, "height": 5}
            },
            "conditions": [],
            "associated_model": {
                "class": "App\\Models\\Product",
                "id": "uuid",
                "data": {"sku": "SKU-001"}
            }
        }
    ],
    "subtotal": 9998,
    "total": 9998,
    "item_count": 2,
    "captured_at": "2026-01-28T10:00:00+00:00"
}
```

Notes:
- `price` and totals are stored in the smallest currency unit (cents).
- `attributes.weight` is in grams when provided.
- `associated_model` is populated when cart items are linked to Eloquent models.

## Checkout States

The checkout session uses Spatie Model States for robust status management. States define allowed transitions and provide behavior methods.

### Available States

| State | Description | Terminal |
|-------|-------------|----------|
| `Pending` | Initial state, checkout not started | No |
| `Processing` | Checkout steps are being executed | No |
| `AwaitingPayment` | Waiting for payment gateway response | No |
| `PaymentProcessing` | Payment is being processed | No |
| `PaymentFailed` | Payment attempt failed | No |
| `Completed` | Checkout finished successfully | Yes |
| `Cancelled` | Checkout was cancelled | Yes |
| `Expired` | Session TTL exceeded | Yes |

### State Behavior Methods

Each state provides methods to check available actions:

```php
use AIArmada\Checkout\States\Pending;
use AIArmada\Checkout\States\Completed;

// Check if session can be cancelled
if ($session->status->canCancel()) {
    Checkout::cancelCheckout($session);
}

// Check if session can be modified
if ($session->status->canModify()) {
    $session->update(['shipping_address' => $newAddress]);
}

// Check if payment can be retried
if ($session->status->canRetryPayment()) {
    Checkout::retryPayment($session);
}

// Check if checkout is in a terminal state
if ($session->status->isTerminal()) {
    // No further transitions possible
}
```

### State Transitions

States enforce valid transitions:

```
Pending → Processing → AwaitingPayment → Completed
                    → PaymentProcessing → Completed
                                       → PaymentFailed → Processing (retry)
                    → Cancelled
                    → Expired
```

### Checking Status

```php
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;

// Check if completed
if ($session->status instanceof Completed) {
    // Show order confirmation
}

// Check if payment failed
if ($session->status instanceof PaymentFailed) {
    // Offer retry option
}

// Get status name for display
$statusName = $session->status->name();  // 'pending', 'completed', etc.
$statusLabel = $session->status->label(); // Localized label
$statusColor = $session->status->color(); // Filament badge color
$statusIcon = $session->status->icon();   // Heroicon name
```

## Checkout Result

The `CheckoutResult` data object provides checkout outcome:

```php
$result = Checkout::processCheckout($session);

// Success check
$result->success;        // bool

// Status
$result->status;         // CheckoutState state object

// Check specific status
use AIArmada\Checkout\States\Completed;
if ($result->status instanceof Completed) {
    // Checkout was successful
}

// IDs
$result->sessionId;      // Checkout session ID
$result->orderId;        // Created order ID (if successful)
$result->paymentId;      // Payment ID

// Redirect handling
$result->redirectUrl;    // Payment redirect URL
$result->requiresRedirect(); // Check if redirect needed

// Errors
$result->message;        // Error message
$result->errors;         // Validation errors array

// Metadata
$result->metadata;       // Additional data
```

## Payment Handling

### Retry Failed Payment

```php
$result = Checkout::retryPayment($session);

if ($result->success) {
    return redirect()->route('orders.show', $result->orderId);
}
```

### Handling Webhooks

For payment gateway webhooks, use the respective gateway's webhook handler:

```php
// Example: Chip webhook handling
use AIArmada\Chip\Facades\Chip;

Route::post('/webhook/chip', function (Request $request) {
    $result = Chip::handleWebhook($request);
    
    if ($result->isPaid()) {
        // Payment confirmed, checkout will auto-complete
    }
    
    return response()->json(['status' => 'ok']);
});
```

## Address Handling

### Setting Addresses

```php
$session->update([
    'shipping_address' => [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'line1' => '123 Main St',
        'city' => 'Kuala Lumpur',
        'state' => 'WP',
        'postcode' => '50000',
        'country' => 'MY',
        'phone' => '+60123456789',
        'email' => 'john@example.com',
    ],
    'billing_address' => [
        // Same structure as shipping
    ],
]);
```

### Using Same as Shipping

```php
$session->update([
    'billing_address' => $session->shipping_address,
]);
```

## Events

The checkout package dispatches these events:

```php
use AIArmada\Checkout\Events\CheckoutStarted;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutStepCompleted;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Events\PaymentProcessed;
use AIArmada\Checkout\Events\PaymentFailed;

// Listen in EventServiceProvider
protected $listen = [
    CheckoutCompleted::class => [
        SendOrderConfirmation::class,
        NotifyWarehouse::class,
    ],
    PaymentFailed::class => [
        LogPaymentFailure::class,
        NotifyCustomerSupport::class,
    ],
    CheckoutPaymentCompleted::class => [
        UpdateOrderPaymentStatus::class,
    ],
];
```

## Error Handling

### Handling Exceptions

```php
use AIArmada\Checkout\Exceptions\CheckoutException;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Exceptions\PaymentException;

try {
    $result = Checkout::processCheckout($session);
} catch (InvalidCheckoutStateException $e) {
    // Session expired, already completed, etc.
    Log::error('Checkout state error', $e->context);
    return back()->with('error', $e->getMessage());
} catch (PaymentException $e) {
    // Payment processing failed
    Log::error('Payment error', $e->context);
    return back()->with('error', 'Payment failed. Please try again.');
} catch (CheckoutException $e) {
    // General checkout error
    Log::error('Checkout error', $e->context);
    return back()->with('error', $e->getMessage());
}
```

### Common Error Scenarios

| Exception | Cause | Resolution |
|-----------|-------|------------|
| `InvalidCheckoutStateException::sessionExpired` | Session TTL exceeded | Start new checkout |
| `InvalidCheckoutStateException::emptyCart` | Cart has no items | Add items to cart |
| `PaymentException::paymentFailed` | Payment declined | Retry with different method |
| `InventoryException::insufficientStock` | Item out of stock | Update quantities |
