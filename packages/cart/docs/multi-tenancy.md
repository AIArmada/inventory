# Multi-Tenancy (Owner Scoping)

The Cart package supports multi-tenant applications via **owner scoping**. When enabled, cart storage and operations are automatically scoped to the current owner (merchant, store, tenant, etc).

Owner resolution is centralized in `commerce-support` via the `OwnerResolverInterface` binding.

## Configuration

Enable owner scoping in `config/cart.php`:

```php
'owner' => [
    'enabled' => true,
],
```

### Environment Variables

```env
CART_OWNER_ENABLED=true
COMMERCE_OWNER_RESOLVER=App\Support\CurrentOwnerResolver
```

## Implementing an Owner Resolver

Create a resolver that implements `AIArmada\CommerceSupport\Contracts\OwnerResolverInterface`:

```php
<?php

namespace App\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;

class CurrentOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        return auth()->user()?->merchant;
    }
}
```

## How It Works

- When `CART_OWNER_ENABLED=true` and an owner is resolved, cart storage is isolated per owner.
- When the resolver returns `null`, carts behave as single-tenant (no owner isolation).

## Fail-Fast Behavior

When owner scoping is enabled, the Cart package expects an `OwnerResolverInterface` binding to exist (provided by `commerce-support`). If it is missing, Cart throws a `RuntimeException` during boot.

## Example: Filament Integration

If you use Filament tenancy, you can implement an owner resolver based on the current Filament tenant:

```php
<?php

namespace App\Support;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

class FilamentOwnerResolver implements OwnerResolverInterface
{
    public function resolve(): ?Model
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Model ? $tenant : null;
    }
}
```
