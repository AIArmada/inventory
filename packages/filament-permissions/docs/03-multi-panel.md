# Multi-Panel Setup

This guide covers configuring Filament Permissions for applications with multiple Filament panels.

## Overview

Multi-panel setups are common in applications that serve different user types:

- **Admin Panel** — Internal staff, administrators
- **Member Panel** — Customers, subscribers
- **Partner Panel** — Vendors, affiliates

Each panel can have:
- Different authentication guards
- Different role requirements
- Independent permission sets

## Basic Multi-Panel Configuration

### 1. Define Guards

In `config/auth.php`, define guards for each panel:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],
],
```

### 2. Configure Filament Permissions

In `config/filament-permissions.php`:

```php
'guards' => ['web', 'admin'],

'panel_guard_map' => [
    'admin' => 'admin',
    'member' => 'web',
],

'panel_roles' => [
    'admin' => ['Super Admin', 'Admin', 'Staff'],
    'member' => ['Super Admin', 'Member', 'Premium'],
],
```

### 3. Create Panel Providers

**Admin Panel:**

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentPermissions\FilamentPermissionsPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->plugins([
                FilamentPermissionsPlugin::make(),
            ]);
    }
}
```

**Member Panel:**

```php
<?php

namespace App\Providers\Filament;

use AIArmada\FilamentPermissions\FilamentPermissionsPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class MemberPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('member')
            ->path('dashboard')
            ->plugins([
                FilamentPermissionsPlugin::make(),
            ]);
    }
}
```

## How Authorization Works

### Automatic Middleware

When `auto_panel_middleware` is enabled, the plugin automatically configures:

```php
$panel
    ->authGuard('admin')  // From panel_guard_map
    ->middleware([
        'web',
        'auth:admin',
        'permission:access admin',  // Panel access permission
    ]);
```

### Role-Based Panel Access

The `AuthorizePanelRoles` middleware enforces `panel_roles`:

1. User authenticates via the panel's guard
2. Middleware checks if user has any role from `panel_roles.{panel_id}`
3. Super Admin role always bypasses this check
4. Access denied throws `AccessDeniedHttpException`

### Flow Diagram

```
Request → Auth Guard → Role Check → Panel Access
            ↓            ↓            ↓
        admin guard   Admin role   ✓ Allowed
        admin guard   Staff role   ✓ Allowed
        admin guard   Member role  ✗ Denied
        web guard     Member role  ✓ Allowed (member panel)
```

## Shared User Model

If using a single User model for all panels:

```php
'panel_guard_map' => [
    'admin' => 'web',
    'member' => 'web',
],

'panel_roles' => [
    'admin' => ['Super Admin', 'Admin'],
    'member' => ['Member', 'Customer'],
],
```

Both panels use the `web` guard but require different roles.

## Guard-Specific Permissions

Permissions are created per guard. A user with the `Admin` role on the `admin` guard won't have those permissions on the `web` guard unless explicitly assigned.

```php
// Create role for admin guard
Role::create(['name' => 'Admin', 'guard_name' => 'admin']);

// Create same role for web guard (separate)
Role::create(['name' => 'Admin', 'guard_name' => 'web']);
```

## Super Admin Universal Access

The Super Admin role provides universal panel access:

```php
'super_admin_role' => 'Super Admin',
```

Users with this role:
- Bypass `Gate::before()` for all permissions
- Access any panel regardless of `panel_roles`
- See all resources and navigation items

## Sync Roles Per Guard

Use the sync command to create roles for all guards:

```php
// config/filament-permissions.php
'sync' => [
    'roles' => [
        'Admin' => ['user.viewAny', 'user.create'],
        'Member' => ['order.viewAny'],
    ],
],
```

```bash
php artisan permissions:sync
```

This creates:
- `Admin` role with permissions for `web` guard
- `Admin` role with permissions for `admin` guard
- `Member` role with permissions for both guards

## Disabling Panel Role Authorization

To allow any authenticated user access to a panel:

```php
'features' => [
    'panel_role_authorization' => false,
],
```

Or leave `panel_roles.{panel_id}` empty:

```php
'panel_roles' => [
    'admin' => ['Super Admin', 'Admin'],
    'member' => [],  // Any authenticated user
],
```

## Troubleshooting

### User can't access panel

1. Check user has required role:
   ```php
   $user->hasAnyRole(['Super Admin', 'Admin']);
   ```

2. Verify role guard matches panel guard:
   ```php
   $user->roles->pluck('guard_name');
   ```

3. Check `panel_roles` configuration includes the role

### Permissions not working

1. Clear permission cache:
   ```bash
   php artisan permission:cache-reset
   ```

2. Verify permission exists for correct guard:
   ```php
   Permission::where('name', 'user.viewAny')
       ->where('guard_name', 'admin')
       ->exists();
   ```

### Super Admin not bypassing

1. Verify role name matches config:
   ```php
   config('filament-permissions.super_admin_role'); // 'Super Admin'
   ```

2. Check user has the role:
   ```php
   $user->hasRole('Super Admin');
   ```
