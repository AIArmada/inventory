# Filament Permissions

A comprehensive Filament v4 permissions suite powered by Spatie laravel-permission with multi-guard support, panel-aware gating, and rich admin UX.

## Features

- **Multi-Guard Support** — Configure multiple authentication guards with automatic permission generation per guard
- **Panel-Aware Authorization** — Per-panel role mapping with middleware-based access control
- **Complete CRUD Resources** — Role, Permission, and User management with relation managers
- **Developer Macros** — `requiresPermission()` and `requiresRole()` for Actions, Navigation, Columns, and Filters
- **Super Admin Bypass** — Automatic `Gate::before` for unrestricted access
- **Permission Explorer** — Grouped permission viewer with role assignments
- **Stats Widget** — Dashboard widget showing permission statistics and unused permissions
- **CLI Tools** — Sync, doctor, import/export commands for permission management

## Requirements

- PHP 8.2+
- Laravel 12+
- Filament 4.2+
- Spatie laravel-permission 6.0+

## Installation

```bash
composer require aiarmada/filament-permissions
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=filament-permissions-config
```

Publish and run Spatie Permission migrations:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

## Setup

### Add HasRoles Trait

Add the `HasRoles` trait to your User model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

### Register Plugin

Register the plugin in your Filament panel provider:

```php
use AIArmada\FilamentPermissions\FilamentPermissionsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentPermissionsPlugin::make(),
        ]);
}
```

## Configuration

The configuration file (`config/filament-permissions.php`) provides extensive customization:

```php
return [
    // Supported authentication guards
    'guards' => ['web', 'admin'],

    // User model class
    'user_model' => App\Models\User::class,

    // Map panel IDs to specific guards
    'panel_guard_map' => [
        'admin' => 'admin',
        'member' => 'web',
    ],

    // Roles allowed per panel (empty = guard-only restriction)
    'panel_roles' => [
        'admin' => ['Super Admin', 'Admin'],
        'member' => ['Super Admin', 'Member'],
    ],

    // Role name that bypasses all permission checks
    'super_admin_role' => 'Super Admin',

    // Enable built-in User resource
    'enable_user_resource' => true,

    // Navigation settings
    'navigation' => [
        'group' => 'Access Control',
        'sort' => 90,
        'icons' => [
            'roles' => 'heroicon-o-key',
            'permissions' => 'heroicon-o-shield-check',
            'users' => 'heroicon-o-user-group',
        ],
    ],

    // Define roles and permissions to sync from config
    'sync' => [
        'roles' => [
            'Admin' => ['user.viewAny', 'user.create', 'user.update'],
        ],
        'permissions' => [
            'user.viewAny', 'user.view', 'user.create', 'user.update', 'user.delete',
        ],
    ],

    // Feature toggles
    'features' => [
        'doctor' => true,
        'policy_generator' => true,
        'impersonation_banner' => true,
        'permission_explorer' => true,
        'diff_widget' => true,
        'export_import' => true,
        'auto_panel_middleware' => true,
        'panel_role_authorization' => true,
    ],
];
```

## Usage

### Permission Macros

The package provides convenient macros for common Filament components:

#### Actions

```php
use Filament\Actions\Action;

Action::make('export')
    ->requiresPermission('order.export');

Action::make('admin-settings')
    ->requiresRole('Admin');

Action::make('analytics')
    ->requiresRole(['Admin', 'Analyst']);
```

#### Table Columns

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('internal_notes')
    ->requiresPermission('order.view_internal');

TextColumn::make('profit_margin')
    ->requiresRole('Finance');
```

#### Table Filters

```php
use Filament\Tables\Filters\Filter;

Filter::make('high_value')
    ->requiresPermission('order.filter_high_value');
```

#### Navigation Items

```php
use Filament\Navigation\NavigationItem;

NavigationItem::make('Reports')
    ->requiresPermission('report.viewAny');

NavigationItem::make('Settings')
    ->requiresRole(['Admin', 'Super Admin']);
```

### Multi-Panel Setup

Configure panel-specific access with role restrictions:

```php
// config/filament-permissions.php
'panel_guard_map' => [
    'admin' => 'admin',
    'customer' => 'web',
],

'panel_roles' => [
    'admin' => ['Super Admin', 'Admin', 'Staff'],
    'customer' => ['Super Admin', 'Customer'],
],
```

The `AuthorizePanelRoles` middleware automatically enforces these restrictions. Super Admin role always has universal access.

### Super Admin Bypass

Users with the configured super admin role automatically bypass all permission checks via `Gate::before`:

```php
// Any user with 'Super Admin' role passes all gates
Gate::allows('any-permission'); // true for Super Admin
```

## Commands

### Sync Permissions

Sync roles and permissions from configuration:

```bash
php artisan permissions:sync

# Clear cache after sync
php artisan permissions:sync --flush-cache
```

### Doctor

Diagnose permission configuration issues:

```bash
php artisan permissions:doctor
```

Detects:
- Roles/permissions with invalid guards
- Unused permissions (not attached to any role)
- Empty roles (no permissions assigned)

### Export

Export current roles and permissions to JSON:

```bash
php artisan permissions:export

# Custom path
php artisan permissions:export storage/backup/permissions.json
```

### Import

Import roles and permissions from JSON:

```bash
php artisan permissions:import storage/permissions.json

# Flush cache after import
php artisan permissions:import storage/permissions.json --flush-cache
```

### Generate Policies

Generate policy stubs with permission-based authorization:

```bash
php artisan permissions:generate-policies Post
```

Creates a policy with methods mapping to permissions like `post.viewAny`, `post.create`, etc.

## Permission Naming Convention

The package uses a consistent `{model}.{ability}` naming convention:

| Permission | Description |
|-----------|-------------|
| `user.viewAny` | View user list |
| `user.view` | View individual user |
| `user.create` | Create new users |
| `user.update` | Update existing users |
| `user.delete` | Delete users |
| `user.restore` | Restore soft-deleted users |
| `user.forceDelete` | Permanently delete users |

## UI Components

### Permission Explorer

A dedicated page showing all permissions grouped by domain with role assignments:

- Navigate to **Access Control → Permission Explorer**
- View permissions organized by prefix (e.g., `user.*`, `order.*`)
- See which roles have each permission

### Stats Widget

Dashboard widget displaying:
- Total permissions count
- Total roles count
- Unused permissions (warning indicator)

## Testing

```bash
./vendor/bin/pest tests/src/FilamentPermissions --parallel
```

## License

MIT License. See [LICENSE](LICENSE) for details.
