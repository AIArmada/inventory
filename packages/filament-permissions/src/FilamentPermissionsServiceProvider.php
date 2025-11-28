<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FilamentPermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FilamentPermissionsPlugin::class);
        $this->mergeConfigFrom(__DIR__.'/../config/filament-permissions.php', 'filament-permissions');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-permissions');

        $this->publishes([
            __DIR__.'/../config/filament-permissions.php' => config_path('filament-permissions.php'),
        ], 'filament-permissions-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/filament-permissions'),
        ], 'filament-permissions-views');

        $this->registerGateBefore();
        $this->registerCommands();
        $this->registerMacros();
    }

    protected function registerGateBefore(): void
    {
        $role = (string) config('filament-permissions.super_admin_role');
        if ($role !== '') {
            Gate::before(static function ($user, string $ability) use ($role) {
                return method_exists($user, 'hasRole') && $user->hasRole($role) ? true : null;
            });
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SyncPermissionsCommand::class,
                Console\DoctorPermissionsCommand::class,
                Console\ExportPermissionsCommand::class,
                Console\ImportPermissionsCommand::class,
                Console\GeneratePoliciesCommand::class,
            ]);
        }
    }

    protected function registerMacros(): void
    {
        Support\Macros\ActionMacros::register();
        Support\Macros\NavigationItemMacros::register();
        Support\Macros\TableComponentMacros::register();
    }
}
