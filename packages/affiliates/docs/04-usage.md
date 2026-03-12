---
title: Basic Usage
---

# Basic Usage

This guide covers common workflows for tracking affiliates, recording conversions, and managing commissions.

## Attaching Affiliates to Carts

### Using the Cart Facade (with aiarmada/cart)

```php
use AIArmada\Cart\Facades\Cart;

// Attach affiliate by code
Cart::attachAffiliate('PARTNER42', [
    'utm_source' => 'newsletter',
    'landing_url' => url()->current(),
    'subject_type' => 'product',
    'subject_identifier' => 'SKU-1001',
    'subject_instance' => 'web',
    'subject_title_snapshot' => 'Pro Plan',
]);

// Check if cart has affiliate
if (Cart::hasAffiliate()) {
    $affiliate = Cart::getAffiliate();
}
```

### Using the AffiliateService Directly

```php
use AIArmada\Affiliates\Services\AffiliateService;

$service = app(AffiliateService::class);

// Find affiliate by code
$affiliate = $service->findByCode('PARTNER42');

// Attach to cart with context
$attribution = $service->attachAffiliate($affiliate, $cart, [
    'source' => 'instagram',
    'campaign' => 'summer-sale',
]);
```

## Cookie-Based Tracking

The `TrackAffiliateCookie` middleware automatically captures affiliate visits from URL parameters:

```
https://yoursite.com/products?aff=PARTNER42&utm_source=instagram
```

Recognized parameters (configurable):
- `aff`
- `affiliate`
- `ref`
- `referral`

The middleware:
1. Captures the affiliate code from the URL
2. Creates an `AffiliateAttribution` record with UTM data
3. Sets a tracking cookie (default: 30 days)
4. Links the attribution to the cart when shopping begins

### Consent Management

For GDPR compliance, enable consent requirement:

```php
// config/affiliates.php
'cookies' => [
    'require_consent' => true,
    'consent_cookie' => 'affiliate_consent',
],
```

Set the consent cookie when user accepts:

```php
Cookie::queue('affiliate_consent', '1', 60 * 24 * 365);
```

## Recording Conversions

### From Cart

```php
use AIArmada\Cart\Facades\Cart;

// Record conversion when order is placed
Cart::recordAffiliateConversion([
    'external_reference' => $order->reference,
    'order_reference' => $order->reference, // compatibility alias
    'subtotal' => $order->subtotal_minor,
    'value_minor' => $order->total_minor,
    'total' => $order->total_minor, // compatibility alias
    'conversion_type' => 'purchase',
]);
```

### Orders Integration (Auto Attribution)

When the Orders package is installed, it can emit a commission attribution event on payment.
The Affiliates package listens for this and records conversions automatically **if** the order
metadata includes a `cart_id` pointing to the original cart.

```php
use AIArmada\Orders\Models\Order;

$order = Order::create([
    // ...
    'metadata' => [
        'cart_id' => $cart->getId(),
    ],
]);
```

### Direct Recording

```php
use AIArmada\Affiliates\Services\AffiliateService;

$service = app(AffiliateService::class);

$conversion = $service->recordConversion(
    affiliate: $affiliate,
    data: [
        'external_reference' => 'ORD-12345',
        'value_minor' => 15000, // $150.00
        'total_minor' => 15000, // compatibility alias
        'subtotal_minor' => 14000,
        'conversion_type' => 'purchase',
        'subject_type' => 'product',
        'subject_identifier' => 'SKU-1001',
        'subject_instance' => 'web',
        'subject_title_snapshot' => 'Pro Plan',
        'metadata' => [
            'customer_id' => $customer->id,
            'items' => $order->items->count(),
        ],
    ]
);
```

## Creating Subject-Aware Tracking Links

```php
$link = $service->createTrackingLink($affiliate, 'https://example.com/products/sku-1001', [
    'params' => ['utm_source' => 'affiliate-campaign'],
    'ttl_seconds' => 3600,
    'subject_type' => 'product',
    'subject_identifier' => 'SKU-1001',
    'subject_instance' => 'web',
    'subject_title_snapshot' => 'Pro Plan',
    'subject_metadata' => [
        'category' => 'subscriptions',
    ],
]);
```

### Conversion Statuses

```php
use AIArmada\Affiliates\Enums\ConversionStatus;

ConversionStatus::Pending;    // Awaiting review
ConversionStatus::Qualified;  // Met qualifications, pending approval
ConversionStatus::Approved;   // Approved for payout
ConversionStatus::Rejected;   // Rejected (fraud, refund, etc.)
ConversionStatus::Paid;       // Commission paid out
```

## Commission Calculation

The `CommissionCalculator` service handles all commission logic:

```php
use AIArmada\Affiliates\Services\CommissionCalculator;

$calculator = app(CommissionCalculator::class);

// Calculate commission for an order
$commission = $calculator->calculate(
    affiliate: $affiliate,
    orderTotal: 15000, // $150.00 in minor units
    orderSubtotal: 14000,
);

// Returns commission in minor units (e.g., 1500 = $15.00)
```

### Commission Types

```php
use AIArmada\Affiliates\Enums\CommissionType;

// Percentage (in basis points: 1000 = 10%)
$affiliate->commission_type = CommissionType::Percentage;
$affiliate->commission_rate = 1000; // 10%

// Fixed amount (in minor units)
$affiliate->commission_type = CommissionType::FixedAmount;
$affiliate->commission_rate = 500; // $5.00 per conversion
```

## Working with Affiliates

### Creating Affiliates

```php
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Enums\CommissionType;

$affiliate = Affiliate::create([
    'code' => 'PARTNER42',
    'name' => 'John Partner',
    'status' => AffiliateStatus::Active,
    'commission_type' => CommissionType::Percentage,
    'commission_rate' => 1000, // 10%
    'currency' => 'USD',
    'contact_email' => 'john@partner.com',
]);
```

### Affiliate Statuses

```php
use AIArmada\Affiliates\Enums\AffiliateStatus;

AffiliateStatus::Draft;      // Not yet active
AffiliateStatus::Pending;    // Awaiting approval
AffiliateStatus::Active;     // Active and earning
AffiliateStatus::Suspended;  // Temporarily disabled
AffiliateStatus::Terminated; // Permanently disabled
```

### Querying Affiliates

```php
// Get active affiliates
$active = Affiliate::query()
    ->where('status', AffiliateStatus::Active)
    ->get();

// Find by default voucher code
$affiliate = $service->findByDefaultVoucherCode('SUMMER20');

// Get affiliate with relationships
$affiliate = Affiliate::with(['conversions', 'payouts', 'balance'])
    ->find($id);
```

## Events

Listen to affiliate events for automation:

```php
use AIArmada\Affiliates\Events\AffiliateAttributed;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;

// In EventServiceProvider
protected $listen = [
    AffiliateAttributed::class => [
        SendWelcomeEmail::class,
        NotifySlack::class,
    ],
    AffiliateConversionRecorded::class => [
        UpdateAffiliateStats::class,
        SendConversionNotification::class,
    ],
];
```

## Facades

Use the `Affiliates` facade for quick access:

```php
use AIArmada\Affiliates\Facades\Affiliates;

$affiliate = Affiliates::findByCode('PARTNER42');
$attribution = Affiliates::attachToCartByCode('PARTNER42', $cart);
```
