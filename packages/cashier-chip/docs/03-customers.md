---
title: Customer Management
---

# Customer Management

Cashier CHIP manages customers through CHIP's Client API. Each billable model is linked to a CHIP client ID.

## Creating Customers

### Create a CHIP Customer

```php
// Create a new CHIP customer
$user->createAsChipCustomer();

// Create with additional options
$user->createAsChipCustomer([
    'phone' => '+60123456789',
    'street_address' => '123 Main Street',
    'city' => 'Kuala Lumpur',
    'country' => 'MY',
]);
```

### Create If Not Exists

```php
// Only create if the user doesn't already have a CHIP ID
$user->createAsChipCustomerIfNotExists();
```

### Auto-Creation

Customers are automatically created when you:

- Create a checkout session
- Create a subscription
- Charge the customer

## Checking Customer Status

```php
// Get the CHIP client ID
$chipId = $user->chipId();

// Check if user has a CHIP ID
if ($user->hasChipId()) {
    // User is a CHIP customer
}
```

## Updating Customers

```php
// Update customer information in CHIP
$user->updateChipCustomer([
    'full_name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+60123456789',
]);
```

## Syncing Customer Data

### Sync to CHIP

```php
// Sync local user data to CHIP
$user->syncToChip();
```

This syncs the following fields (if present on your model):
- `name` → `full_name`
- `email` → `email`
- `phone` → `phone`

### Custom Sync Mapping

Override the `chipCustomerData` method for custom mapping:

```php
class User extends Authenticatable
{
    use Billable;
    
    /**
     * Get the customer data to sync with CHIP.
     */
    public function chipCustomerData(): array
    {
        return [
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone_number,
            'street_address' => $this->line1,
            'city' => $this->city,
            'country' => 'MY',
        ];
    }
}
```

## Retrieving Customer from CHIP

```php
// Get the full CHIP client object
$client = $user->asChipCustomer();

// Access client properties
echo $client->full_name;
echo $client->email;
```

## Customer Balance

CHIP doesn't support customer credit balances like Stripe. For credit functionality, use vouchers or implement a local credit system.

## Multiple Customer Models

You can use different models for billing:

```php
// In a service provider
use AIArmada\CashierChip\CashierChip;

public function boot(): void
{
    CashierChip::useCustomerModel(Team::class);
}
```

The model must:
1. Use the `Billable` trait
2. Have a factory for testing

```php
class Team extends Model
{
    use Billable;
    
    protected static function newFactory()
    {
        return TeamFactory::new();
    }
}
```

## Database Schema

The `chip_customers` table stores the relationship:

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `billable_id` | uuid | Foreign key to billable model |
| `billable_type` | string | Billable model class |
| `chip_id` | string | CHIP client ID |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
