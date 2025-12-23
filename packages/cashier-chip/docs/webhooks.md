---
title: Webhooks
---

# Webhooks

Cashier CHIP handles incoming CHIP webhooks to update payment statuses, save recurring tokens, and manage subscription states.

## Webhook Route

The package registers a webhook route at:

```
POST /chip/webhook
```

Configure your CHIP dashboard to send webhooks to this URL.

## Configuration

### CSRF Protection

Exclude the webhook route from CSRF verification:

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'chip/*',
    ]);
})
```

### Webhook Secret

Configure the webhook secret in your `.env`:

```env
CHIP_WEBHOOK_SECRET=your-webhook-secret
```

### Signature Verification

Enable/disable signature verification:

```php
// config/cashier-chip.php
'webhooks' => [
    'secret' => env('CHIP_WEBHOOK_SECRET'),
    'verify_signature' => true,  // Set to false for testing
],
```

## Handled Events

The webhook controller handles these CHIP events:

| Event | Handler | Description |
|-------|---------|-------------|
| `purchase.payment_successful` | `handlePurchasePaymentSuccess` | Payment completed |
| `purchase.payment_failed` | `handlePurchasePaymentFailed` | Payment failed |
| `purchase.expired` | `handlePurchaseExpired` | Purchase expired |
| `purchase.refunded` | `handlePurchaseRefunded` | Payment refunded |
| `recurring_token.created` | `handleRecurringTokenCreated` | Card saved |
| `recurring_token.deleted` | `handleRecurringTokenDeleted` | Card removed |

## Events Dispatched

Each webhook dispatches Laravel events you can listen for:

### Generic Events

```php
use AIArmada\CashierChip\Events\WebhookReceived;
use AIArmada\CashierChip\Events\WebhookHandled;

// Fired for every webhook
WebhookReceived::class

// Fired after successful processing
WebhookHandled::class
```

### Payment Events

```php
use AIArmada\CashierChip\Events\PaymentSucceeded;
use AIArmada\CashierChip\Events\PaymentFailed;
use AIArmada\CashierChip\Events\PaymentRefunded;

protected $listen = [
    PaymentSucceeded::class => [
        SendPaymentConfirmation::class,
        UpdateOrderStatus::class,
    ],
    PaymentFailed::class => [
        NotifyPaymentFailure::class,
    ],
];
```

### Subscription Events

```php
use AIArmada\CashierChip\Events\SubscriptionCreated;
use AIArmada\CashierChip\Events\SubscriptionRenewed;
use AIArmada\CashierChip\Events\SubscriptionPaymentFailed;
```

### Payment Method Events

```php
use AIArmada\CashierChip\Events\PaymentMethodAdded;
use AIArmada\CashierChip\Events\PaymentMethodRemoved;
```

## Custom Webhook Handlers

Extend the webhook controller for custom handling:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use AIArmada\CashierChip\Http\Controllers\WebhookController as CashierWebhookController;

class WebhookController extends CashierWebhookController
{
    /**
     * Handle successful payments.
     */
    protected function handlePurchasePaymentSuccess(array $payload): Response
    {
        // Your custom logic
        $purchaseId = $payload['id'];
        
        // Call parent to handle default logic
        $response = parent::handlePurchasePaymentSuccess($payload);
        
        // Additional processing
        $this->notifyTeam($purchaseId);
        
        return $response;
    }
    
    /**
     * Handle unknown events.
     */
    protected function handleUnknownEvent(array $payload): Response
    {
        Log::info('Unknown CHIP event', $payload);
        
        return new Response('Webhook received', 200);
    }
}
```

Register your custom controller:

```php
// routes/web.php
use App\Http\Controllers\WebhookController;

Route::post('chip/webhook', [WebhookController::class, 'handleWebhook'])
    ->name('chip.webhook');
```

## Payload Structure

CHIP webhooks contain this structure:

All monetary amounts are integers in the smallest currency unit (for MYR, this is cents).

```json
{
    "event_type": "purchase.payment_successful",
    "id": "purchase-uuid",
    "client_id": "client-uuid",
    "status": "paid",
    "is_recurring_token": true,
    "recurring_token": "tok_xxxxx",
    "purchase": {
        "total": 10000,
        "currency": "MYR",
        "products": [
            {
                "name": "Product Name",
                "price": 10000,
                "quantity": 1
            }
        ]
    }
}
```

## Accessing Webhook Data

In your event listener:

```php
class HandlePaymentSuccess
{
    public function handle(PaymentSucceeded $event): void
    {
        $payload = $event->payload;
        $billable = $event->billable;
        
        // Access purchase data
        $purchaseId = $payload['id'];
        $amount = $payload['purchase']['total'];
        
        // Access billable (user)
        if ($billable) {
            $billable->notify(new PaymentReceived($amount));
        }
    }
}
```

## Testing Webhooks

### Local Development

Use a tunnel service like ngrok:

```bash
ngrok http 8000
```

Configure the ngrok URL in your CHIP dashboard.

### Faking Webhooks

```php
use AIArmada\CashierChip\CashierChip;

CashierChip::fake();

// Now all CHIP API calls are faked
$user->charge(10000);

// Simulate a webhook
$response = $this->postJson('/chip/webhook', [
    'event_type' => 'purchase.payment_successful',
    'id' => 'purchase-123',
    'status' => 'paid',
]);

$response->assertOk();
```

### Disabling Signature Verification

For testing:

```php
// config/cashier-chip.php
'webhooks' => [
    'verify_signature' => env('CHIP_VERIFY_WEBHOOK', true),
],

// .env.testing
CHIP_VERIFY_WEBHOOK=false
```

## Webhook Queues

For high-volume applications, queue webhook processing:

```php
class WebhookController extends CashierWebhookController
{
    protected function handlePurchasePaymentSuccess(array $payload): Response
    {
        // Queue the processing
        dispatch(new ProcessPaymentWebhook($payload));
        
        return new Response('Queued', 200);
    }
}
```

## Error Handling

### Retry Logic

Return appropriate status codes:

```php
protected function handlePurchasePaymentSuccess(array $payload): Response
{
    try {
        $this->processPayment($payload);
        return new Response('OK', 200);
    } catch (ModelNotFoundException $e) {
        // Customer not found - log but don't retry
        Log::warning('Webhook customer not found', ['id' => $payload['id']]);
        return new Response('Ignored', 200);
    } catch (\Exception $e) {
        // Other error - retry
        Log::error('Webhook error', ['error' => $e->getMessage()]);
        return new Response('Error', 500);
    }
}
```

### Logging

Enable webhook logging:

```php
// In a custom webhook controller
protected function handleWebhook(): Response
{
    $payload = $this->getPayload();
    
    Log::channel('webhooks')->info('CHIP webhook received', [
        'event' => $payload['event_type'] ?? 'unknown',
        'id' => $payload['id'] ?? null,
    ]);
    
    return parent::handleWebhook();
}
```

## Webhook Security

1. **Always verify signatures** in production
2. Use HTTPS for webhook endpoints
3. Validate payload structure before processing
4. Store webhook secret securely (environment variable)
5. Log webhook activity for debugging
