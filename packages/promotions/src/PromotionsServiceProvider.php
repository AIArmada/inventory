<?php

declare(strict_types=1);

namespace AIArmada\Promotions;

use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Services\PromotionService;
use Illuminate\Support\ServiceProvider;

class PromotionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/promotions.php', 'promotions');

        $this->app->singleton(PromotionServiceInterface::class, PromotionService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
        }
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/promotions.php' => config_path('promotions.php'),
        ], 'promotions-config');
    }

    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'promotions-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
