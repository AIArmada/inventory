<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Models\RecoveryCampaign;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Campaign performance overview widget.
 */
class CampaignPerformanceWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeCampaigns = RecoveryCampaign::query()
            ->where('status', 'active')
            ->count();

        $totals = RecoveryCampaign::query()
            ->selectRaw('
                SUM(total_sent) as total_sent,
                SUM(total_opened) as total_opened,
                SUM(total_clicked) as total_clicked,
                SUM(total_recovered) as total_recovered,
                SUM(recovered_revenue_cents) as total_revenue
            ')
            ->first();

        $openRate = ($totals?->total_sent ?? 0) > 0
            ? ($totals->total_opened / $totals->total_sent) * 100
            : 0;

        $clickRate = ($totals?->total_sent ?? 0) > 0
            ? ($totals->total_clicked / $totals->total_sent) * 100
            : 0;

        $conversionRate = ($totals?->total_sent ?? 0) > 0
            ? ($totals->total_recovered / $totals->total_sent) * 100
            : 0;

        return [
            Stat::make('Active Campaigns', (string) $activeCampaigns)
                ->description('Currently running')
                ->icon('heroicon-o-rocket-launch')
                ->color('primary'),

            Stat::make('Messages Sent', number_format($totals?->total_sent ?? 0))
                ->description('All time')
                ->icon('heroicon-o-envelope')
                ->color('info'),

            Stat::make('Open Rate', number_format($openRate, 1) . '%')
                ->description('Of sent messages')
                ->icon('heroicon-o-eye')
                ->color($this->getRateColor($openRate, [30, 20])),

            Stat::make('Click Rate', number_format($clickRate, 1) . '%')
                ->description('Of sent messages')
                ->icon('heroicon-o-cursor-arrow-rays')
                ->color($this->getRateColor($clickRate, [10, 5])),

            Stat::make('Carts Recovered', number_format($totals?->total_recovered ?? 0))
                ->description(number_format($conversionRate, 1) . '% conversion')
                ->icon('heroicon-o-arrow-path')
                ->color('success'),

            Stat::make('Revenue Recovered', '$' . number_format(($totals?->total_revenue ?? 0) / 100, 2))
                ->description('All time')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning'),
        ];
    }

    /**
     * @param  array{0: float, 1: float}  $thresholds  [good, warning]
     */
    private function getRateColor(float $rate, array $thresholds): string
    {
        if ($rate >= $thresholds[0]) {
            return 'success';
        }

        if ($rate >= $thresholds[1]) {
            return 'warning';
        }

        return 'danger';
    }
}
