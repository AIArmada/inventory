<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use AIArmada\FilamentChip\Pages\AnalyticsDashboardPage;
use AIArmada\FilamentChip\Pages\WebhookMonitorPage;
use AIArmada\FilamentChip\Resources\ClientResource;
use AIArmada\FilamentChip\Resources\PaymentResource;
use AIArmada\FilamentChip\Resources\PurchaseResource;
use AIArmada\FilamentChip\Resources\RecurringScheduleResource;
use AIArmada\FilamentChip\Widgets\ChipStatsWidget;
use AIArmada\FilamentChip\Widgets\PaymentMethodsWidget;
use AIArmada\FilamentChip\Widgets\RecentTransactionsWidget;
use AIArmada\FilamentChip\Widgets\RecurringStatsWidget;
use AIArmada\FilamentChip\Widgets\RevenueChartWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

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
            ->pages([
                AnalyticsDashboardPage::class,
                WebhookMonitorPage::class,
            ])
            ->resources([
                PurchaseResource::class,
                PaymentResource::class,
                ClientResource::class,
                RecurringScheduleResource::class,
            ])
            ->widgets([
                ChipStatsWidget::class,
                RevenueChartWidget::class,
                PaymentMethodsWidget::class,
                RecurringStatsWidget::class,
                RecentTransactionsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
