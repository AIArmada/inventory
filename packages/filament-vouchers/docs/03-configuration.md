---
title: Configuration
---

# Configuration

Complete configuration options for Filament Vouchers.

## Configuration File

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-vouchers-config
```

## Options

### Navigation Group

```php
'navigation_group' => 'E-commerce',
```

Controls the navigation group label for voucher resources. Set to `null` to place resources at the root level.

### Default Currency

```php
'default_currency' => 'MYR',
```

ISO-4217 currency code used for displaying monetary values in widgets. Individual voucher records store their own configured currency.

### Polling Interval

```php
'polling_interval' => 60,
```

How frequently (in seconds) voucher tables poll for updates. Set to `null` to disable polling.

### Resource Navigation Sort

```php
'resources' => [
    'navigation_sort' => [
        'vouchers' => 40,
        'voucher_usage' => 41,
        'voucher_wallets' => 42,
    ],
],
```

Control the order of resources in the navigation sidebar.

### Order Resource

```php
'order_resource' => null,
// or
'order_resource' => \App\Filament\Resources\OrderResource::class,
```

When set, voucher usage records can link to order detail pages.

### Owner Types

```php
'owners' => [
    [
        'label' => 'Store',
        'model' => App\Models\Store::class,
        'title_attribute' => 'name',
        'subtitle_attribute' => 'email',      // Optional
        'search_attributes' => ['name', 'code'],
    ],
],
```

Define owner types for multi-tenant voucher assignment:

| Key | Required | Description |
|-----|----------|-------------|
| `label` | Yes | Human-readable label for the owner type |
| `model` | Yes | Eloquent model class |
| `title_attribute` | Yes | Attribute used for display |
| `subtitle_attribute` | No | Secondary attribute for display |
| `search_attributes` | No | Attributes to search when filtering |

Leave empty to only allow global vouchers.

## Full Example

```php
// config/filament-vouchers.php
return [
    'navigation_group' => 'Marketing',
    'default_currency' => 'USD',
    'polling_interval' => 30,

    'resources' => [
        'navigation_sort' => [
            'vouchers' => 10,
            'voucher_usage' => 11,
            'voucher_wallets' => 12,
        ],
    ],

    'order_resource' => \App\Filament\Resources\OrderResource::class,

    'owners' => [
        [
            'label' => 'Store',
            'model' => App\Models\Store::class,
            'title_attribute' => 'name',
            'search_attributes' => ['name', 'code'],
        ],
        [
            'label' => 'Vendor',
            'model' => App\Models\Vendor::class,
            'title_attribute' => 'company_name',
            'subtitle_attribute' => 'email',
            'search_attributes' => ['company_name', 'email'],
        ],
    ],
];
```
