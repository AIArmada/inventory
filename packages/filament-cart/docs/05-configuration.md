# Configuration

Complete configuration reference for the filament-cart package.

## Publishing Configuration

```bash
php artisan vendor:publish --tag="filament-cart-config"
```

Creates `config/filament-cart.php`.

## Configuration Options

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Group
    |--------------------------------------------------------------------------
    |
    | The navigation group where cart resources will be displayed in Filament.
    |
    */
    'navigation_group' => 'E-Commerce',

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | Real-time update interval in seconds for Filament tables.
    | Set to 0 or null to disable polling.
    |
    */
    'polling_interval' => 30,

    /*
    |--------------------------------------------------------------------------
    | Global Conditions
    |--------------------------------------------------------------------------
    |
    | Enable automatic application of global conditions to all carts.
    | When enabled, conditions marked as "is_global" will be automatically
    | applied to new carts and re-evaluated when items change.
    |
    */
    'enable_global_conditions' => true,

    /*
    |--------------------------------------------------------------------------
    | Dynamic Rule Factory
    |--------------------------------------------------------------------------
    |
    | The factory class used to resolve dynamic condition rules.
    | Must implement AIArmada\Cart\Contracts\RulesFactoryInterface.
    |
    */
    'dynamic_rules_factory' => \AIArmada\Cart\Services\BuiltInRulesFactory::class,

    /*
    |--------------------------------------------------------------------------
    | Event Synchronization
    |--------------------------------------------------------------------------
    |
    | Configure how cart events synchronize to normalized models.
    |
    */
    'synchronization' => [
        /*
        | Queue synchronization for better performance in high-traffic apps.
        | Requires queue configuration in your Laravel application.
        */
        'queue_sync' => false,

        /*
        | Queue connection to use for synchronization jobs.
        */
        'queue_connection' => 'default',

        /*
        | Queue name for synchronization jobs.
        */
        'queue_name' => 'cart-sync',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | Customize Filament resource behavior.
    |
    */
    'resources' => [
        /*
        | Navigation sort order for resources.
        | Lower numbers appear first in the navigation.
        */
        'navigation_sort' => [
            'carts' => 30,
        ],
    ],
];
```

## Environment Variables

You can override configuration via environment variables:

```env
# Disable global conditions
FILAMENT_CART_GLOBAL_CONDITIONS=false

# Enable queue synchronization
FILAMENT_CART_QUEUE_SYNC=true
FILAMENT_CART_QUEUE_CONNECTION=redis
FILAMENT_CART_QUEUE_NAME=cart-sync
```

Then reference in config:

```php
'enable_global_conditions' => env('FILAMENT_CART_GLOBAL_CONDITIONS', true),

'synchronization' => [
    'queue_sync' => env('FILAMENT_CART_QUEUE_SYNC', false),
    'queue_connection' => env('FILAMENT_CART_QUEUE_CONNECTION', 'default'),
    'queue_name' => env('FILAMENT_CART_QUEUE_NAME', 'cart-sync'),
],
```

## Custom Rules Factory

Register a custom rules factory for dynamic conditions:

```php
// config/filament-cart.php
'dynamic_rules_factory' => \App\Services\CustomRulesFactory::class,
```

Your factory must implement `RulesFactoryInterface`:

```php
<?php

namespace App\Services;

use AIArmada\Cart\Contracts\RulesFactoryInterface;

class CustomRulesFactory implements RulesFactoryInterface
{
    public function createRules(string $key, array $metadata = []): array
    {
        return match ($key) {
            'my-custom-rule' => [
                fn (array $payload): bool => /* your logic */,
            ],
            default => [],
        };
    }
    
    public function canCreateRules(string $key): bool
    {
        return in_array($key, ['my-custom-rule']);
    }
    
    public function getAvailableKeys(): array
    {
        return ['my-custom-rule'];
    }
}
```

## Navigation Customization

Change the navigation group for all cart resources:

```php
'navigation_group' => 'Shop Management',
```

Or customize per-resource by extending:

```php
class CustomCartResource extends CartResource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Custom Group';
    }
}
```
