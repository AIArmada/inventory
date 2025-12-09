<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\FilamentAuthz\Enums\SetupStage;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class SetupCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'authz:setup
        {--fresh : Start completely fresh (drops tables)}
        {--force : Force setup without confirmations}
        {--minimal : Minimal setup without interactive prompts}
        {--tenant= : Configure for specific tenant model}
        {--panel= : Configure for specific panel}
        {--skip-policies : Skip policy generation}
        {--skip-permissions : Skip permission generation}';

    /**
     * @var string
     */
    protected $description = 'Interactive setup wizard for Filament Authz';

    /**
     * @var array<string, mixed>
     */
    protected array $state = [];

    public function handle(): int
    {
        if ($this->isProhibited()) {
            return Command::FAILURE;
        }

        $this->welcome();
        $this->detectEnvironment();
        $this->configurePackage();
        $this->setupDatabase();
        $this->setupRoles();
        $this->setupPermissions();
        $this->setupPolicies();
        $this->setupSuperAdmin();
        $this->verify();
        $this->showCompletion();

        return Command::SUCCESS;
    }

    protected function isProhibited(): bool
    {
        // Check if running in production without force
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Cannot run setup in production without --force flag.');

            return true;
        }

        return false;
    }

    protected function welcome(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════════╗');
        $this->line('║                                                           ║');
        $this->line('║   🔐 Filament Authz Setup Wizard                          ║');
        $this->line('║                                                           ║');
        $this->line('║   Enterprise-grade authorization for Filament             ║');
        $this->line('║                                                           ║');
        $this->line('╚═══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    protected function detectEnvironment(): void
    {
        $this->info(SetupStage::Detection->icon().' Detecting environment...');
        $this->newLine();

        // Detect Spatie Permission
        $spatieInstalled = class_exists(\Spatie\Permission\PermissionServiceProvider::class);
        $this->displayDetection('Spatie Permission', $spatieInstalled, 'Required');

        if (! $spatieInstalled) {
            $this->error('Spatie Permission is required. Run: composer require spatie/laravel-permission');
            exit(1);
        }

        // Detect Filament panels
        $panels = collect(Filament::getPanels());
        $this->displayDetection('Filament Panels', $panels->isNotEmpty(), $panels->count().' found');
        $this->state['panels'] = $panels->keys()->toArray();

        // Detect existing config
        $hasConfig = file_exists(config_path('filament-authz.php'));
        $this->displayDetection('Existing Config', $hasConfig, $hasConfig ? 'Will be updated' : 'Will be created');

        // Detect User model
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        $hasRoles = class_exists($userModel) && in_array(\Spatie\Permission\Traits\HasRoles::class, class_uses_recursive($userModel));
        $this->displayDetection('User Model HasRoles', $hasRoles, $userModel);
        $this->state['userModel'] = $userModel;
        $this->state['hasRoles'] = $hasRoles;

        // Detect tenancy
        $hasTenancy = $panels->some(fn ($panel) => $panel->hasTenancy());
        $this->displayDetection('Multi-Tenancy', $hasTenancy, $hasTenancy ? 'Enabled' : 'Not configured');
        $this->state['tenancy'] = $hasTenancy;

        // Detect guards
        $guards = array_keys(config('auth.guards', []));
        $this->displayDetection('Auth Guards', true, implode(', ', $guards));
        $this->state['guards'] = $guards;

        $this->newLine();
    }

    protected function configurePackage(): void
    {
        if ($this->option('minimal')) {
            $this->state['superAdminRole'] = 'Super Admin';
            $this->state['panelUserRole'] = 'Panel User';
            $this->state['permissionFormat'] = 'dot';
            $this->state['features'] = ['hierarchies', 'audit', 'discovery'];
            $this->publishConfig();

            return;
        }

        $this->info(SetupStage::Configuration->icon().' Configuration');
        $this->newLine();

        // Super Admin Role
        $this->state['superAdminRole'] = text(
            label: 'What should the Super Admin role be called?',
            default: 'Super Admin',
            hint: 'This role bypasses all permission checks'
        );

        // Default Panel User Role
        $createPanelUser = confirm(
            label: 'Create a default Panel User role?',
            default: true,
            hint: 'Automatically assigned to new users for basic panel access'
        );
        $this->state['panelUserRole'] = $createPanelUser ? 'Panel User' : null;

        // Permission format
        $this->state['permissionFormat'] = select(
            label: 'Permission naming format:',
            options: [
                'dot' => 'Dot notation (user.viewAny)',
                'colon' => 'Colon notation (User:viewAny)',
                'underscore' => 'Underscore notation (user_viewAny)',
            ],
            default: 'dot'
        );

        // Features to enable
        $this->state['features'] = multiselect(
            label: 'Enable features:',
            options: [
                'hierarchies' => 'Permission Hierarchies',
                'temporal' => 'Temporal Permissions',
                'abac' => 'ABAC Policy Engine',
                'audit' => 'Audit Trail',
                'discovery' => 'Entity Discovery',
            ],
            default: ['hierarchies', 'audit', 'discovery']
        );

        // Panel configuration
        if (count($this->state['panels']) > 1) {
            $panelGuards = [];
            $panelRoles = [];

            foreach ($this->state['panels'] as $panelId) {
                $guard = select(
                    label: "Guard for '{$panelId}' panel:",
                    options: array_combine($this->state['guards'], $this->state['guards'])
                );
                $panelGuards[$panelId] = $guard;

                $roles = text(
                    label: "Roles allowed in '{$panelId}' panel (comma-separated):",
                    default: $this->state['superAdminRole']
                );
                $panelRoles[$panelId] = array_map('trim', explode(',', $roles));
            }

            $this->state['panelGuards'] = $panelGuards;
            $this->state['panelRoles'] = $panelRoles;
        }

        $this->publishConfig();
        $this->newLine();
    }

    protected function publishConfig(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'filament-authz-config',
            '--force' => true,
        ]);
    }

    protected function setupDatabase(): void
    {
        $this->info(SetupStage::Database->icon().' Database Setup');
        $this->newLine();

        // Check for existing tables
        $hasExistingTables = Schema::hasTable('permissions');

        if ($hasExistingTables && ! $this->option('fresh')) {
            if (! $this->option('minimal') && ! $this->option('force')) {
                $action = select(
                    label: 'Permission tables already exist. What would you like to do?',
                    options: [
                        'migrate' => 'Run new migrations only',
                        'fresh' => 'Drop and recreate all tables (DATA LOSS)',
                        'skip' => 'Skip database setup',
                    ]
                );

                if ($action === 'skip') {
                    $this->line('Database setup skipped.');

                    return;
                }

                if ($action === 'fresh') {
                    $this->call('migrate:fresh', ['--path' => 'vendor/spatie/laravel-permission/database/migrations']);
                }
            }
        }

        // Publish and run Spatie migrations
        $this->call('vendor:publish', [
            '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
            '--tag' => 'permission-migrations',
        ]);

        // Publish and run our migrations
        $this->call('vendor:publish', [
            '--tag' => 'filament-authz-migrations',
        ]);

        $this->call('migrate');

        $this->line('✓ Database migrations complete');
        $this->newLine();
    }

    protected function setupRoles(): void
    {
        $this->info(SetupStage::Roles->icon().' Role Setup');
        $this->newLine();

        // Create Super Admin
        $superAdmin = Role::findOrCreate($this->state['superAdminRole']);
        $this->line("✓ Created role: {$superAdmin->name}");

        // Create Panel User if enabled
        if ($this->state['panelUserRole'] ?? false) {
            $panelUser = Role::findOrCreate($this->state['panelUserRole']);
            $this->line("✓ Created role: {$panelUser->name}");
        }

        // Ask for additional roles
        if (! $this->option('minimal')) {
            $additionalRoles = text(
                label: 'Additional roles to create (comma-separated, leave empty to skip):',
                hint: 'e.g., Admin, Editor, Viewer',
                default: ''
            );

            if ($additionalRoles !== '') {
                foreach (explode(',', $additionalRoles) as $roleName) {
                    $roleName = mb_trim($roleName);
                    if ($roleName !== '') {
                        $role = Role::findOrCreate($roleName);
                        $this->line("✓ Created role: {$role->name}");
                    }
                }
            }
        }

        $this->newLine();
    }

    protected function setupPermissions(): void
    {
        if ($this->option('skip-permissions')) {
            return;
        }

        $this->info(SetupStage::Permissions->icon().' Permission Discovery & Generation');
        $this->newLine();

        $discovery = app(EntityDiscoveryService::class);

        $resources = $discovery->discoverResources();
        $pages = $discovery->discoverPages();
        $widgets = $discovery->discoverWidgets();

        $this->line("Found {$resources->count()} resources, {$pages->count()} pages, {$widgets->count()} widgets");

        if (! $this->option('minimal') && ! $this->option('force')) {
            $proceed = confirm('Generate permissions for discovered entities?', true);
            if (! $proceed) {
                return;
            }
        }

        $permissionCount = 0;

        // Generate resource permissions
        foreach ($resources as $resource) {
            foreach ($resource->toPermissionKeys() as $permissionKey) {
                Permission::findOrCreate($permissionKey);
                $permissionCount++;
            }
        }

        // Generate page permissions
        foreach ($pages as $page) {
            Permission::findOrCreate($page->getPermissionKey());
            $permissionCount++;
        }

        // Generate widget permissions
        foreach ($widgets as $widget) {
            Permission::findOrCreate($widget->getPermissionKey());
            $permissionCount++;
        }

        $this->line("✓ Generated {$permissionCount} permissions");
        $this->newLine();
    }

    protected function setupPolicies(): void
    {
        if ($this->option('skip-policies')) {
            return;
        }

        $this->info(SetupStage::Policies->icon().' Policy Generation');
        $this->newLine();

        if (! $this->option('minimal')) {
            $policyType = select(
                label: 'Policy type to generate:',
                options: [
                    'composite' => 'Composite (Full features)',
                    'contextual' => 'Contextual (Team/Owner aware)',
                    'hierarchical' => 'Hierarchical (Permission groups)',
                    'basic' => 'Basic (Simple checks)',
                    'skip' => 'Skip policy generation',
                ]
            );

            if ($policyType === 'skip') {
                return;
            }

            $this->state['policyType'] = $policyType;
        }

        $this->call('authz:policies', [
            '--type' => $this->state['policyType'] ?? 'basic',
            '--force' => $this->option('force'),
        ]);
    }

    protected function setupSuperAdmin(): void
    {
        $this->info(SetupStage::UserSetup->icon().' Super Admin Assignment');
        $this->newLine();

        $userModel = $this->state['userModel'];
        if (! class_exists($userModel)) {
            $this->line('User model not found. Super Admin can be assigned later.');

            return;
        }

        $existingUsers = $userModel::count();

        if ($existingUsers === 0) {
            $this->line('No users exist yet. Super Admin can be assigned later.');

            return;
        }

        if ($this->option('minimal')) {
            return;
        }

        $assignNow = confirm('Assign Super Admin role to an existing user?', true);

        if (! $assignNow) {
            $this->line('Run `php artisan authz:super-admin` later to assign.');

            return;
        }

        // Show user selection
        $users = $userModel::limit(10)->get()->mapWithKeys(fn ($user) => [
            $user->id => "{$user->name} ({$user->email})",
        ])->toArray();

        $userId = select(
            label: 'Select user to become Super Admin:',
            options: $users
        );

        $user = $userModel::find($userId);
        if ($user && method_exists($user, 'assignRole')) {
            $user->assignRole($this->state['superAdminRole']);
            $this->line("✓ Assigned {$this->state['superAdminRole']} to {$user->name}");
        }

        $this->newLine();
    }

    protected function verify(): void
    {
        $this->info(SetupStage::Verification->icon().' Verification');
        $this->newLine();

        $checks = [
            'Config published' => file_exists(config_path('filament-authz.php')),
            'Migrations run' => Schema::hasTable('permissions'),
            'Super Admin role exists' => Role::where('name', $this->state['superAdminRole'])->exists(),
            'Permissions generated' => Permission::count() > 0,
        ];

        $allPassed = true;
        foreach ($checks as $check => $passed) {
            $icon = $passed ? '✓' : '✗';
            $color = $passed ? 'green' : 'red';
            $this->line("<fg={$color}>{$icon}</> {$check}");
            if (! $passed) {
                $allPassed = false;
            }
        }

        $this->newLine();

        if (! $allPassed) {
            $this->warn('Some checks failed. Review the output above.');
        }
    }

    protected function showCompletion(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════════╗');
        $this->line('║                                                           ║');
        $this->line('║   ✅ Setup Complete!                                      ║');
        $this->line('║                                                           ║');
        $this->line('╚═══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->info('Next steps:');
        $this->line('  1. Add HasRoles trait to your User model (if not already done)');
        $this->line('  2. Register FilamentAuthzPlugin in your panel providers');
        $this->line('  3. Run `php artisan authz:discover` to view all permissions');
        $this->line('  4. Run `php artisan authz:doctor` to diagnose any issues');
        $this->newLine();

        $this->line('Useful commands:');
        $this->line('  • authz:sync         — Sync permissions from config');
        $this->line('  • authz:policies     — Generate Laravel policies');
        $this->line('  • authz:super-admin  — Assign Super Admin role');
        $this->line('  • authz:export       — Export permissions to JSON');
        $this->newLine();
    }

    protected function displayDetection(string $item, bool $status, string $detail): void
    {
        $icon = $status ? '✓' : '✗';
        $color = $status ? 'green' : 'yellow';
        $this->line("  <fg={$color}>{$icon}</> {$item}: {$detail}");
    }
}
