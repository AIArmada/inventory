---
title: Checkout Sessions
---

# Checkout Sessions

CHIP checkout sessions redirect customers to CHIP's hosted payment page.

## Basic Checkout

### Customer Checkout

```php
// Create checkout for authenticated user
$checkout = $user->checkout(10000, [
    'reference' => 'Order #123',
]);

// Redirect to CHIP checkout
return $checkout->redirect();
```

### Guest Checkout

```php
use AIArmada\CashierChip\Checkout;

$checkout = Checkout::guest()
    ->addProduct('Widget', 5000, 2)  // name, price (cents), quantity
    ->successUrl('/success')
    ->cancelUrl('/cancel')
    ->create(10000);

return $checkout->redirect();
```

## Checkout Builder

The fluent builder provides a clean API:

```php
use AIArmada\CashierChip\Checkout;

$checkout = Checkout::customer($user)
    ->addProduct('Monthly Plan', 9900)
    ->addProduct('Setup Fee', 2500)
    ->successUrl(route('checkout.success'))
    ->cancelUrl(route('checkout.cancel'))
    ->webhookUrl(route('chip.webhook'))
    ->recurring()  // Request recurring token
    ->withMetadata(['order_id' => $order->id])
    ->currency('MYR')
    ->create(12400);

return $checkout->redirect();
```

## Checkout Options

### Redirect URLs

```php
$checkout = $user->checkout(10000, [
    'success_url' => route('checkout.success'),
    'cancel_url' => route('checkout.cancel'),
]);
```

### Reference/Description

```php
$checkout = $user->checkout(10000, [
    'reference' => 'Premium Plan - Monthly',
]);
```

### Products

```php
$checkout = $user->checkout(10000, [
    'products' => [
        ['name' => 'Widget', 'price' => 50.00, 'quantity' => 2],
        ['name' => 'Service', 'price' => 25.00, 'quantity' => 1],
    ],
]);
```

### Recurring Token

Request a recurring token for future charges:

```php
$checkout = $user->checkout(10000, [
    'recurring' => true,
]);
```

### Receipt

```php
$checkout = $user->checkout(10000, [
    'send_receipt' => true,
]);
```

### Metadata

```php
$checkout = $user->checkout(10000, [
    'metadata' => [
        'order_id' => $order->id,
        'customer_ref' => 'CUST-001',
    ],
]);
```

## Checkout Object

The `Checkout` object provides several methods:

```php
$checkout = $user->checkout(10000);

// Get the checkout URL
$url = $checkout->url();

// Get the purchase ID
$id = $checkout->id();

// Redirect to checkout
return $checkout->redirect();

// Get underlying CHIP Purchase
$purchase = $checkout->asChipPurchase();

// Convert to Payment object
$payment = $checkout->asPayment();

// Get owner (billable model)
$user = $checkout->owner();
```

## Response Handling

### As Controller Response

The `Checkout` object implements `Responsable`:

```php
public function store(Request $request)
{
    return $request->user()->checkout(10000, [
        'reference' => 'Order Payment',
    ]);
    // Automatically redirects to CHIP checkout
}
```

### Manual Redirect

```php
$checkout = $user->checkout(10000);

return redirect()->away($checkout->url());
```

### JSON Response

```php
return response()->json([
    'checkout_url' => $checkout->url(),
    'purchase_id' => $checkout->id(),
]);
```

## Setup Purchases

Create zero-amount checkouts to save payment methods:

```php
// Create a setup purchase (saves card without charging)
$checkout = $user->createSetupPurchase([
    'success_url' => route('billing.methods'),
    'cancel_url' => route('billing.methods'),
]);

return redirect($checkout->checkout_url);
```

This creates a CHIP purchase with:
- `total_override = 0` (zero amount)
- `skip_capture = true` (preauthorization only)
- `force_recurring = true` (save card for future use)

## Customizing Checkout Creation

### Via Billable Trait

Override the `createCheckout` method:

```php
class User extends Authenticatable
{
    use Billable;
    
    protected function createCheckout(int $amount, array $options = []): Checkout
    {
        // Add default options
        $options = array_merge([
            'reference' => "Order for {$this->name}",
            'send_receipt' => true,
        ], $options);
        
        return Checkout::create($this, $amount, $options);
    }
}
```

## Error Handling

```php
use AIArmada\CashierChip\Exceptions\CheckoutFailure;

try {
    $checkout = $user->checkout(10000);
} catch (CheckoutFailure $e) {
    return back()->withErrors([
        'checkout' => $e->getMessage(),
    ]);
}
```
