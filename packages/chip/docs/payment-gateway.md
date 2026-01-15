# Payment Gateway

Universal payment gateway interface for provider-agnostic checkout.

## Overview

`ChipGateway` implements `PaymentGatewayInterface` from `aiarmada/commerce-support`. CHIP is **fully independent** – it works standalone without requiring other commerce packages, but **auto-integrates** with Cart when both are installed.

```php
interface PaymentGatewayInterface
{
    public function getName(): string;
    public function getDisplayName(): string;
    public function isTestMode(): bool;
    public function createPayment(CheckoutableInterface $checkoutable, ?CustomerInterface $customer, array $options): PaymentIntentInterface;
    public function getPayment(string $paymentId): PaymentIntentInterface;
    public function cancelPayment(string $paymentId): PaymentIntentInterface;
    public function refundPayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;
    public function capturePayment(string $paymentId, ?Money $amount = null): PaymentIntentInterface;
    public function getPaymentMethods(array $filters = []): array;
    public function supports(string $feature): bool;
    public function getWebhookHandler(): WebhookHandlerInterface;
}
```

## Standalone Usage

Create payments with any `CheckoutableInterface` implementation:

```php
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\CommerceSupport\Contracts\Payment\CheckoutableInterface;
use AIArmada\CommerceSupport\Data\Customer;

// Your custom order/invoice implementing CheckoutableInterface
class Order implements CheckoutableInterface
{
    public function getCheckoutTotal(): Money { /* ... */ }
    public function getCheckoutCurrency(): string { /* ... */ }
    public function getCheckoutReference(): string { /* ... */ }
    public function getCheckoutLineItems(): array { /* ... */ }
    public function getCheckoutNotes(): ?string { /* ... */ }
    public function getCheckoutMetadata(): array { /* ... */ }
}

$gateway = app(ChipGateway::class);
$order = new Order($items);

$customer = Customer::fromArray([
    'email' => 'customer@example.com',
    'name' => 'John Doe',
]);

$payment = $gateway->createPayment($order, $customer, [
    'success_url' => route('payment.success'),
    'failure_url' => route('payment.failed'),
]);

return redirect($payment->getCheckoutUrl());
```

## Cart Integration

When `aiarmada/cart` is installed, Cart automatically implements `CheckoutableInterface` – no additional setup required:

```php
use AIArmada\Chip\Gateways\ChipGateway;
use AIArmada\CommerceSupport\Data\Customer;

class CheckoutController extends Controller
{
    public function __construct(private ChipGateway $gateway) {}
    
    public function checkout(Request $request)
    {
        // Cart implements CheckoutableInterface
        $cart = app(\AIArmada\Cart\Cart::class);
        
        $customer = Customer::fromArray([
            'email' => $request->user()->email,
            'name' => $request->user()->name,
        ]);
        
        $payment = $this->gateway->createPayment($cart, $customer, [
            'success_url' => route('payment.success'),
            'failure_url' => route('payment.failed'),
        ]);
        
        return redirect($payment->getCheckoutUrl());
    }
}
```

## Create Payment

```php
$payment = $gateway->createPayment($cart, $customer, [
    'success_url' => 'https://example.com/success',
    'failure_url' => 'https://example.com/failed',
    'cancel_url' => 'https://example.com/cart',
    'webhook_url' => 'https://example.com/webhooks/chip',
    'send_receipt' => true,
    'pre_authorize' => false,
    'metadata' => ['order_id' => $orderId],
]);
```

| Option | Type | Description |
|--------|------|-------------|
| `success_url` | string | Redirect after success |
| `failure_url` | string | Redirect after failure |
| `cancel_url` | string | Redirect on cancel |
| `webhook_url` | string | Webhook callback URL |
| `send_receipt` | bool | Email receipt to customer |
| `pre_authorize` | bool | Auth only, capture later |
| `metadata` | array | Custom data |

## Payment Intent

```php
$payment->getId();           // 'pur_abc123'
$payment->getStatus();       // PaymentStatus::PENDING
$payment->getAmount();       // Money::MYR(9900)
$payment->getCurrency();     // 'MYR'
$payment->getCheckoutUrl();  // CHIP checkout URL
$payment->getMetadata();     // Custom metadata
$payment->getCreatedAt();    // Carbon
$payment->getPaidAt();       // Carbon or null
```

## Refunds

```php
// Full refund
$gateway->refundPayment('pur_abc123');

// Partial refund
$gateway->refundPayment('pur_abc123', Money::MYR(5000));
```

## Pre-Authorization

```php
// 1. Create with pre_authorize
$payment = $gateway->createPayment($cart, $customer, [
    'pre_authorize' => true,
]);

// 2. Capture later
$gateway->capturePayment($payment->getId());

// Or partial capture
$gateway->capturePayment($payment->getId(), Money::MYR(5000));

// Or cancel
$gateway->cancelPayment($payment->getId());
```

## Feature Support

```php
$gateway->supports('refunds');           // true
$gateway->supports('partial_refunds');   // true
$gateway->supports('pre_authorization'); // true
$gateway->supports('webhooks');          // true
$gateway->supports('hosted_checkout');   // true
$gateway->supports('embedded_checkout'); // false
$gateway->supports('direct_charge');     // true
```

## Error Handling

```php
use AIArmada\CommerceSupport\Exceptions\PaymentGatewayException;

try {
    $payment = $gateway->createPayment($cart, $customer, $options);
} catch (PaymentGatewayException $e) {
    Log::error('Payment failed', [
        'gateway' => $e->getGatewayName(),
        'message' => $e->getMessage(),
        'context' => $e->getContext(),
    ]);
}
```

## Gateway Switching

```php
// AppServiceProvider
use AIArmada\CommerceSupport\Contracts\Payment\PaymentGatewayInterface;

$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    $gateway = config('payments.default');
    return match ($gateway) {
        'chip' => $app->make(ChipGateway::class),
        'stripe' => $app->make(StripeGateway::class),
    };
});

// Controller - works with any gateway
public function checkout(PaymentGatewayInterface $gateway)
{
    $payment = $gateway->createPayment($cart, $customer, $options);
}
```

## Next Steps

- [CHIP Collect](chip-collect.md) – Purchase operations
- [Webhooks](webhooks.md) – Event handling
