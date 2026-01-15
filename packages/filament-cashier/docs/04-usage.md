---
title: Usage
---

# Usage

This guide covers common usage patterns for Filament Cashier.

## Admin Panel Usage

### Viewing Subscriptions

Navigate to **Billing → Subscriptions** to see all subscriptions across gateways.

The subscription list shows:
- **Customer** - The user who owns the subscription
- **Gateway** - Stripe or CHIP badge
- **Type** - Subscription type/name
- **Plan** - The price/plan ID
- **Status** - Active, On Trial, Canceled, etc.
- **Amount** - Monthly amount
- **Next Billing** - Next billing date

### Filtering Subscriptions

Use the tabs to filter by:
- **All** - All subscriptions
- **Stripe** / **CHIP** - Filter by gateway
- **Active** - Only active subscriptions
- **Needs Attention** - Past due or incomplete

Use the dropdown filters for:
- Gateway
- Status (Active, On Trial, Past Due, Canceled, etc.)

### Subscription Actions

On each subscription row:

| Action | Description |
|--------|-------------|
| **View** | See subscription details |
| **Cancel** | Cancel at period end |
| **Resume** | Resume a canceled subscription |
| **View in Gateway** | Open gateway dashboard |

### Creating Subscriptions

Click **Create Subscription** and follow the wizard:

1. **Customer** - Select the user
2. **Gateway** - Choose Stripe or CHIP
3. **Plan** - Select plan and quantity
4. **Payment** - Choose payment method (optional)

### Viewing Invoices

Navigate to **Billing → Invoices** to see all invoices.

Available actions:
- **Download** - Download PDF invoice
- **View in Gateway** - Open gateway dashboard
- **Export** - Bulk export to CSV

## Dashboard Widgets

### Total MRR Widget

Shows combined Monthly Recurring Revenue across all gateways.

```php
// Widget uses config for currency conversion
'currency' => [
    'base' => 'USD',
    'display_converted' => true, // Enable to convert MYR → USD
],
```

### Gateway Breakdown Widget

Doughnut chart showing revenue distribution by gateway.

### Gateway Comparison Widget

Line chart comparing 6-month revenue trends per gateway.

### Churn Widget

Shows monthly cancellations with trend indicator.

## Customer Portal

### Enabling the Portal

1. Enable in config:
```php
'billing_portal' => [
    'enabled' => true,
    'path' => 'billing',
],
```

2. Register the panel provider:
```php
// config/app.php
'providers' => [
    AIArmada\FilamentCashier\CustomerPortal\BillingPanelProvider::class,
],
```

3. Access at `/billing`

### Portal Pages

| Page | Purpose |
|------|---------|
| **Billing Overview** | Dashboard with subscriptions, payment methods, invoices |
| **Manage Subscriptions** | View, cancel, resume subscriptions |
| **Payment Methods** | View, add, remove, set default payment methods |
| **View Invoices** | List and download invoices |

### Customer Actions

Customers can:
- View all their subscriptions across gateways
- Cancel subscriptions (at period end)
- Resume canceled subscriptions (if on grace period)
- View payment methods from all gateways
- Set default payment method per gateway
- Delete payment methods
- View and download invoices

## Gateway Management

Enable the gateway management page:

```php
FilamentCashierPlugin::make()
    ->gatewayManagement()
```

### Features

- **Gateway Health** - Check connectivity to each gateway
- **Set Default** - Configure the default gateway
- **Test Connection** - Verify API credentials

### Health Statuses

| Status | Color | Meaning |
|--------|-------|---------|
| Healthy | Green | API responding correctly |
| Not Configured | Yellow | Missing credentials |
| Error | Red | API error occurred |
| Unknown | Gray | SDK not installed |

## Customization

### Custom Gateway Labels

```php
// config/filament-cashier.php
'gateways' => [
    'stripe' => [
        'label' => 'International Cards',
        'icon' => 'heroicon-o-globe-alt',
    ],
    'chip' => [
        'label' => 'Malaysian Payments',
        'icon' => 'heroicon-o-building-library',
    ],
],
```

### Custom Navigation Group

```php
FilamentCashierPlugin::make()
    ->navigationGroup('Finance & Billing')
    ->navigationSort(20)
```

### Disabling Resources

```php
FilamentCashierPlugin::make()
    ->dashboard(false)  // Hide dashboard
    ->invoices(false)   // Hide invoices
```

## Working with DTOs

The package uses DTOs to normalize data across gateways.

### UnifiedSubscription

```php
use AIArmada\FilamentCashier\Support\UnifiedSubscription;

// Properties
$sub->id           // Subscription ID
$sub->gateway      // 'stripe' or 'chip'
$sub->userId       // User ID
$sub->type         // Subscription type
$sub->planId       // Price/plan ID
$sub->amount       // Amount in cents
$sub->currency     // Currency code
$sub->quantity     // Quantity
$sub->status       // SubscriptionStatus enum
$sub->trialEndsAt  // CarbonImmutable|null
$sub->endsAt       // CarbonImmutable|null
$sub->nextBillingDate // CarbonImmutable|null
$sub->createdAt    // CarbonImmutable
$sub->original     // Original Eloquent model

// Methods
$sub->formattedAmount()       // "$29.00"
$sub->billingCycle()          // "Monthly"
$sub->needsAttention()        // true if past_due/incomplete
$sub->gatewayConfig()         // Gateway config array
$sub->externalDashboardUrl()  // Gateway dashboard URL
$sub->getExternalId()         // Gateway-specific ID
```

### UnifiedInvoice

```php
use AIArmada\FilamentCashier\Support\UnifiedInvoice;

// Properties
$invoice->id       // Invoice ID
$invoice->gateway  // 'stripe' or 'chip'
$invoice->userId   // User ID
$invoice->number   // Invoice number
$invoice->amount   // Amount in cents
$invoice->currency // Currency code
$invoice->status   // InvoiceStatus enum
$invoice->date     // CarbonImmutable
$invoice->dueDate  // CarbonImmutable|null
$invoice->paidAt   // CarbonImmutable|null
$invoice->pdfUrl   // PDF download URL

// Methods
$invoice->formattedAmount()     // "RM 99.00"
$invoice->gatewayConfig()       // Gateway config array
$invoice->externalDashboardUrl() // Gateway dashboard URL
```

### Status Enums

```php
use AIArmada\FilamentCashier\Support\SubscriptionStatus;

SubscriptionStatus::Active
SubscriptionStatus::OnTrial
SubscriptionStatus::PastDue
SubscriptionStatus::Canceled
SubscriptionStatus::OnGracePeriod
SubscriptionStatus::Paused
SubscriptionStatus::Incomplete
SubscriptionStatus::Expired

// Methods
$status->label()        // "Active"
$status->color()        // "success"
$status->icon()         // "heroicon-o-check-circle"
$status->isActive()     // true for Active, OnTrial, OnGracePeriod
$status->isCancelable() // true for Active, OnTrial, PastDue
$status->isResumable()  // true for OnGracePeriod, Paused
```

## Currency Formatting

Use the `CurrencyFormatter` utility:

```php
use AIArmada\FilamentCashier\Support\CurrencyFormatter;

CurrencyFormatter::format(2900, 'USD');         // "$29.00"
CurrencyFormatter::format(9900, 'MYR');         // "RM99.00"
CurrencyFormatter::formatWithCode(2900, 'USD'); // "29.00 USD"
CurrencyFormatter::getSymbol('MYR');            // "RM"
CurrencyFormatter::isZeroDecimal('JPY');        // true
CurrencyFormatter::formatAuto(10000, 'JPY');    // "¥10,000"
```

## Events

The package doesn't dispatch its own events but works with events from the underlying cashier packages.

Listen to events from `aiarmada/cashier`:
- `PaymentSucceeded`
- `PaymentFailed`
- `SubscriptionCreated`
- `SubscriptionCanceled`
- etc.

See the [cashier package documentation](../../../cashier/docs/05-webhooks.md) for details.
