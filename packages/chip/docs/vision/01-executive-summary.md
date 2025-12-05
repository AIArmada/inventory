# Chip Vision: Executive Summary

> **Document:** 01 of 05  
> **Package:** `aiarmada/chip` + `aiarmada/filament-chip`  
> **Status:** Vision (API-Constrained)  
> **Last Updated:** December 5, 2025

---

## API Boundaries

This vision is constrained to what the **Chip API actually supports**:

### Chip Collect API
- ✅ Purchases (create, get, cancel, refund, capture, release)
- ✅ Recurring tokens (create via `forceRecurring`, charge, delete)
- ✅ Clients (CRUD operations)
- ✅ Account (balance, turnover, company statements)
- ✅ Webhooks (CRUD, signature verification)
- ✅ Payment methods listing

### Chip Send API
- ✅ Send instructions (disbursements)
- ✅ Bank accounts (CRUD)
- ✅ Groups (organize bank accounts)
- ✅ Send limits
- ✅ Webhooks for Send events

### NOT Available in Chip API
- ❌ Native subscription management
- ❌ Billing templates API
- ❌ Dispute/chargeback endpoints
- ❌ Analytics/reporting API
- ❌ Rate quotes

---

## Package Hierarchy

```
aiarmada/chip                    ← Core payment gateway SDK
    └── aiarmada/filament-chip   ← Filament admin integration
```

---

## Current State Assessment

### Existing Capabilities

| Feature | Status | Notes |
|---------|--------|-------|
| Payment Lifecycle | ✅ Complete | Create, capture, refund, void |
| Pre-authorization | ✅ Complete | Hold and capture flow |
| FPX Banking | ✅ Complete | Malaysian bank transfers |
| E-Wallets | ✅ Complete | Touch 'n Go, Boost, etc. |
| Card Payments | ✅ Complete | Visa, Mastercard |
| Webhooks | ✅ Complete | All event types handled |
| Payout Operations | ✅ Complete | Mass payouts via Chip Send |
| Recurring Tokens | ✅ Partial | Token storage, charge capability |

### Realistic Gaps (Addressable)

| Gap | Solution | Priority |
|-----|----------|----------|
| No recurring automation | App-layer scheduling | High |
| Basic webhook handling | Enhanced pipeline | Medium |
| Limited analytics | Local data aggregation | Medium |
| Minimal Filament coverage | Enhanced admin tools | Medium |

---

## Vision Pillars (API-Constrained)

### 1. Recurring Payment Automation
Build **app-layer** recurring payment scheduling using Chip's existing token + charge APIs.

### 2. Enhanced Webhook Pipeline
Improve webhook processing with enrichment, routing, retry logic, and monitoring.

### 3. Local Analytics Dashboard
Aggregate metrics from local `chip_purchases` data - NOT from Chip API.

### 4. Improved Filament Admin
Comprehensive admin interface for purchases, tokens, and webhooks.

### 5. Developer Experience
Better builders, validation, error handling, and testing utilities.

---

## Strategic Impact Matrix

| Feature | Business Value | Technical Complexity | Priority |
|---------|---------------|---------------------|----------|
| Recurring Automation | 🔴 Critical | Medium | P0 |
| Enhanced Webhooks | 🟡 High | Low | P1 |
| Local Analytics | 🟢 Medium | Low | P2 |
| Filament Enhancements | 🟢 Medium | Medium | P2 |

---

## Vision Documents

| # | Document | Description |
|---|----------|-------------|
| 01 | Executive Summary | This document |
| 02 | [Recurring Payments](02-recurring-payments.md) | App-layer recurring billing |
| 03 | [Enhanced Webhooks](03-enhanced-webhooks.md) | Advanced webhook handling |
| 04 | [Local Analytics](04-local-analytics.md) | Revenue analytics from local data |
| 05 | [Implementation Roadmap](05-implementation-roadmap.md) | Phased delivery plan |

---

## Key Constraints

1. **No Chip Subscription API** - All subscription logic must be app-layer
2. **No Chip Analytics API** - All metrics computed from local database
3. **No Dispute API** - Cannot implement chargeback workflows
4. **Package Scope** - This package handles Chip API only, not general billing

---

## Dependencies

### External
- Chip Collect API
- Chip Send API
- Webhook delivery infrastructure

### Internal Packages
- `aiarmada/commerce-support` - Money handling

---

## Roadmap Overview

```
Phase 1: Recurring Payments (3-4 weeks)
    │
    ├── ChipRecurringSchedule model (local)
    ├── Scheduled payment jobs
    ├── Token lifecycle management
    └── Retry logic for failed charges

Phase 2: Enhanced Webhooks (2 weeks)
    │
    ├── Enriched payload processing
    ├── Improved routing
    └── Retry & monitoring

Phase 3: Local Analytics (2 weeks)
    │
    ├── Aggregation from purchases
    ├── Dashboard widgets
    └── Export functionality

Phase 4: Filament (2-3 weeks)
    │
    ├── Enhanced resources
    ├── Dashboard widgets
    └── Bulk operations
```

---

## Success Criteria

- [ ] Recurring payments work via app-layer scheduler
- [ ] Webhook processing is reliable with retry
- [ ] Revenue metrics computed from local data
- [ ] Comprehensive Filament admin
- [ ] PHPStan Level 6 compliance
- [ ] ≥85% test coverage

---

## Navigation

**Next:** [02-recurring-payments.md](02-recurring-payments.md)
