# Configuration Reference

Complete configuration options for Filament J&T Express.

## Configuration File

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-jnt-config
```

---

## Options

### Navigation Group

```php
'navigation_group' => 'Shipping',
```

Controls the navigation group label for all J&T resources. Set to `null` to place resources at the root level.

---

### Navigation Badge Color

```php
'navigation_badge_color' => 'primary',
```

Color for navigation badges showing record counts. Supports Filament color names: `primary`, `success`, `warning`, `danger`, `info`, `gray`.

---

### Polling Interval

```php
'polling_interval' => '30s',
```

How frequently tables auto-refresh for real-time updates. Accepts:

- String with unit: `'30s'`, `'1m'`, `'5m'`
- `null` to disable polling

---

### Resource Navigation Sort

```php
'resources' => [
    'navigation_sort' => [
        'orders' => 10,
        'tracking_events' => 20,
        'webhook_logs' => 30,
    ],
],
```

Control the order of resources in the navigation sidebar. Lower numbers appear first.

---

### Table Datetime Format

```php
'tables' => [
    'datetime_format' => 'Y-m-d H:i:s',
],
```

PHP datetime format used for displaying timestamps in tables.

---

## Full Example

```php
// config/filament-jnt.php
return [
    'navigation_group' => 'Shipping',
    'navigation_badge_color' => 'success',
    'polling_interval' => '1m',

    'resources' => [
        'navigation_sort' => [
            'orders' => 1,
            'tracking_events' => 2,
            'webhook_logs' => 3,
        ],
    ],

    'tables' => [
        'datetime_format' => 'd M Y, H:i',
    ],
];
```

---

## Environment Variables

No environment variable overrides are provided. Use config publishing for customization.
