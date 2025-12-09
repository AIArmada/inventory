# Filament Cashier Chip Vision: Executive Summary

> **Document:** 01 of 05  
> **Package:** `aiarmada/filament-cashier-chip`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Package Hierarchy

```
Chip Payment Gateway API (External)
    │
    └── aiarmada/chip                     ← Core SDK for Chip API
        │   • Purchase management
        │   • Client CRUD
        │   • Webhook handling
        │   • Payment methods
        │
        └── aiarmada/cashier-chip         ← Laravel Cashier-style integration
            │   • Billable trait
            │   • Subscription lifecycle
            │   • Payment methods (recurring tokens)
            │   • Checkout sessions
            │   • Invoicing
            │
            └── aiarmada/filament-cashier-chip  ← THIS PACKAGE
                    • Admin UI for subscription management
                    • Customer billing portal
                    • Revenue dashboards
                    • Invoice management
```

---

## Purpose Statement

**filament-cashier-chip** provides a comprehensive Filament admin interface for managing Laravel Cashier Chip billing operations. It bridges the gap between the Cashier billing engine and intuitive admin workflows, enabling:

- **Subscription Administration** - Full CRUD with plan swaps, cancellations, trials
- **Customer Billing Portal** - Self-service view for customers (Filament panel)
- **Revenue Analytics** - MRR, churn, conversion dashboards
- **Invoice Management** - Generation, PDF export, email delivery

---

## Strategic Context

### Why This Package?

| Need | Solution |
|------|----------|
| Cashier handles business logic but lacks UI | Filament resources provide intuitive admin |
| CHIP has no native billing portal | Self-hosted Filament panel for customers |
| Revenue metrics require manual queries | Automated dashboard widgets |
| Invoice management is code-only | Visual invoice builder and management |

### Positioning in Commerce Suite

```
┌────────────────────────────────────────────────────────────────┐
│                    Commerce Admin Panel                         │
├──────────────┬──────────────┬──────────────┬──────────────────┤
│ filament-    │ filament-    │ filament-    │                  │
│ vouchers     │ inventory    │ affiliates   │     ...etc       │
├──────────────┴──────────────┴──────────────┴──────────────────┤
│                    Billing / Payments Section                   │
├─────────────────────────┬─────────────────────────────────────┤
│   filament-chip         │   filament-cashier-chip              │
│   (Purchases, Tokens)   │   (Subscriptions, Invoices)          │
├─────────────────────────┴─────────────────────────────────────┤
│              Core Layer: cashier-chip + chip                   │
└────────────────────────────────────────────────────────────────┘
```

---

## Current State Assessment

### Existing Capabilities (via cashier-chip)

| Feature | Status | Notes |
|---------|--------|-------|
| Billable Trait | ✅ Complete | User model integration |
| Customer Sync | ✅ Complete | Create/update Chip customers |
| Subscriptions | ✅ Complete | Full lifecycle management |
| Payment Methods | ✅ Complete | Recurring tokens via setup purchase |
| Checkout Sessions | ✅ Complete | Redirect checkout flow |
| Invoices | ✅ Complete | Invoice model with line items |
| Webhooks | ✅ Complete | Event-driven updates |

### Missing Admin UI (This Package Fills)

| Gap | Priority | Impact |
|-----|----------|--------|
| No subscription admin interface | 🔴 Critical | Admins can't manage subscriptions |
| No customer billing portal | 🔴 Critical | Users need code to manage billing |
| No revenue dashboards | 🟡 High | No visibility into billing health |
| No invoice management UI | 🟡 High | Invoice ops require developer |
| No plan management | 🟢 Medium | Plans defined in config only |

---

## Vision Pillars

### 1. Subscription Management Excellence
Complete Filament resources for subscription CRUD, with intuitive actions for common operations (cancel, resume, swap, extend trial).

### 2. Customer Self-Service Portal
Dedicated Filament panel for customers to manage their own billing: view subscriptions, update payment methods, download invoices.

### 3. Revenue Intelligence Dashboard
Real-time widgets showing MRR, active subscribers, churn rate, trial conversions, and revenue trends.

### 4. Invoice Operations Center
Create, view, email, and export invoices with line-item management and PDF generation.

### 5. Seamless Integration
Works out-of-box with `filament-chip` for unified billing admin, and `filament-authz` for role-based access.

---

## Technical Architecture

### Plugin Design

```php
use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentCashierChipPlugin::make()
                ->navigationGroup('Billing')
                ->enableCustomerPortal()
                ->enableDashboard()
                ->enableInvoicing(),
        ]);
}
```

### Resource Structure

```
src/
├── FilamentCashierChipPlugin.php
├── FilamentCashierChipServiceProvider.php
├── Resources/
│   ├── SubscriptionResource/
│   │   ├── SubscriptionResource.php
│   │   ├── Pages/
│   │   │   ├── ListSubscriptions.php
│   │   │   ├── ViewSubscription.php
│   │   │   └── EditSubscription.php
│   │   └── RelationManagers/
│   │       └── ItemsRelationManager.php
│   ├── CustomerResource/
│   ├── InvoiceResource/
│   └── PaymentMethodResource/
├── Widgets/
│   ├── MrrWidget.php
│   ├── ActiveSubscribersWidget.php
│   ├── ChurnRateWidget.php
│   ├── RevenueChartWidget.php
│   └── SubscriptionDistributionWidget.php
└── Pages/
    ├── BillingDashboard.php
    └── CustomerPortal/
        ├── ManageSubscription.php
        ├── PaymentMethods.php
        └── InvoiceHistory.php
```

---

## Strategic Impact Matrix

| Feature | Business Value | Technical Complexity | Priority |
|---------|---------------|---------------------|----------|
| Subscription Resource | 🔴 Critical | Medium | P0 |
| Customer Portal | 🔴 Critical | Medium | P0 |
| Dashboard Widgets | 🟡 High | Low | P1 |
| Invoice Resource | 🟡 High | Medium | P1 |
| Plan Management | 🟢 Medium | Low | P2 |
| Reporting Export | 🟢 Medium | Low | P2 |

---

## Vision Documents

| # | Document | Description |
|---|----------|-------------|
| 01 | Executive Summary | This document |
| 02 | [Subscription Management](02-subscription-management.md) | Subscription CRUD and actions |
| 03 | [Customer Portal](03-customer-portal.md) | Self-service billing portal |
| 04 | [Dashboard Widgets](04-dashboard-widgets.md) | Revenue analytics and metrics |
| 05 | [Implementation Roadmap](05-implementation-roadmap.md) | Phased delivery plan |

---

## Key Constraints

1. **No Direct API Calls** - All Chip interactions through Cashier abstractions
2. **UI Only** - Business logic remains in `cashier-chip`, no duplication
3. **Model Reuse** - Use `Subscription`, `Invoice` models from Cashier
4. **Package Independence** - Must work without `filament-chip` (optional integration)

---

## Dependencies

### Required
- `aiarmada/cashier-chip` - Core billing engine
- `filament/filament` ^5.0 - Admin framework

### Optional
- `aiarmada/filament-chip` - Enhanced integration with purchase resources
- `aiarmada/filament-authz` - Role-based access control

---

## Success Criteria

- [ ] Full subscription CRUD in Filament admin
- [ ] Customer portal for self-service billing management
- [ ] MRR and subscription metrics on dashboard
- [ ] Invoice generation and PDF export
- [ ] PHPStan Level 6 compliance
- [ ] ≥85% test coverage
- [ ] Full translation support (en, ms)

---

## Navigation

**Next:** [02-subscription-management.md](02-subscription-management.md)
