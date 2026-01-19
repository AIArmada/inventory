<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'roles' => 'Roles',
        'permissions' => 'Permissions',
        'group' => 'Settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Labels
    |--------------------------------------------------------------------------
    */
    'resource' => [
        'role' => [
            'label' => 'Role',
            'plural_label' => 'Roles',
        ],
        'permission' => [
            'label' => 'Permission',
            'plural_label' => 'Permissions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */
    'form' => [
        'name' => 'Name',
        'name_placeholder' => 'Enter role name',
        'name_helper' => 'A unique identifier for this role',
        'guard_name' => 'Guard',
        'guard_name_helper' => 'The authentication guard this role applies to',
        'permissions' => 'Permissions',
        'team' => 'Team',
        'team_placeholder' => 'Select a team...',
        'select_all' => 'Select All',
        'select_all_message' => 'Enables/Disables all permissions for this role',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */
    'table' => [
        'name' => 'Name',
        'guard_name' => 'Guard',
        'permissions_count' => 'Permissions',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */
    'filter' => [
        'guard' => 'Guard',
        'all_guards' => 'All Guards',
        'has_permissions' => 'Has Permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    */
    'tabs' => [
        'resources' => 'Resources',
        'pages' => 'Pages',
        'widgets' => 'Widgets',
        'custom' => 'Custom',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */
    'search' => [
        'resources' => 'Search Resources',
        'resources_placeholder' => 'Type to filter resources...',
        'pages' => 'Search Pages',
        'pages_placeholder' => 'Type to filter pages...',
        'widgets' => 'Search Widgets',
        'widgets_placeholder' => 'Type to filter widgets...',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections
    |--------------------------------------------------------------------------
    */
    'section' => [
        'role_details' => 'Role Details',
        'role_details_description' => 'Define the role name and the guard it applies to.',
        'resources_count' => ':count resource|:count resources',
        'permissions_count' => ':count permission|:count permissions',
        'pages_count' => ':count page|:count pages',
        'widgets_count' => ':count widget|:count widgets',
        'pages' => 'Page Permissions',
        'pages_description' => 'Control access to individual pages.',
        'widgets' => 'Widget Permissions',
        'widgets_description' => 'Control visibility of dashboard widgets.',
        'custom' => 'Custom Permissions',
        'custom_description' => 'Additional application-specific permissions.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */
    'empty_state' => [
        'heading' => 'No roles yet',
        'description' => 'Create roles to manage access permissions for your users.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notification' => [
        'role_created' => 'Role created successfully',
        'role_updated' => 'Role updated successfully',
        'role_deleted' => 'Role deleted successfully',
        'permissions_synced' => 'Permissions synced successfully',
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */
    'forbidden' => 'You do not have permission to access this resource.',

    /*
    |--------------------------------------------------------------------------
    | Resource Permission Prefixes
    |--------------------------------------------------------------------------
    */
    'resource_permission_prefixes' => [
        'view' => 'View',
        'viewAny' => 'View Any',
        'create' => 'Create',
        'update' => 'Update',
        'delete' => 'Delete',
        'deleteAny' => 'Delete Any',
        'forceDelete' => 'Force Delete',
        'forceDeleteAny' => 'Force Delete Any',
        'restore' => 'Restore',
        'restoreAny' => 'Restore Any',
        'reorder' => 'Reorder',
        'replicate' => 'Replicate',
    ],

    /*
    |--------------------------------------------------------------------------
    | Commands
    |--------------------------------------------------------------------------
    */
    'command' => [
        'discover' => [
            'description' => 'Discover Filament resources, pages, and widgets',
        ],
        'sync' => [
            'description' => 'Sync permissions from discovered entities to database',
        ],
        'super_admin' => [
            'description' => 'Assign super admin role to a user',
        ],
        'seeder' => [
            'description' => 'Generate a seeder for existing roles and permissions',
        ],
        'policies' => [
            'description' => 'Generate policy classes for Filament resources',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonation
    |--------------------------------------------------------------------------
    */
    'impersonate' => [
        'action' => 'Impersonate',
        'modal_heading' => 'Impersonate User',
        'modal_description' => 'You are about to impersonate this user. You will be logged in as them and can perform actions on their behalf.',
        'confirm' => 'Start Impersonation',
        'leave' => 'Leave Impersonation',
        'banner_message' => 'You are currently impersonating :name.',
        'left_message' => 'You have left impersonation and returned to your account.',
        'redirect_label' => 'Redirect To',
        'redirect_helper' => 'Choose where to redirect after impersonating this user.',
        'redirect_frontpage' => 'Frontpage',
        'redirect_panel_suffix' => 'Panel',
    ],
];
