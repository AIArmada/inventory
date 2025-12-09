# Filament Cashier Vision Progress

> **Package:** `aiarmada/filament-cashier`  
> **Last Updated:** December 9, 2025  
> **Dependencies:** `aiarmada/cashier` (wrapper for `laravel/cashier` + `aiarmada/cashier-chip`)

---

## Package Hierarchy

```
Payment Gateway APIs (Stripe, CHIP)
    │
    ├── laravel/cashier            ← Stripe billing
    │
    └── aiarmada/cashier-chip      ← CHIP billing
        │
        └── aiarmada/cashier       ← Unified multi-gateway wrapper
            │
            └── aiarmada/filament-cashier  ← THIS PACKAGE (Unified Filament Admin)
```

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Foundation Setup | 🔴 Not Started | 0% |
| Phase 2: Unified Subscription Resource | 🔴 Not Started | 0% |
| Phase 3: Multi-Gateway Dashboard | 🔴 Not Started | 0% |
| Phase 4: Customer Billing Portal | 🔴 Not Started | 0% |
| Phase 5: Invoicing & Reporting | 🔴 Not Started | 0% |
| Phase 6: Gateway Switching UI | 🔴 Not Started | 0% |

---

## Phase 1: Foundation Setup

### Package Structure
- [ ] `FilamentCashierServiceProvider`
- [ ] `FilamentCashierPlugin` for Filament panels
- [ ] Config file with navigation/gateway settings
- [ ] Resource translations (en, ms)

### Core Configuration
- [ ] Panel configuration options
- [ ] Gateway detection and availability
- [ ] Navigation group/sort settings
- [ ] Permission integration

### Gateway Detection
- [ ] Auto-detect installed gateways
- [ ] Graceful degradation for missing gateways
- [ ] Gateway availability indicators

---

## Phase 2: Unified Subscription Resource

### SubscriptionResource (Multi-Gateway)
- [ ] List all subscriptions across gateways
- [ ] Gateway column with badge/icon
- [ ] Status badges consistent across gateways
- [ ] Unified filters (gateway, status, plan)
- [ ] Gateway-specific actions delegated appropriately

### Subscription Infolist
- [ ] Gateway-aware detail display
- [ ] Unified subscription lifecycle info
- [ ] Gateway-specific metadata sections

### Subscription Actions
- [ ] Cancel (delegates to appropriate gateway)
- [ ] Resume (delegates to appropriate gateway)
- [ ] Swap plan (gateway-specific options)
- [ ] Create subscription (gateway selection)

### Create Subscription Form
- [ ] Gateway selector
- [ ] Dynamic plan options per gateway
- [ ] Payment method from selected gateway

---

## Phase 3: Multi-Gateway Dashboard

### Unified Stats Widgets
- [ ] `TotalMrrWidget` - Combined MRR across gateways
- [ ] `TotalSubscribersWidget` - All active subscribers
- [ ] `GatewayBreakdownWidget` - Revenue per gateway
- [ ] `UnifiedChurnWidget` - Combined churn metrics

### Gateway Comparison Widgets
- [ ] Revenue comparison chart (Stripe vs CHIP)
- [ ] Subscriber distribution by gateway
- [ ] Transaction volume by gateway

### Dashboard Page
- [ ] Combined billing dashboard
- [ ] Gateway tabs/filters
- [ ] Cross-gateway analytics

---

## Phase 4: Customer Billing Portal

### Unified Customer Resource
- [ ] List customers with gateway indicators
- [ ] Multi-gateway subscription view
- [ ] Payment methods across gateways
- [ ] Gateway-specific customer sync

### Customer Self-Service Portal
- [ ] View all subscriptions (any gateway)
- [ ] Manage payment methods per gateway
- [ ] Unified invoice history
- [ ] Gateway switching support

### Payment Methods Management
- [ ] List methods from all gateways
- [ ] Add method to specific gateway
- [ ] Set default per gateway

---

## Phase 5: Invoicing & Reporting

### Unified Invoice Resource
- [ ] List invoices from all gateways
- [ ] Gateway column/filter
- [ ] Download PDF (gateway-specific)
- [ ] Invoice status normalization

### Cross-Gateway Reports
- [ ] Revenue report (all gateways combined)
- [ ] Gateway comparison reports
- [ ] Subscription metrics by gateway
- [ ] Export with gateway breakdown

---

## Phase 6: Gateway Switching UI

### Subscription Migration
- [ ] Migrate subscription from Gateway A → B
- [ ] Preview migration impact
- [ ] Handle payment method transfer
- [ ] Proration calculations

### Gateway Management Page
- [ ] View active gateways
- [ ] Gateway health/status
- [ ] Configure default gateway
- [ ] Test gateway connectivity

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | ✅ Complete |
| [02-unified-subscriptions.md](02-unified-subscriptions.md) | ✅ Complete |
| [03-multi-gateway-dashboard.md](03-multi-gateway-dashboard.md) | ✅ Complete |
| [04-customer-portal.md](04-customer-portal.md) | ✅ Complete |
| [05-implementation-roadmap.md](05-implementation-roadmap.md) | ✅ Complete |

---

## Dependencies & Constraints

### Required Packages
| Package | Purpose |
|---------|---------|
| `aiarmada/cashier` | Unified multi-gateway billing wrapper |
| `filament/filament` | Filament admin framework |

### Optional Gateway Packages
| Package | Gateway | Features Enabled |
|---------|---------|------------------|
| `laravel/cashier` | Stripe | Stripe subscriptions, invoices |
| `aiarmada/cashier-chip` | CHIP | CHIP subscriptions, purchases |

### Optional Filament Packages
| Package | Integration |
|---------|-------------|
| `aiarmada/filament-cashier-chip` | Enhanced CHIP-specific UI |
| `aiarmada/filament-authz` | Role-based access control |

### Constraints
- Unified interface - delegates to gateway-specific packages
- No direct API calls - all via `aiarmada/cashier` abstractions
- Gateway-agnostic where possible, gateway-aware when necessary
- Graceful degradation if gateway package not installed

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
- Package structure defined as unified admin layer
- Multi-gateway architecture documented
- 6-phase implementation roadmap established
- Vision documents pending creation
