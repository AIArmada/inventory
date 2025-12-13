<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Services\CartMonitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Real-time live statistics widget.
 */
class LiveStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '10s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $monitor = app(CartMonitor::class);
        $stats = $monitor->getLiveStats();

        return [
            Stat::make('Active Carts', (string) $stats->active_carts)
                ->description("{$stats->carts_with_items} with items")
                ->icon('heroicon-o-shopping-cart')
                ->color('primary'),

            Stat::make('Checkouts', (string) $stats->checkouts_in_progress)
                ->description('In progress')
                ->icon('heroicon-o-credit-card')
                ->color('success'),

            Stat::make('Recent Abandonments', (string) $stats->recent_abandonments)
                ->description('Last 30 minutes')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stats->recent_abandonments > 0 ? 'warning' : 'gray'),

            Stat::make('Total Value', $stats->getFormattedTotalValue())
                ->description("{$stats->high_value_carts} high-value")
                ->icon('heroicon-o-currency-dollar')
                ->color('info'),

            Stat::make('Pending Alerts', (string) $stats->pending_alerts)
                ->description('Unread')
                ->icon('heroicon-o-bell')
                ->color($stats->pending_alerts > 0 ? 'danger' : 'gray'),

            Stat::make('Fraud Signals', (string) $stats->fraud_signals)
                ->description('Last 24 hours')
                ->icon('heroicon-o-shield-exclamation')
                ->color($stats->fraud_signals > 0 ? 'danger' : 'success'),
        ];
    }
}
