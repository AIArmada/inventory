# Filament Cashier Chip Vision Progress

> **Package:** `aiarmada/filament-cashier-chip`  
> **Last Updated:** December 9, 2025  
> **Dependencies:** `aiarmada/cashier-chip` → `aiarmada/chip` → Chip API

---

## Package Hierarchy

```
Chip Payment Gateway API (External)
    └── aiarmada/chip              ← Core SDK for Chip API
        └── aiarmada/cashier-chip  ← Laravel Cashier-style billing integration
            └── aiarmada/filament-cashier-chip  ← THIS PACKAGE (Filament Admin UI)
```

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Foundation Setup | 🔴 Not Started | 0% |
| Phase 2: Subscription Management UI | 🔴 Not Started | 0% |
| Phase 3: Customer Billing Portal | 🔴 Not Started | 0% |
| Phase 4: Admin Dashboard & Widgets | 🔴 Not Started | 0% |
| Phase 5: Invoicing & Reporting | 🔴 Not Started | 0% |

---

## Phase 1: Foundation Setup

### Package Structure
- [ ] `FilamentCashierChipServiceProvider`
- [ ] `FilamentCashierChipPlugin` for Filament panels
- [ ] Config file with navigation/resource settings
- [ ] Resource translations (en, ms)

### Core Configuration
- [ ] Panel configuration options
- [ ] Navigation group/sort settings
- [ ] Permission integration with `filament-authz`
- [ ] Multi-tenancy support configuration

### Dependencies
- [ ] Verify `cashier-chip` service bindings
- [ ] Integration with `filament-chip` (optional)
- [ ] Leverage existing Chip models via Cashier

---

## Phase 2: Subscription Management UI

### SubscriptionResource
- [ ] List view with status badges, plan info, billing dates
- [ ] Infolist with full subscription details
- [ ] Create action (new subscription via SubscriptionBuilder)
- [ ] Edit action (swap plans, update quantity)
- [ ] Cancel/Resume actions with confirmation modals
- [ ] Extend trial action

### SubscriptionItemRelationManager
- [ ] Items table within subscription
- [ ] Quantity adjustments
- [ ] Price display integration

### Subscription Actions
- [ ] Bulk pause/resume
- [ ] Bulk plan migration
- [ ] Export subscriptions

### Filters & Tabs
- [ ] Status filter (active, canceled, on_trial, past_due)
- [ ] Plan filter
- [ ] Date range filters
- [ ] Quick tabs for common views

---

## Phase 3: Customer Billing Portal

### CustomerResource
- [ ] Customer list with billing status
- [ ] Customer infolist with Chip customer details
- [ ] Create customer action (sync to Chip)
- [ ] Link to user model

### Customer Subscriptions Tab
- [ ] Inline subscriptions manager
- [ ] Create subscription for customer

### Payment Methods Management
- [ ] List recurring tokens/payment methods
- [ ] Set default payment method
- [ ] Delete payment method action
- [ ] Add payment method via setup purchase

### Billing History
- [ ] Invoice list per customer
- [ ] Payment history
- [ ] Download invoice PDF action

---

## Phase 4: Admin Dashboard & Widgets

### Dashboard Widgets
- [ ] `MRRWidget` - Monthly Recurring Revenue
- [ ] `ActiveSubscribersWidget` - Total active subscribers
- [ ] `ChurnRateWidget` - Subscription churn metrics
- [ ] `RevenueChartWidget` - Revenue trend over time
- [ ] `SubscriptionDistributionWidget` - Plans breakdown
- [ ] `TrialConversionsWidget` - Trial to paid conversion rate

### Dashboard Page
- [ ] Dedicated billing dashboard page
- [ ] Customizable widget layout
- [ ] Date range filters for metrics

### Real-time Updates
- [ ] Widget polling for live data
- [ ] Webhook-triggered cache invalidation

---

## Phase 5: Invoicing & Reporting

### InvoiceResource
- [ ] Invoice listing with status
- [ ] Invoice infolist with line items
- [ ] Mark as paid action
- [ ] Send invoice email action
- [ ] Download PDF action

### Invoice Generator
- [ ] Create manual invoices
- [ ] Line item builder
- [ ] Tax calculation integration

### Reports
- [ ] Revenue reports with export
- [ ] Subscription analytics export
- [ ] Failed payment reports
- [ ] Churn analysis reports

### Scheduled Reports
- [ ] Email scheduled reports
- [ ] Report generation commands

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | ✅ Complete |
| [02-subscription-management.md](02-subscription-management.md) | ✅ Complete |
| [03-customer-portal.md](03-customer-portal.md) | ✅ Complete |
| [04-dashboard-widgets.md](04-dashboard-widgets.md) | ✅ Complete |
| [05-implementation-roadmap.md](05-implementation-roadmap.md) | ✅ Complete |

---

## Dependencies & Constraints

### Required Packages
| Package | Purpose |
|---------|---------|
| `aiarmada/cashier-chip` | Core billing logic, Billable trait, Subscription model |
| `aiarmada/chip` | Chip API SDK, Purchase/Client models |
| `filament/filament` | Filament admin framework |

### Optional Integrations
| Package | Integration |
|---------|-------------|
| `aiarmada/filament-chip` | Shared resources for Chip purchases |
| `aiarmada/filament-authz` | Role-based access to billing features |

### Constraints
- All subscription logic delegated to `cashier-chip` package
- UI-only concerns handled here (no business logic duplication)
- Database access through Cashier models only
- No direct Chip API calls (all via Cashier abstractions)

---

## Legend

| Symbol | Meaning |
|--------|---------|
| 🔴 | Not Started |
| 🟡 | In Progress |
| 🟢 | Completed |

---

## Notes

### December 9, 2025
- Initial vision documentation created
- Package structure defined following existing patterns
- 5-phase implementation roadmap established
- Dependency hierarchy documented
- Vision documents pending creation
