<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Listeners\PermissionEventSubscriber;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use AIArmada\FilamentAuthz\Services\ComplianceReportService;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\DelegationService;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\ImplicitPermissionService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use AIArmada\FilamentAuthz\Services\PermissionGroupService;
use AIArmada\FilamentAuthz\Services\PermissionImpactAnalyzer;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Services\PermissionTester;
use AIArmada\FilamentAuthz\Services\PermissionVersioningService;
use AIArmada\FilamentAuthz\Services\PolicyEngine;
use AIArmada\FilamentAuthz\Services\PolicyGeneratorService;
use AIArmada\FilamentAuthz\Services\RoleComparer;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use AIArmada\FilamentAuthz\Services\TeamPermissionService;
use AIArmada\FilamentAuthz\Services\TemporalPermissionService;
use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FilamentAuthzServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilamentAuthzPlugin::class);
        $this->mergeConfigFrom(__DIR__.'/../config/filament-authz.php', 'filament-authz');

        $this->registerServices();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-authz');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/filament-authz.php' => config_path('filament-authz.php'),
        ], 'filament-authz-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-authz'),
        ], 'filament-authz-views');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'filament-authz-migrations');

        $this->registerGateBefore();
        $this->registerCommands();
        $this->registerMacros();
        $this->registerEventSubscriber();
    }

    protected function registerServices(): void
    {
        // Core services as singletons
        $this->app->singleton(WildcardPermissionResolver::class);
        $this->app->singleton(ImplicitPermissionService::class);
        $this->app->singleton(PermissionGroupService::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(RoleInheritanceService::class);
        $this->app->singleton(RoleTemplateService::class);
        $this->app->singleton(PolicyEngine::class);
        $this->app->singleton(PermissionCacheService::class);

        // Services with dependencies
        $this->app->singleton(PermissionAggregator::class, function ($app) {
            return new PermissionAggregator(
                $app->make(RoleInheritanceService::class),
                $app->make(WildcardPermissionResolver::class),
                $app->make(ImplicitPermissionService::class)
            );
        });

        $this->app->singleton(ContextualAuthorizationService::class, function ($app) {
            return new ContextualAuthorizationService(
                $app->make(PermissionAggregator::class)
            );
        });

        $this->app->singleton(TeamPermissionService::class, function ($app) {
            return new TeamPermissionService(
                $app->make(ContextualAuthorizationService::class)
            );
        });

        $this->app->singleton(TemporalPermissionService::class, function ($app) {
            return new TemporalPermissionService(
                $app->make(ContextualAuthorizationService::class)
            );
        });

        $this->app->singleton(PermissionTester::class, function ($app) {
            return new PermissionTester(
                $app->make(PermissionAggregator::class),
                $app->make(PolicyEngine::class),
                $app->make(ContextualAuthorizationService::class)
            );
        });

        $this->app->singleton(RoleComparer::class, function ($app) {
            return new RoleComparer(
                $app->make(RoleInheritanceService::class)
            );
        });

        $this->app->singleton(PermissionImpactAnalyzer::class, function ($app) {
            return new PermissionImpactAnalyzer(
                $app->make(RoleInheritanceService::class)
            );
        });

        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(ComplianceReportService::class);

        // Entity Discovery
        $this->app->singleton(EntityDiscoveryService::class);

        // Policy Generator
        $this->app->singleton(PolicyGeneratorService::class);

        // Permission Versioning
        $this->app->singleton(PermissionVersioningService::class, function ($app) {
            return new PermissionVersioningService(
                $app->make(AuditLogger::class)
            );
        });

        // Delegation Service
        $this->app->singleton(DelegationService::class, function ($app) {
            return new DelegationService(
                $app->make(AuditLogger::class)
            );
        });

        // Compliance Report Generator
        $this->app->singleton(Services\ComplianceReportGenerator::class);

        // Identity Provider Sync
        $this->app->singleton(Services\IdentityProviderSync::class);

        // Code Manipulator (not singleton, new instance per file)
        $this->app->bind(Services\CodeManipulator::class, function ($app, $params) {
            return new Services\CodeManipulator($params['path'] ?? '');
        });
    }

    protected function registerGateBefore(): void
    {
        $role = (string) config('filament-authz.super_admin_role');
        if ($role !== '') {
            Gate::before(static function ($user, string $ability) use ($role) {
                return method_exists($user, 'hasRole') && $user->hasRole($role) ? true : null;
            });
        }

        // Register wildcard permission resolution
        if (config('filament-authz.features.wildcard_permissions', true)) {
            Gate::before(function ($user, string $ability) {
                if (! method_exists($user, 'getAllPermissions')) {
                    return null;
                }

                $resolver = app(WildcardPermissionResolver::class);
                $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

                foreach ($userPermissions as $permission) {
                    if ($resolver->isWildcard($permission) && $resolver->matches($permission, $ability)) {
                        return true;
                    }
                }

                return null;
            });
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SyncAuthzCommand::class,
                Console\DoctorAuthzCommand::class,
                Console\ExportAuthzCommand::class,
                Console\ImportAuthzCommand::class,
                Console\GeneratePoliciesCommand::class,
                Console\PermissionGroupsCommand::class,
                Console\RoleHierarchyCommand::class,
                Console\RoleTemplateCommand::class,
                Console\AuthzCacheCommand::class,
                Console\SetupCommand::class,
                Console\DiscoverCommand::class,
                Console\SnapshotCommand::class,
                Console\InstallTraitCommand::class,
            ]);
        }
    }

    protected function registerMacros(): void
    {
        Support\Macros\ActionMacros::register();
        Support\Macros\NavigationItemMacros::register();
        Support\Macros\TableComponentMacros::register();
        Support\Macros\ColumnMacros::register();
        Support\Macros\FilterMacros::register();
        Support\Macros\NavigationMacros::register();
        Support\Macros\FormMacros::register();
    }

    protected function registerEventSubscriber(): void
    {
        if (config('filament-authz.audit.enabled', true)) {
            Event::subscribe(PermissionEventSubscriber::class);
        }
    }
}
