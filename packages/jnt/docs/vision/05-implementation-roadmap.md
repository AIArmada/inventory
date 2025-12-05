# Implementation Roadmap

> **Document:** 05 of 05  
> **Package:** `aiarmada/jnt`  
> **Status:** Vision (API-Constrained)

---

## Overview

Phased delivery of the **API-constrained** JNT vision across **4 phases** spanning approximately **7-9 weeks**.

---

## Phase Summary

| Phase | Focus | Duration |
|-------|-------|----------|
| 1 | Enhanced Orders | 2-3 weeks |
| 2 | Tracking & Status | 2 weeks |
| 3 | Notifications | 1-2 weeks |
| 4 | Filament Integration | 2 weeks |

---

## Phase 1: Enhanced Orders (Weeks 1-3)

### Deliverables

```
Week 1-2:
├── Models
│   ├── JntOrder (enhanced)
│   └── JntOrderStatus enum
│
├── Services
│   ├── JntOrderService
│   │   ├── create()
│   │   ├── cancel()
│   │   └── syncStatus()
│   │
│   └── JntOrderBuilder
│
└── Events
    ├── JntOrderSubmitted
    └── JntOrderCancelled

Week 3:
├── Validation
│   └── OrderValidator
│
├── Tests
│   ├── OrderCreationTest
│   ├── OrderCancellationTest
│   └── OrderBuilderTest
│
└── Config updates
```

### Success Criteria
- [ ] Orders create via builder pattern
- [ ] Cancellation works with validation
- [ ] Status sync from API works
- [ ] Owner relationship functional

---

## Phase 2: Tracking & Status (Weeks 4-5)

### Deliverables

```
Week 4:
├── Models
│   └── JntTrackingEvent
│
├── Enums
│   └── TrackingStatus (normalized)
│
├── Services
│   ├── JntStatusMapper
│   └── JntTrackingService
│       ├── track()
│       ├── syncOrderTracking()
│       └── parseEvents()
│
└── DTOs
    ├── TrackingResult
    └── TrackingEvent

Week 5:
├── Webhook Handler
│   └── Enhanced webhook processing
│
├── Tests
│   ├── StatusMappingTest
│   ├── TrackingServiceTest
│   └── WebhookHandlerTest
│
└── Database migration
    └── jnt_tracking_events table
```

### Success Criteria
- [ ] Status normalization works
- [ ] Tracking events stored locally
- [ ] Webhook updates orders
- [ ] Status history available

---

## Phase 3: Notifications (Weeks 6-7)

### Deliverables

```
Week 6-7:
├── Notifications
│   └── JntShipmentNotification
│       ├── shipped template
│       ├── out_for_delivery template
│       ├── delivered template
│       └── failed template
│
├── Listeners
│   └── SendShipmentNotifications
│
├── Config
│   └── notifications settings
│
└── Tests
    └── NotificationTest
```

### Success Criteria
- [ ] Email notifications send
- [ ] Configurable notification types
- [ ] Notifiable from owner or receiver

---

## Phase 4: Filament (Weeks 8-9)

### Deliverables

```
Week 8:
├── Resources
│   ├── JntOrderResource (enhanced)
│   │   ├── Table with filters
│   │   ├── Status badges
│   │   └── Bulk actions
│   │
│   └── Relation Managers
│       └── TrackingEventsRelationManager

Week 9:
├── Widgets
│   ├── JntOrderStatsWidget
│   └── RecentOrdersWidget
│
├── Pages
│   └── TrackingPage
│
└── Actions
    ├── CancelOrderAction
    └── SyncTrackingAction
```

### Success Criteria
- [ ] Order resource complete
- [ ] Tracking timeline display
- [ ] Dashboard widgets work
- [ ] Bulk operations functional

---

## Database Migrations

```
Phase 1:
└── Enhance jnt_orders table

Phase 2:
└── create_jnt_tracking_events_table.php
```

---

## Removed from Scope

The following were **removed** because J&T API does not support them:

| Original Vision | Reason |
|-----------------|--------|
| Multi-carrier abstraction | Wrong package scope |
| Rate shopping engine | No rate API |
| Carrier selection rules | Single carrier only |
| Returns/RMA management | No returns API |
| Address validation | No validation API |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Test Coverage | ≥ 85% |
| PHPStan Level | 6 |
| Order Creation | < 2s |
| Tracking Sync | < 1s |

---

## Navigation

**Previous:** [04-notifications.md](04-notifications.md)
