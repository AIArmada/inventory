# CHIP Collect

Accept payments via FPX, cards, e-wallets, and more.

## Facade

```php
use AIArmada\Chip\Facades\Chip;
```

## Create Purchase

### Array Method

```php
$purchase = Chip::createPurchase([
    'brand_id' => config('chip.collect.brand_id'),
    'client' => [
        'email' => 'customer@example.com',
        'full_name' => 'John Doe',
        'phone' => '+60123456789',
    ],
    'purchase' => [
        'currency' => 'MYR',
        'products' => [
            ['name' => 'Product A', 'price' => 5000, 'quantity' => '2'],
            ['name' => 'Product B', 'price' => 3000, 'quantity' => '1'],
        ],
    ],
    'success_redirect' => route('checkout.success'),
    'failure_redirect' => route('checkout.failed'),
]);

return redirect($purchase->checkout_url);
```

### Fluent Builder

```php
$purchase = Chip::purchase()
    ->customer('customer@example.com', 'John Doe', '+60123456789')
    ->addProductCents('Product A', 5000, 2)
    ->addProductCents('Product B', 3000, 1)
    ->successUrl(route('checkout.success'))
    ->failureUrl(route('checkout.failed'))
    ->sendReceipt(true)
    ->create();
```

### With Money Objects

```php
use Akaunting\Money\Money;

Chip::purchase()
    ->customer('customer@example.com')
    ->addProductMoney('Product', Money::MYR(9900), 1)
    ->successUrl(route('success'))
    ->create();
```

### From Checkoutable

```php
$cart = app(\AIArmada\Cart\Cart::class);

$purchase = Chip::purchase()
    ->fromCheckoutable($cart)
    ->fromCustomer($customer)
    ->successUrl(route('success'))
    ->create();
```

## Purchase Operations

### Retrieve

```php
$purchase = Chip::getPurchase('pur_abc123');

$purchase->id;
$purchase->status;           // 'created', 'paid', 'cancelled'
$purchase->getAmount();      // Money object
$purchase->getAmountInCents();
$purchase->getCheckoutUrl();
$purchase->isPaid();
$purchase->isRefunded();
$purchase->isCancelled();
```

### Cancel

```php
$purchase = Chip::cancelPurchase('pur_abc123');
```

### Refund

```php
// Full refund
$purchase = Chip::refundPurchase('pur_abc123');

// Partial refund (in cents)
$purchase = Chip::refundPurchase('pur_abc123', 5000);
```

### Capture (Pre-auth)

```php
// Full capture
$purchase = Chip::capturePurchase('pur_abc123');

// Partial capture
$purchase = Chip::capturePurchase('pur_abc123', 5000);
```

### Release Hold

```php
$purchase = Chip::releasePurchase('pur_abc123');
```

### Mark as Paid

```php
$purchase = Chip::markPurchaseAsPaid('pur_abc123');
```

### Resend Invoice

```php
$purchase = Chip::resendInvoice('pur_abc123');
```

## Clients

```php
// Create
$client = Chip::createClient([
    'email' => 'customer@example.com',
    'full_name' => 'John Doe',
]);

// Retrieve
$client = Chip::getClient('cli_abc123');

// List
$clients = Chip::listClients(['limit' => 50]);

// Update
$client = Chip::updateClient('cli_abc123', ['full_name' => 'Jane Doe']);

// Delete
Chip::deleteClient('cli_abc123');
```

## Payment Methods

```php
$methods = Chip::getPaymentMethods([
    'currency' => 'MYR',
]);
```

## Account

```php
$balance = Chip::getAccountBalance();
$turnover = Chip::getAccountTurnover([
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
]);
```

## Company Statements

```php
$statements = Chip::listCompanyStatements();
$statement = Chip::getCompanyStatement('stmt_abc123');
$statement = Chip::cancelCompanyStatement('stmt_abc123');
```

## Public Key

```php
$publicKey = Chip::getPublicKey();
```

## Next Steps

- [CHIP Send](chip-send.md) – Disbursements
- [Webhooks](webhooks.md) – Event handling
