---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- Filament 5+
- aiarmada/customers package

## Composer Installation

Install the plugin via Composer:

```bash
composer require aiarmada/filament-customers
```

The plugin will auto-register via Laravel's package discovery.

## Register Plugin

Add the plugin to your Filament panel:

```php
use AIArmada\FilamentCustomers\FilamentCustomersPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->path('admin')
        ->plugins([
            FilamentCustomersPlugin::make(),
        ]);
}
```

## Dependencies

Ensure you have the core customers package installed:

```bash
composer require aiarmada/customers
```

Run migrations if you haven't already:

```bash
php artisan migrate
```

## Verification

Visit your Filament admin panel. You should see:
- **Customers** resource in the CRM navigation group
- **Segments** resource in the CRM navigation group
- **Customer Stats** widget on the dashboard (if enabled)
- **Top Customers** widget on the dashboard (if enabled)

## Default Configuration

The plugin works out of the box with sensible defaults:

- Navigation icon: `heroicon-o-users` (Customers), `heroicon-o-user-group` (Segments)
- Navigation group: "CRM"
- Navigation sort: Customers (1), Segments (2)
- Owner scoping: Automatically applied if enabled in config
- Widgets: Enabled on dashboard

## Customization Options

You can customize the plugin by extending resources:

```php
use AIArmada\FilamentCustomers\Resources\CustomerResource;

class CustomCustomerResource extends CustomerResource
{
    protected static ?string $navigationGroup = 'Sales';
    
    protected static ?int $navigationSort = 10;
}
```

Then register your custom resource:

```php
$panel->resources([
    CustomCustomerResource::class,
]);
```

## Policies

The plugin uses Laravel policies for authorization. Ensure your User model can authorize:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    // Your user model
}
```

Policies are automatically registered:
- `CustomerPolicy` - Customer authorization
- `SegmentPolicy` - Segment authorization
- `AddressPolicy` - Address authorization
- `CustomerNotePolicy` - Note authorization
- `WishlistPolicy` - Wishlist authorization

## Multi-Tenancy Setup

If using multi-tenancy, ensure owner context is available:

```php
// In your Filament panel provider or middleware

use AIArmada\CommerceSupport\Support\OwnerContext;

// Set tenant context
OwnerContext::setOwner($tenant);

// Or in Filament middleware
protected function provideTenantContext(): void
{
    $tenant = Filament::getTenant();
    OwnerContext::setOwner($tenant);
}
```

## Testing

Create a test customer to verify installation:

```php
use AIArmada\Customers\Models\Customer;

Customer::create([
    'first_name' => 'Test',
    'last_name' => 'Customer',
    'email' => 'test@example.com',
    'status' => 'active',
]);
```

Then visit the Customers resource in your Filament panel.

## Troubleshooting

### Customers Not Appearing

**Problem**: Customer list is empty even though customers exist in database.

**Solution**: Check owner scoping:

```php
// Verify owner context is set
$owner = OwnerContext::resolve();
dd($owner); // Should not be null in multi-tenant mode
```

### Navigation Not Showing

**Problem**: CRM navigation group not appearing.

**Solution**: Ensure plugin is registered in panel:

```php
// app/Providers/Filament/AdminPanelProvider.php
return $panel->plugins([
    FilamentCustomersPlugin::make(),
]);
```

### Permission Denied

**Problem**: Cannot view or edit customers.

**Solution**: Implement policy or allow all for testing:

```php
// app/Policies/CustomerPolicy.php
public function viewAny(User $user): bool
{
    return true; // For testing only
}
```

## Next Steps

- [Configuration](03-configuration.md) - Configure the plugin
- [Resources](04-resources.md) - Learn about resources
- [Customization](06-customization.md) - Customize the plugin
