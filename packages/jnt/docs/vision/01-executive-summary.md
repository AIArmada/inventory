# JNT Vision: Executive Summary

> **Document:** 01 of 05  
> **Package:** `aiarmada/jnt` + `aiarmada/filament-jnt`  
> **Status:** Vision (API-Constrained)  
> **Last Updated:** December 5, 2025

---

## API Boundaries

This vision is constrained to what the **J&T Express API actually supports**:

### J&T Express API Capabilities
- ✅ Orders: Create (single/batch), cancel, query
- ✅ Tracking: By order ID or tracking number (single/batch)
- ✅ Waybills: PDF label generation
- ✅ Webhooks: Tracking status updates

### NOT Available in J&T API
- ❌ Rate quotes / shipping cost calculation
- ❌ Multi-carrier abstraction
- ❌ Returns/RMA management
- ❌ Address validation
- ❌ Carrier selection rules
- ❌ Service level comparison

---

## Package Hierarchy

```
aiarmada/jnt                    ← Core J&T Express SDK
    └── aiarmada/filament-jnt   ← Filament admin integration
```

---

## Current State Assessment

### Existing Capabilities

| Feature | Status | Notes |
|---------|--------|-------|
| Order Creation | ✅ Complete | Single and batch |
| Order Cancellation | ✅ Complete | Via API |
| Order Query | ✅ Complete | Status lookup |
| Parcel Tracking | ✅ Complete | Single and batch |
| Waybill Generation | ✅ Complete | PDF labels |
| Webhook Handling | ✅ Complete | Status updates |

### Realistic Gaps (Addressable)

| Gap | Solution | Priority |
|-----|----------|----------|
| Basic tracking UI | Enhanced tracking page | High |
| No status normalization | Unified status enum | High |
| Limited notifications | App-layer notifications | Medium |
| Basic Filament coverage | Enhanced admin tools | Medium |

---

## Vision Pillars (API-Constrained)

### 1. Enhanced Order Management
Improve order lifecycle with better validation, status sync, and batch operations.

### 2. Tracking Normalization
Unified status mapping and enhanced tracking display.

### 3. App-Layer Notifications
Customer notifications via Laravel events (not J&T API).

### 4. Improved Filament Admin
Comprehensive admin interface for orders, tracking, and webhooks.

---

## Vision Documents

| # | Document | Description |
|---|----------|-------------|
| 01 | Executive Summary | This document |
| 02 | [Enhanced Orders](02-enhanced-orders.md) | Order management improvements |
| 03 | [Tracking & Status](03-tracking-status.md) | Status normalization |
| 04 | [Notifications](04-notifications.md) | App-layer notifications |
| 05 | [Implementation Roadmap](05-implementation-roadmap.md) | Phased delivery |

---

## Key Constraints

1. **J&T Only** - This package handles J&T Express only, not multi-carrier
2. **No Rate API** - Cannot provide shipping quotes
3. **No Returns API** - Cannot manage RMA via API
4. **Package Scope** - Courier integration only

---

## Roadmap Overview

```
Phase 1: Enhanced Orders (2-3 weeks)
Phase 2: Tracking & Status (2 weeks)
Phase 3: Notifications (1-2 weeks)
Phase 4: Filament (2 weeks)
```

**Total: 7-9 weeks**

---

## Navigation

**Next:** [02-enhanced-orders.md](02-enhanced-orders.md)
