---
title: Overview
---

# Filament JNT

Filament admin panel integration for the JNT package. Provides resources, actions, and widgets for managing J&T Express shipping orders in your Filament panels.

---

## Features

- **Order Management Resource** - View and manage shipping orders
- **Tracking Events Resource** - View tracking history and events
- **Webhook Logs Resource** - Monitor webhook activity
- **Cancel Order Action** - Cancel orders with reason selection
- **Sync Tracking Action** - Manually sync tracking information
- **Dashboard Widget** - Stats overview with caching

---

## Resources

### JntOrderResource

Full-featured resource for shipping orders:

| Feature | Description |
|---------|-------------|
| List View | Paginated table with filters and search |
| View Page | Detailed order information |
| Columns | Order ID, tracking, status, weight, value, dates |
| Filters | Status, express type, service type, problems, delivery |
| Actions | View, cancel order, sync tracking |
| Search | Order ID, tracking number, customer code |

### JntTrackingEventResource

View tracking history:

| Feature | Description |
|---------|-------------|
| List View | All tracking events with timestamps |
| Columns | Tracking number, scan type, location, time |
| Filters | By order, by status, by date |

### JntWebhookLogResource

Monitor webhook activity:

| Feature | Description |
|---------|-------------|
| List View | Webhook payloads and processing status |
| Columns | Bill code, processed status, exceptions |
| Filters | By status, by date |

---

## Actions

### CancelOrderAction

Cancel shipping orders with reason selection:

- Grouped cancellation reasons (customer, merchant, delivery, payment)
- Custom reason support for "Other"
- Confirmation modal with form
- Owner-scoped authorization
- Automatic status update

### SyncTrackingAction

Manually sync tracking information:

- Fetch latest tracking from J&T API
- Update order status
- Create new tracking events
- Confirmation modal
- Error handling with notifications

---

## Widget

### JntStatsWidget

Dashboard statistics widget:

| Stat | Description |
|------|-------------|
| Total Orders | All shipping orders |
| Delivered | Orders with delivery date |
| In Transit | Orders being delivered |
| Pending | Awaiting pickup |
| Returns | Orders being returned |
| Problems | Orders requiring attention |

Features:
- 30-second cache for performance
- Owner-scoped when enabled
- 6-column layout
- Color-coded status indicators

---

## Architecture

```
filament-jnt/
├── src/
│   ├── FilamentJntPlugin.php        # Plugin registration
│   ├── FilamentJntServiceProvider.php
│   ├── Actions/
│   │   ├── CancelOrderAction.php
│   │   └── SyncTrackingAction.php
│   ├── Resources/
│   │   ├── BaseJntResource.php      # Shared resource logic
│   │   ├── JntOrderResource.php
│   │   ├── JntTrackingEventResource.php
│   │   └── JntWebhookLogResource.php
│   └── Widgets/
│       └── JntStatsWidget.php
└── config/
    └── filament-jnt.php
```

---

## Requirements

- PHP 8.4+
- Filament v5
- `aiarmada/jnt` package (core package)
- `aiarmada/commerce-support` (for multi-tenancy)

---

## Quick Start

```php
// In your Filament panel provider
use AIArmada\FilamentJnt\FilamentJntPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentJntPlugin::make(),
        ]);
}
```

See [Installation](02-installation.md) for detailed setup instructions.
