---
title: Overview
---

# CHIP Payment Gateway Package

A comprehensive Laravel integration for the [CHIP](https://chip-in.asia) payment gateway, providing both **Collect** (payment collection) and **Send** (payout) functionality for Malaysian businesses.

## What is CHIP?

CHIP is a Malaysian fintech payment gateway that offers:
- **FPX** (Financial Process Exchange) - Direct bank transfers
- **Credit/Debit Cards** - Visa, Mastercard, Maestro
- **E-Wallets** - DuitNow, Touch 'n Go, GrabPay, ShopeePay
- **Payouts** - Send money to bank accounts (CHIP Send)

## Package Features

### Payment Collection (CHIP Collect)
- Create and manage purchases with a fluent builder API
- Process one-time payments
- Pre-authorization and capture flows
- Full and partial refunds
- Real-time webhook handling with signature verification
- Client/customer management with saved payment methods
- Idempotency support for preventing duplicate payments

### Payouts (CHIP Send)
- Create payout instructions to Malaysian bank accounts
- Manage recipient bank accounts
- Track payout status and history
- Webhook notifications for payout events

### Enterprise Features
- **Multi-tenancy Support**: Owner-scoped data with configurable isolation
- **Analytics**: Local analytics service with revenue metrics
- **Health Checks**: Built-in gateway health monitoring
- **Audit Trail**: Full audit logging via commerce-support integration
- **Testing Utilities**: Webhook simulation and testing helpers
- **Webhook Deduplication**: Automatic duplicate webhook prevention

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Your Laravel App                        │
├─────────────────────────────────────────────────────────────┤
│  Facades          │  Services           │  Gateway          │
│  ├─ Chip          │  ├─ ChipCollect     │  └─ ChipGateway   │
│  └─ ChipSend      │  ├─ ChipSend        │      (implements  │
│                   │  ├─ Webhook         │   PaymentGateway  │
│                   │  └─ Analytics       │     Interface)    │
├─────────────────────────────────────────────────────────────┤
│  Clients          │  Builders           │  Events           │
│  ├─ CollectClient │  └─ PurchaseBuilder │  ├─ PurchasePaid  │
│  └─ SendClient    │                     │  ├─ Refunded      │
│                   │                     │  └─ 20+ more...   │
├─────────────────────────────────────────────────────────────┤
│                      CHIP API (gate.chip-in.asia)           │
└─────────────────────────────────────────────────────────────┘
```

## Quick Start

```php
use AIArmada\Chip\Facades\Chip;

// Create a simple purchase
$purchase = Chip::purchase()
    ->email('customer@example.com')
    ->addProductCents('Premium Plan', 9900) // RM 99.00
    ->successUrl(route('payment.success'))
    ->failureUrl(route('payment.failed'))
    ->create();

// Redirect customer to payment page
return redirect($purchase->checkout_url);
```

## Requirements

- PHP 8.4+
- Laravel 12.x
- CHIP merchant account with API credentials
- `aiarmada/commerce-support` package

## Multi-tenancy Support

Full multi-tenancy support via `commerce-support`:

- Owner-scoped models with `HasOwner` trait
- Auto-assignment on create
- Brand ID to owner mapping for webhooks
- Greppable opt-out via `withoutOwnerScope()`

## Related Packages

| Package | Description |
|---------|-------------|
| `aiarmada/filament-chip` | Filament admin panel for CHIP data |
| `aiarmada/cashier-chip` | Stripe Cashier-like subscription billing |
| `aiarmada/commerce-support` | Shared commerce contracts and utilities |

## Quick Links

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage Guide](04-usage.md)
- [Troubleshooting](99-troubleshooting.md)
