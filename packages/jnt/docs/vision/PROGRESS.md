# JNT Vision Progress

> **Package:** `aiarmada/jnt` + `aiarmada/filament-jnt`  
> **Last Updated:** January 3, 2025  
> **Scope:** API-Constrained (J&T Express API only)

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Enhanced Orders | 🟡 In Progress | 80% |
| Phase 2: Tracking & Status | 🟢 Completed | 100% |
| Phase 3: Notifications | 🔴 Not Started | 0% |
| Phase 4: Filament Integration | 🟢 Completed | 100% |

---

## Phase 1: Enhanced Orders

### Models
- [x] `JntOrder` model (enhanced with tracking fields)
- [ ] `JntOrderStatus` enum

### Services
- [x] `JntExpressService::createOrder()`
- [x] `JntExpressService::cancelOrder()`
- [ ] `JntOrderService::syncStatus()`

### Builder
- [x] `OrderBuilder::orderId()`
- [x] `OrderBuilder::sender()`
- [x] `OrderBuilder::receiver()`
- [x] `OrderBuilder::addItem()`
- [x] `OrderBuilder::packageInfo()`
- [x] `OrderBuilder::cashOnDelivery()`
- [x] `OrderBuilder::build()`

### Events
- [x] `OrderCreatedEvent`
- [x] `OrderCancelledEvent`

### Tests
- [ ] `OrderCreationTest`
- [ ] `OrderCancellationTest`
- [ ] `OrderBuilderTest`

---

## Phase 2: Tracking & Status

### Database
- [x] `create_jnt_tracking_events_table` (existing)

### Models
- [x] `JntTrackingEvent` model (enhanced with getNormalizedStatus)

### Enums
- [x] `TrackingStatus` enum (normalized)
- [x] `ScanTypeCode` enum (existing)

### Services
- [x] `JntStatusMapper` implementation
- [x] `JntTrackingService::track()`
- [x] `JntTrackingService::syncOrderTracking()`
- [x] `JntTrackingService::parseTrackingData()`
- [x] `JntTrackingService::getNormalizedStatus()`
- [x] `JntTrackingService::getCurrentStatus()`
- [x] `JntTrackingService::batchSyncTracking()`
- [x] `JntTrackingService::getOrdersNeedingTrackingUpdate()`

### DTOs
- [x] `TrackingData` DTO (existing)
- [x] `TrackingDetailData` DTO (existing)

### Events
- [x] `JntOrderStatusChanged` event
- [x] `TrackingUpdatedEvent` (existing)

### Webhook
- [x] Enhanced webhook handler

### Tests
- [ ] `StatusMappingTest`
- [ ] `TrackingServiceTest`
- [ ] `WebhookHandlerTest`

---

## Phase 3: Notifications (App-Layer)

### Notifications
- [ ] `JntShipmentNotification::shipped`
- [ ] `JntShipmentNotification::outForDelivery`
- [ ] `JntShipmentNotification::delivered`
- [ ] `JntShipmentNotification::failed`

### Listeners
- [ ] `SendShipmentNotifications` listener

### Config
- [ ] Notification settings in config

### Tests
- [ ] `NotificationTest`

---

## Phase 4: Filament Integration

### Resources
- [x] `JntOrderResource` (enhanced with normalized status display)
- [x] `JntTrackingEventResource` (enhanced with normalized status)
- [x] `TrackingEventsRelationManager` (existing)

### Widgets
- [x] `JntStatsWidget` (enhanced with returns tracking, 6 stats)

### Pages
- [x] `ViewJntOrder` (enhanced with sync action header)

### Actions
- [x] `CancelOrderAction` - Cancel order with reason selection
- [x] `SyncTrackingAction`

### Tables/Infolists
- [x] `JntOrderTable` - Normalized status badges with icons
- [x] `JntOrderInfolist` - Normalized status display
- [x] `JntTrackingEventTable` - Normalized status with filters

### Tests
- [ ] `FilamentResourceTests`

---

## Vision Documents

| Document | Status |
|----------|--------|
| [01-executive-summary.md](01-executive-summary.md) | ✅ Revised |
| [02-enhanced-orders.md](02-enhanced-orders.md) | ✅ New |
| [03-tracking-status.md](03-tracking-status.md) | ✅ New |
| [04-notifications.md](04-notifications.md) | ✅ New |
| [05-implementation-roadmap.md](05-implementation-roadmap.md) | ✅ New |

---

## Removed from Scope

These features were removed because J&T API does not support them:

| Feature | Reason |
|---------|--------|
| Multi-carrier abstraction | Wrong package scope (J&T only) |
| Rate shopping engine | No rate/quote API |
| Carrier selection rules | Single carrier only |
| Returns/RMA management | No returns API |
| Address validation | No validation API |

---

## Legend

| Symbol | Meaning |
|--------|---------|
| 🔴 | Not Started |
| 🟡 | In Progress |
| 🟢 | Completed |

---

## Notes

### January 3, 2025
- Phase 2 (Tracking & Status) fully implemented
- Created TrackingStatus enum with normalized statuses
- Created JntStatusMapper to map ScanTypeCode to TrackingStatus
- Created JntTrackingService with full sync functionality
- Added JntOrderStatusChanged event for status transitions
- Enhanced JntTrackingEvent model with getNormalizedStatus method
- Registered new services in JntServiceProvider
- **Filament Integration Completed:**
  - Enhanced JntOrderTable with normalized status badges and icons
  - Enhanced JntOrderInfolist with normalized status display
  - Enhanced JntTrackingEventTable with normalized status filters
  - Enhanced JntStatsWidget with 6 stats including returns tracking
  - Created SyncTrackingAction for manual tracking sync
  - Added sync action to ViewJntOrder page header
- Tests pending

### December 5, 2025
- **Phase 4 (Filament Integration) fully completed**
  - Created CancelOrderAction with grouped cancellation reasons from CancellationReason enum
  - Added cancel action to ViewJntOrder page header alongside sync action
- Vision documents revised to API-constrained scope
- Package remains J&T only (not multi-carrier)
- Removed rate shopping, returns, carrier selection (no API support)
- Focus on enhanced order management, status normalization, app-layer notifications
