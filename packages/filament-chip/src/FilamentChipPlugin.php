<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage;
use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use AIArmada\FilamentChip\Widgets\RecentTransactionsWidget;
use AIArmada\FilamentChip\Widgets\RevenueChartWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Filament CHIP Plugin
 *
 * Provides admin panel integration for CHIP payment gateway data.
 * Essential resources, pages, and widgets are registered by default.
 * Optional components (payouts, webhooks) can be enabled via configuration.
 */
final class FilamentChipPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-chip';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages($this->getPages())
            ->resources($this->getResources())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Get essential pages (minimal by default).
     *
     * @return array<class-string>
     */
    private function getPages(): array
    {
        return [
            AnalyticsDashboardPage::class,
        ];
    }

    /**
     * Get essential resources (minimal by default).
     *
     * @return array<class-string>
     */
    private function getResources(): array
    {
        return [
            PurchaseResource::class,
            ClientResource::class,
        ];
    }

    /**
     * Get essential widgets (minimal by default).
     *
     * @return array<class-string>
     */
    private function getWidgets(): array
    {
        return [
            ChipStatsWidget::class,
            RevenueChartWidget::class,
            RecentTransactionsWidget::class,
        ];
    }
}
