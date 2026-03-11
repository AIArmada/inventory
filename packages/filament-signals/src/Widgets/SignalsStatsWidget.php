<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Widgets;

use AIArmada\FilamentSignals\Support\SignalsUiConfig;
use AIArmada\Signals\Services\SignalsDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class SignalsStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $summary = app(SignalsDashboardService::class)->summary();
        $outcomesLabel = SignalsUiConfig::outcomesLabel();
        $monetaryValueLabel = SignalsUiConfig::monetaryValueLabel();

        return [
            Stat::make('Tracked Properties', number_format($summary['tracked_properties']))
                ->description('Sites and apps under analysis')
                ->color('primary'),
            Stat::make('Active Alert Rules', number_format($summary['active_alert_rules']))
                ->description('Threshold monitors currently enabled')
                ->color('danger'),
            Stat::make('Unread Alerts', number_format($summary['unread_alerts']))
                ->description('Signals needing operator attention')
                ->color('danger'),
            Stat::make('Identities', number_format($summary['identities']))
                ->description('Known or anonymous people')
                ->color('success'),
            Stat::make('Sessions', number_format($summary['sessions']))
                ->description('Sessions in the active range')
                ->color('warning'),
            Stat::make('Events', number_format($summary['events']))
                ->description('Captured interactions')
                ->color('info'),
            Stat::make($outcomesLabel, number_format($summary['conversions']))
                ->description('Events matching the primary outcome')
                ->color('success'),
            Stat::make($monetaryValueLabel, $this->formatMoney($summary['revenue_minor']))
                ->description('Tracked monetary value in range')
                ->color('warning'),
        ];
    }

    private function formatMoney(int $minor): string
    {
        return config('signals.defaults.currency', 'MYR') . ' ' . number_format($minor / 100, 2);
    }
}
