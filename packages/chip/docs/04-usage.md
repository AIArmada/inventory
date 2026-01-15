---
title: Usage
---

# Usage Guide

## Payment Gateway (Recommended)

The `ChipGateway` implements the universal `PaymentGatewayInterface`, allowing CHIP to work interchangeably with other gateways (Stripe, PayPal, etc.):

```php
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;

// Inject via interface for easy gateway switching
$gateway = app(PaymentGatewayInterface::class);

$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => route('checkout.success'),
    'failure_url' => route('checkout.failed'),
]);

return redirect($payment->getCheckoutUrl());
```

### Gateway Features

```php
// Check supported features
$gateway->supports('refunds');         // true
$gateway->supports('partial_refunds'); // true
$gateway->supports('pre_authorization'); // true
$gateway->supports('webhooks');        // true

// Get payment methods
$methods = $gateway->getPaymentMethods(['currency' => 'MYR']);

// Refund a payment
$payment = $gateway->refundPayment($paymentId, Money::MYR(5000));

// Capture pre-authorized payment
$payment = $gateway->capturePayment($paymentId);
```

## CHIP Collect Facade

The `Chip` facade provides direct access to the Collect API.

### Create a Purchase (Fluent Builder)

```php
use AIArmada\Chip\Facades\Chip;

$purchase = Chip::purchase()
    // Customer details
    ->customer('customer@example.com', 'John Doe', '+60123456789')
    
    // Products (amounts in cents)
    ->addProductCents('Premium Plan', 9900, quantity: 1)
    ->addProductCents('Setup Fee', 2500, quantity: 1, taxPercent: 6.0)
    
    // Or use Money objects for type safety
    ->addProductMoney('Add-on', Money::MYR(1500))
    
    // Redirects
    ->successUrl(route('payment.success'))
    ->failureUrl(route('payment.failed'))
    ->cancelUrl(route('payment.cancelled'))
    
    // Optional settings
    ->reference('ORD-2024-001')
    ->sendReceipt(true)
    ->notes('Thank you for your purchase!')
    
    // Create the purchase
    ->create();

// Redirect to CHIP checkout
return redirect($purchase->checkout_url);
```

### Create from Cart/Order (Checkoutable Interface)

```php
use AIArmada\Chip\Facades\Chip;

// Any model implementing CheckoutableInterface
$purchase = Chip::purchase()
    ->fromCheckoutable($cart)
    ->fromCustomer($user)
    ->successUrl(route('checkout.success'))
    ->create();
```

### Pre-Authorization Flow

```php
// Create pre-authorized purchase (hold funds without charging)
$purchase = Chip::purchase()
    ->customer('customer@example.com')
    ->addProductCents('Reservation', 50000)
    ->preAuthorize(true)
    ->create();

// Later: capture the authorized amount
$captured = Chip::capturePurchase($purchase->id);

// Or release without charging
$released = Chip::releasePurchase($purchase->id);
```

### Refunds

```php
// Full refund
$purchase = Chip::refundPurchase($purchaseId);

// Partial refund (amount in cents)
$purchase = Chip::refundPurchase($purchaseId, 5000); // RM 50.00
```

### Client Management

```php
// Create a client
$client = Chip::createClient([
    'email' => 'customer@example.com',
    'full_name' => 'John Doe',
    'phone' => '+60123456789',
]);

// Get client's purchases
$purchases = Chip::listClientPurchases($client->id);
```

### Account Information

```php
// Get account balance
$balance = Chip::getAccountBalance();

// Get turnover report
$turnover = Chip::getAccountTurnover([
    'from' => '2024-01-01',
    'to' => '2024-12-31',
]);

// List company statements
$statements = Chip::listCompanyStatements();
```

## CHIP Send Facade (Payouts)

The `ChipSend` facade handles disbursements and payouts.

### Create Bank Account

```php
use AIArmada\Chip\Facades\ChipSend;

$bankAccount = ChipSend::createBankAccount(
    bankCode: 'MBBEMYKL', // Maybank
    accountNumber: '1234567890',
    accountHolderName: 'John Doe',
    reference: 'vendor-001'
);
```

### Create Payout

```php
$instruction = ChipSend::createSendInstruction(
    amountInCents: 10000,        // RM 100.00
    currency: 'MYR',
    recipientBankAccountId: $bankAccount->id,
    description: 'Affiliate Payout',
    reference: 'PAY-2024-001',
    email: 'recipient@example.com'
);

// Track status
$status = $instruction->state; // pending, success, failed
```

### Manage Payouts

```php
// List all payouts
$payouts = ChipSend::listSendInstructions();

// Get specific payout
$payout = ChipSend::getSendInstruction($id);

// Cancel pending payout
$cancelled = ChipSend::cancelSendInstruction($id);
```

## Webhooks

### Listen to Events

Register listeners in your `EventServiceProvider`:

```php
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PaymentRefunded;

protected $listen = [
    PurchasePaid::class => [
        CompleteOrder::class,
        SendConfirmationEmail::class,
    ],
    PurchaseCancelled::class => [
        ReleaseInventory::class,
    ],
    PaymentRefunded::class => [
        ProcessRefund::class,
    ],
];
```

### Event Listener Example

```php
use AIArmada\Chip\Events\PurchasePaid;

class CompleteOrder
{
    public function handle(PurchasePaid $event): void
    {
        $purchase = $event->purchase; // PurchaseData object
        $payload = $event->payload;   // Raw webhook payload
        
        // Find your order by reference
        $order = Order::where('reference', $purchase->reference)->first();
        
        // Update order status
        $order->update([
            'status' => 'paid',
            'paid_at' => $purchase->getCreatedAt(),
            'chip_purchase_id' => $purchase->id,
        ]);
        
        // Trigger fulfillment...
    }
}
```

### All Available Events

**Purchase Events:**
- `PurchaseCreated` - Purchase was created
- `PurchasePaid` - Payment successful
- `PurchasePaymentFailure` - Payment failed
- `PurchaseCancelled` - Purchase cancelled
- `PurchaseHold` - Funds on hold (pre-auth)
- `PurchaseCaptured` - Pre-auth captured
- `PurchaseReleased` - Pre-auth released
- `PurchasePreauthorized` - Pre-authorization complete

**Refund Events:**
- `PurchasePendingRefund` - Refund initiated
- `PaymentRefunded` - Refund completed

**Payout Events:**
- `PayoutPending` - Payout processing
- `PayoutSuccess` - Payout completed
- `PayoutFailed` - Payout failed

## Testing

### Webhook Simulation

```php
use AIArmada\Chip\Testing\SimulatesWebhooks;

class PaymentTest extends TestCase
{
    use SimulatesWebhooks;
    
    public function test_handles_paid_webhook(): void
    {
        // Disable signature verification for testing
        $this->withoutWebhookSignatureVerification();
        
        // Fake events
        $this->fakeWebhookEvents([PurchasePaid::class]);
        
        // Simulate webhook
        $this->simulatePaidWebhook()
            ->with(['reference' => 'ORD-001'])
            ->dispatch();
        
        // Assert event was dispatched
        $this->assertWebhookDispatched(PurchasePaid::class);
    }
}
```

## Artisan Commands

| Command | Description |
|---------|-------------|
| `chip:health-check` | Check CHIP API connectivity and credentials |
| `chip:aggregate-metrics` | Aggregate purchase data into daily metrics |
| `chip:retry-webhooks` | Retry failed webhooks |
| `chip:clean-webhooks` | Clean old webhook records |
