# Filament J&T Express

> Filament 5 admin plugin for managing J&T Express shipping orders and tracking, powered by the `aiarmada/jnt` package.

## Features

- **Shipping Orders** – view and manage J&T Express orders with full details
- **Tracking Events** – monitor real-time tracking status updates
- **Webhook Logs** – debug incoming webhook notifications
- **Global Search** – search across orders, tracking numbers, and statuses
- **Auto-Polling** – real-time updates without page refresh
- **Operational Actions** – sync tracking and cancel orders via the J&T API

## Installation

```bash
composer require aiarmada/filament-jnt
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-jnt-config
```

Register the plugin in your panel provider:

```php
use AIArmada\FilamentJnt\FilamentJntPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentJntPlugin::make(),
        ]);
}
```

---

## Configuration

```php
// config/filament-jnt.php
return [
    // Navigation group for all J&T resources
    'navigation_group' => 'Shipping',

    // Badge color for navigation items
    'navigation_badge_color' => 'primary',

    // Auto-refresh interval for tables
    'polling_interval' => '30s',

    // Resource navigation ordering
    'resources' => [
        'navigation_sort' => [
            'orders' => 10,
            'tracking_events' => 20,
            'webhook_logs' => 30,
        ],
    ],

    // Table display formats
    'tables' => [
        'datetime_format' => 'Y-m-d H:i:s',
    ],
];
```

---

## Resources

### Shipping Orders

View J&T Express shipping orders with:

- Order ID and tracking number
- Customer code and status
- Sender and receiver information
- Package details (weight, dimensions)
- Timestamps (created, updated, synced)

**Global search:** `order_id`, `tracking_number`, `customer_code`, `last_status`

### Tracking Events

Monitor parcel tracking events:

- Tracking number and order reference
- Scan type and description
- Location information
- Event timestamps

**Global search:** `tracking_number`, `order_reference`, `scan_type_name`, `description`

### Webhook Logs

Debug webhook notifications:

- Tracking number and order reference
- Processing status (pending, processed, failed)
- Raw payload data
- Error messages if any

**Global search:** `tracking_number`, `order_reference`, `processing_status`

---

## Auto-Polling

Tables automatically refresh based on the configured interval:

```php
'polling_interval' => '30s',  // Refresh every 30 seconds
```

Set to `null` to disable auto-polling.

---

## Documentation

- [Configuration Reference](docs/configuration.md) – All configuration options
- [Resources](docs/resources.md) – Available resources and customization

---

## Requirements

- PHP 8.4+
- Laravel 12+
- Filament 5.0+
- aiarmada/jnt package

---

## License

MIT License. See [LICENSE](LICENSE) for details.
