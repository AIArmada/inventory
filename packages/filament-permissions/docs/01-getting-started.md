# Getting Started

This guide walks you through setting up Filament Permissions in your Laravel application.

## Prerequisites

Before installing, ensure you have:

- Laravel 12.x
- Filament 4.2+
- A User model with database table

## Installation Steps

### 1. Install via Composer

```bash
composer require aiarmada/filament-permissions
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=filament-permissions-config
```

This creates `config/filament-permissions.php`.

### 3. Install Spatie Permission

If not already installed, the package requires Spatie laravel-permission:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### 4. Configure User Model

Add the `HasRoles` trait to your User model:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### 5. Register the Plugin

In your Filament panel provider:

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
            ->plugins([
                FilamentPermissionsPlugin::make(),
            ]);
    }
}
```

### 6. Create Super Admin Role

Run the sync command or manually create:

```bash
php artisan permissions:sync
```

Or via tinker:

```php
use Spatie\Permission\Models\Role;

Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
```

### 7. Assign Super Admin to User

```php
$user = User::first();
$user->assignRole('Super Admin');
```

## Verification

1. Log in to your Filament panel
2. Navigate to **Access Control** in the sidebar
3. You should see **Roles**, **Permissions**, and **Users** menu items

## Next Steps

- [Configuration Guide](configuration.md) — Customize guards, panels, and features
- [Multi-Panel Setup](multi-panel.md) — Configure multiple panels with different access
- [CLI Reference](cli.md) — Available artisan commands
