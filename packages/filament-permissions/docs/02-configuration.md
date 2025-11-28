# Configuration

Complete reference for `config/filament-permissions.php`.

## Guards

Define which authentication guards the package should manage:

```php
'guards' => ['web', 'admin'],
```

Permissions and roles are created for each configured guard. This allows different permission sets for different authentication systems.

## User Model

Specify your application's User model:

```php
'user_model' => App\Models\User::class,
```

The User resource uses this model for CRUD operations.

## Panel Guard Map

Map Filament panel IDs to specific guards:

```php
'panel_guard_map' => [
    'admin' => 'admin',    // Admin panel uses 'admin' guard
    'member' => 'web',     // Member panel uses 'web' guard
],
```

When `auto_panel_middleware` is enabled, the panel automatically:
- Sets the authentication guard
- Applies `auth:{guard}` middleware
- Adds `permission:access {panel-id}` middleware

## Panel Roles

Restrict panel access by role:

```php
'panel_roles' => [
    'admin' => ['Super Admin', 'Admin', 'Staff'],
    'member' => ['Super Admin', 'Member'],
],
```

Users must have at least one of the listed roles to access the panel. Super Admin always has access regardless of configuration.

**Note:** If empty or not configured, panel access falls back to guard-only restriction.

## Super Admin Role

The role name that bypasses all permission checks:

```php
'super_admin_role' => 'Super Admin',
```

Users with this role:
- Pass all `Gate::allows()` checks via `Gate::before()`
- Access all panels regardless of `panel_roles` config
- See all navigation items and resources

Set to empty string to disable super admin bypass.

## Navigation

Customize how resources appear in Filament navigation:

```php
'navigation' => [
    'group' => 'Access Control',
    'sort' => 90,
    'icons' => [
        'roles' => 'heroicon-o-key',
        'permissions' => 'heroicon-o-shield-check',
        'users' => 'heroicon-o-user-group',
    ],
],
```

## Sync Configuration

Define roles and permissions to sync from config:

```php
'sync' => [
    'permissions' => [
        'user.viewAny',
        'user.view',
        'user.create',
        'user.update',
        'user.delete',
        'order.viewAny',
        'order.view',
        'order.export',
    ],
    'roles' => [
        'Admin' => [
            'user.viewAny',
            'user.view',
            'user.create',
            'user.update',
        ],
        'Manager' => [
            'order.viewAny',
            'order.view',
            'order.export',
        ],
    ],
],
```

Run `php artisan permissions:sync` to apply changes.

## Feature Flags

Toggle package features:

```php
'features' => [
    // Enable permissions:doctor command
    'doctor' => true,

    // Enable permissions:generate-policies command
    'policy_generator' => true,

    // Show impersonation banner widget for super admins
    'impersonation_banner' => true,

    // Enable Permission Explorer page
    'permission_explorer' => true,

    // Enable stats overview widget
    'diff_widget' => true,

    // Enable import/export commands
    'export_import' => true,

    // Auto-configure panel middleware based on panel_guard_map
    'auto_panel_middleware' => true,

    // Enforce panel_roles restrictions
    'panel_role_authorization' => true,
],
```

## Permission Naming

Customize how abilities map to permission strings:

```php
'permission_naming' => [
    'ability_to_permission' => \AIArmada\FilamentPermissions\Support\DefaultAbilityToPermissionMapper::class,
],
```

The default mapper converts `User` + `viewAny` to `user.viewAny`.

### Custom Mapper

Create a custom mapper:

```php
<?php

namespace App\Support;

class CustomPermissionMapper
{
    public function __invoke(string $modelClass, string $ability): string
    {
        $base = class_basename($modelClass);
        
        return sprintf('%s:%s', strtolower($base), $ability);
    }
}
```

Register in config:

```php
'permission_naming' => [
    'ability_to_permission' => \App\Support\CustomPermissionMapper::class,
],
```

## Full Example

```php
<?php

return [
    'guards' => ['web', 'admin'],
    
    'user_model' => App\Models\User::class,
    
    'panel_guard_map' => [
        'admin' => 'admin',
        'member' => 'web',
    ],
    
    'panel_roles' => [
        'admin' => ['Super Admin', 'Admin'],
        'member' => ['Super Admin', 'Member'],
    ],
    
    'super_admin_role' => 'Super Admin',
    
    'enable_user_resource' => true,
    
    'navigation' => [
        'group' => 'Access Control',
        'sort' => 90,
        'icons' => [
            'roles' => 'heroicon-o-key',
            'permissions' => 'heroicon-o-shield-check',
            'users' => 'heroicon-o-user-group',
        ],
    ],
    
    'sync' => [
        'permissions' => [
            'user.viewAny', 'user.view', 'user.create', 'user.update', 'user.delete',
            'role.viewAny', 'role.view', 'role.create', 'role.update', 'role.delete',
        ],
        'roles' => [
            'Admin' => ['user.viewAny', 'user.view', 'user.create', 'user.update'],
        ],
    ],
    
    'permission_naming' => [
        'ability_to_permission' => \AIArmada\FilamentPermissions\Support\DefaultAbilityToPermissionMapper::class,
    ],
    
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
