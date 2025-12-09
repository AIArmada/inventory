<?php

declare(strict_types=1);

$tablePrefix = env('AUTHZ_TABLE_PREFIX', env('COMMERCE_TABLE_PREFIX', ''));

$tables = [
    'permission_groups' => $tablePrefix . 'authz_permission_groups',
    'role_templates' => $tablePrefix . 'authz_role_templates',
    'permission_group_permission' => $tablePrefix . 'authz_permission_group_permission',
    'scoped_permissions' => $tablePrefix . 'authz_scoped_permissions',
    'access_policies' => $tablePrefix . 'authz_access_policies',
    'audit_logs' => $tablePrefix . 'authz_audit_logs',
    'permission_snapshots' => $tablePrefix . 'authz_permission_snapshots',
    'permission_requests' => $tablePrefix . 'authz_permission_requests',
    'delegations' => $tablePrefix . 'authz_delegations',
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('AUTHZ_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => $tables,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'Settings',
        'sort' => 99,
        'icons' => [
            'roles' => 'heroicon-o-shield-check',
            'permissions' => 'heroicon-o-key',
            'users' => 'heroicon-o-users',
            'groups' => 'heroicon-o-folder',
            'templates' => 'heroicon-o-document-duplicate',
            'policies' => 'heroicon-o-document-text',
            'audit_logs' => 'heroicon-o-clipboard-document-list',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */
    'guards' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'super_admin_role' => 'super_admin',
    'default_guard' => 'web',
    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'enable_user_resource' => false,
    'user_model' => App\Models\User::class,
    'panel_guard_map' => [],
    'features' => [
        'permission_explorer' => false,
        'diff_widget' => false,
        'impersonation_banner' => false,
        'auto_panel_middleware' => false,
        'panel_role_authorization' => false,
        'permission_groups' => true,
        'role_templates' => true,
        'scoped_permissions' => true,
        'access_policies' => true,
        'audit_logging' => true,
        'wildcard_permissions' => true,
        'role_inheritance' => true,
        'permission_matrix' => true,
        'role_hierarchy' => true,
        'stats_widget' => true,
        'hierarchy_widget' => true,
        'activity_widget' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hierarchies
    |--------------------------------------------------------------------------
    */
    'hierarchies' => [
        'max_role_depth' => 5,
        'max_group_depth' => 5,
        'cache_hierarchy' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
        'async' => true,
        'retention_days' => 90,
        'log_access_checks' => false,
        'exclude_events' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | ABAC Policies
    |--------------------------------------------------------------------------
    */
    'policies' => [
        'combining_algorithm' => 'deny_overrides',
        'cache_policies' => true,
        'cache_ttl' => 300,
        'default_type' => 'basic', // basic, hierarchical, contextual, temporal, abac, composite
        'stubs_path' => null, // Custom stubs directory, defaults to package stubs
        'methods' => [
            'viewAny',
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'forceDelete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Discovery
    |--------------------------------------------------------------------------
    |
    | Auto-discover permissions from Filament resources, pages, and widgets.
    |
    */
    'discovery' => [
        'enabled' => env('AUTHZ_DISCOVERY_ENABLED', true),
        'auto_sync' => env('AUTHZ_DISCOVERY_AUTO_SYNC', false),
        'discover_all_panels' => true,
        'panels' => [], // Leave empty to discover all panels

        // Cache settings
        'cache' => [
            'enabled' => true,
            'ttl' => 3600, // 1 hour
        ],

        // Namespace filtering
        'namespaces' => [
            'include' => [
                // 'App\\Filament\\*',
            ],
            'exclude' => [
                // 'App\\Filament\\Admin\\Resources\\Shield*',
            ],
        ],

        // Exclude specific classes
        'exclude' => [
            // 'App\\Filament\\Resources\\ShieldResource',
        ],

        // Exclude patterns (applied to class basename)
        'exclude_patterns' => [
            // 'Shield*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Enterprise Features
    |--------------------------------------------------------------------------
    */
    'enterprise' => [
        // Permission Versioning
        'versioning' => [
            'enabled' => true,
            'auto_snapshot_on_sync' => false,
            'max_snapshots' => 50,
        ],

        // Permission Delegation
        'delegation' => [
            'enabled' => true,
            'max_delegation_depth' => 3,
            'allow_redelegation' => false,
        ],

        // Approval Workflows
        'approvals' => [
            'enabled' => false,
            'auto_approve_for_roles' => [],
            'notification_channels' => ['mail', 'database'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Access Configuration
    |--------------------------------------------------------------------------
    */
    'panel_user_role' => null, // Role auto-assigned to new users for panel access
    'panel_roles' => [
        // 'admin' => ['Super Admin', 'Admin'],
        // 'app' => ['Super Admin', 'User'],
    ],
];
