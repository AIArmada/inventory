<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart;

use AIArmada\FilamentCart\Pages\AnalyticsPage;
use AIArmada\FilamentCart\Pages\CartDashboard;
use AIArmada\FilamentCart\Pages\LiveDashboardPage;
use AIArmada\FilamentCart\Pages\RecoverySettingsPage;
use AIArmada\FilamentCart\Resources\AlertRuleResource;
use AIArmada\FilamentCart\Resources\CartConditionResource;
use AIArmada\FilamentCart\Resources\CartItemResource;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Resources\ConditionResource;
use AIArmada\FilamentCart\Resources\RecoveryCampaignResource;
use AIArmada\FilamentCart\Resources\RecoveryTemplateResource;
use AIArmada\FilamentCart\Widgets\AnalyticsStatsWidget;
use AIArmada\FilamentCart\Widgets\CampaignPerformanceWidget;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use AIArmada\FilamentCart\Widgets\LiveStatsWidget;
use AIArmada\FilamentCart\Widgets\PendingAlertsWidget;
use AIArmada\FilamentCart\Widgets\RecentActivityWidget;
use AIArmada\FilamentCart\Widgets\RecoveryFunnelWidget;
use AIArmada\FilamentCart\Widgets\StrategyComparisonWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentCartPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-cart';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @return array<class-string>
     */
    private function getResources(): array
    {
        $resources = [
            CartResource::class,
            CartItemResource::class,
            CartConditionResource::class,
            ConditionResource::class,
        ];

        if (config('filament-cart.features.recovery', true)) {
            $resources[] = RecoveryCampaignResource::class;
            $resources[] = RecoveryTemplateResource::class;
        }

        if (config('filament-cart.features.monitoring', true)) {
            $resources[] = AlertRuleResource::class;
        }

        return $resources;
    }

    /**
     * @return array<class-string>
     */
    private function getPages(): array
    {
        $pages = [];

        if (config('filament-cart.features.dashboard', true)) {
            $pages[] = CartDashboard::class;
        }

        if (config('filament-cart.features.analytics', true)) {
            $pages[] = AnalyticsPage::class;
        }

        if (config('filament-cart.features.recovery', true)) {
            $pages[] = RecoverySettingsPage::class;
        }

        if (config('filament-cart.features.monitoring', true)) {
            $pages[] = LiveDashboardPage::class;
        }

        return $pages;
    }

    /**
     * @return array<class-string>
     */
    private function getWidgets(): array
    {
        $widgets = [
            CartStatsWidget::class,
            AnalyticsStatsWidget::class,
        ];

        if (config('filament-cart.features.recovery', true)) {
            $widgets[] = CampaignPerformanceWidget::class;
            $widgets[] = RecoveryFunnelWidget::class;
            $widgets[] = StrategyComparisonWidget::class;
        }

        if (config('filament-cart.features.monitoring', true)) {
            $widgets[] = LiveStatsWidget::class;
            $widgets[] = RecentActivityWidget::class;
            $widgets[] = PendingAlertsWidget::class;
        }

        return $widgets;
    }
}
