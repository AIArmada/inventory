---
title: Billing Portal
---

# Billing Portal

The billing portal provides customer self-service for managing subscriptions, payment methods, and invoices.

## Overview

The billing portal is a separate Filament panel that customers can access to:

- View active subscriptions
- Cancel or resume subscriptions
- Add/remove payment methods
- Download invoice history

## Enabling the Portal

### Register the Panel Provider

```php
// config/app.php
'providers' => [
    // ...
    AIArmada\FilamentCashierChip\BillingPanelProvider::class,
],
```

Or register programmatically:

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->register(
        \AIArmada\FilamentCashierChip\BillingPanelProvider::class
    );
}
```

### Access the Portal

By default, the portal is available at:

```
https://yourapp.com/billing
```

## Portal Pages

### BillingDashboard

Overview page showing:
- Current subscriptions summary
- Payment method status
- Recent invoices

### Subscriptions

Manage active subscriptions:
- View subscription details
- Cancel subscriptions (grace period applies)
- Resume canceled subscriptions

**Customer Actions:**

```php
// Cancel subscription (ends at period end)
$this->cancelSubscription($subscriptionId);

// Resume if in grace period
$this->resumeSubscription($subscriptionId);
```

### PaymentMethods

Manage saved payment methods:
- View saved cards
- Set default payment method
- Add new payment methods via CHIP checkout
- Delete payment methods

**Customer Actions:**

```php
// Add payment method (redirects to CHIP)
$this->getAddPaymentMethodUrl();

// Set as default
$this->setAsDefault($paymentMethodId);

// Delete payment method
$this->deletePaymentMethod($paymentMethodId);
```

### Invoices

View and download invoice history:
- List all invoices
- View invoice details
- Download PDF (if renderer configured)

## Configuration

### Basic Settings

```php
// config/filament-cashier-chip.php
'billing' => [
    'panel_id' => 'billing',
    'path' => 'billing',
    'brand_name' => 'My App Billing',
    'primary_color' => '#6366f1',
],
```

### Authentication

```php
'billing' => [
    'login_enabled' => true,    // Show login page
    'auth_guard' => 'web',      // Auth guard to use
    'allowed_roles' => [],       // Empty = all authenticated
],
```

### Feature Toggles

```php
'billing' => [
    'features' => [
        'subscriptions' => true,
        'payment_methods' => true,
        'invoices' => true,
    ],
],
```

### Billable Model

By default, the portal uses the authenticated user. For team billing:

```php
'billing' => [
    'billable_model' => \App\Models\Team::class,
],
```

The `InteractsWithBillable` trait resolves the billable:

1. If user matches `billable_model`, use user
2. If user has `currentTeam()` method, use team
3. Fall back to user

### Custom Redirects

```php
'billing' => [
    'redirects' => [
        'after_payment_method_added' => '/billing/payment-methods?success=1',
    ],
],
```

## Customizing the Portal

### Custom Panel Provider

Create your own panel provider:

```php
namespace App\Providers\Filament;

use AIArmada\FilamentCashierChip\BillingPanelProvider;
use Filament\Panel;

class CustomBillingPanelProvider extends BillingPanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->brandName('Acme Billing')
            ->brandLogo(asset('images/logo.svg'))
            ->colors([
                'primary' => '#3b82f6',
            ])
            ->topNavigation()
            ->sidebarCollapsibleOnDesktop(false);
    }
}
```

### Custom Pages

Extend the built-in pages:

```php
namespace App\Filament\Billing\Pages;

use AIArmada\FilamentCashierChip\Pages\Subscriptions as BaseSubscriptions;

class Subscriptions extends BaseSubscriptions
{
    protected string $view = 'billing.pages.subscriptions';
    
    public function upgradeSubscription(string $subscriptionId): void
    {
        // Custom upgrade logic
    }
}
```

### Custom Views

Publish and customize views:

```bash
php artisan vendor:publish --tag=filament-cashier-chip-views
```

Views are published to `resources/views/vendor/filament-cashier-chip/`.

## InteractsWithBillable Trait

All portal pages use this trait for common functionality:

```php
trait InteractsWithBillable
{
    // Get the billable model for current user
    protected function getBillable(): ?Model;
    
    // Get payment methods
    protected function getPaymentMethods(): Collection;
    
    // Get default payment method
    protected function getDefaultPaymentMethod(): mixed;
    
    // Check if billable has a method
    protected function billableHasMethod(string $method): bool;
    
    // Get billing panel ID
    protected function getBillingPanelId(): string;
    
    // Generate billing route
    protected function billingRoute(string $name, array $parameters = []): string;
}
```

## Security Considerations

### Authentication

The portal requires authentication by default. Ensure your auth guard is properly configured:

```php
'billing' => [
    'auth_guard' => 'web',
    'login_enabled' => true,
],
```

### Role-Based Access

Restrict portal access to specific roles:

```php
'billing' => [
    'allowed_roles' => ['customer', 'subscriber'],
],
```

### Owner Scoping

All queries are automatically owner-scoped:
- Users can only see their own subscriptions
- Payment methods are filtered by billable
- Invoices are scoped to the customer

## Notifications

The portal uses Filament notifications for user feedback:

```php
// Success notification
Notification::make()
    ->title(__('Subscription cancelled'))
    ->success()
    ->send();

// Error notification
Notification::make()
    ->title(__('Failed to cancel subscription'))
    ->body($e->getMessage())
    ->danger()
    ->send();
```

## Translations

Customize portal text via translations:

```bash
php artisan vendor:publish --tag=filament-cashier-chip-translations
```

Translation keys:
- `filament-cashier-chip::filament-cashier-chip.subscriptions.title`
- `filament-cashier-chip::filament-cashier-chip.payment_methods.title`
- etc.

## Next Steps

- [Widgets](06-widgets.md) – Dashboard analytics
