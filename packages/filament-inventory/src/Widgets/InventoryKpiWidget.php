<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Widgets;

use AIArmada\Inventory\Reports\InventoryKpiService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class InventoryKpiWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return config('filament-inventory.features.kpi_widget', true);
    }

    protected function getStats(): array
    {
        $kpiService = app(InventoryKpiService::class);
        $kpis = $kpiService->getDashboardKpis();
        $trends = $kpiService->getKpiTrends(3);

        $lastTurnover = $trends->last()['turnover'] ?? 0;
        $previousTurnover = $trends->skip($trends->count() - 2)->first()['turnover'] ?? 0;
        $turnoverTrend = $previousTurnover > 0
            ? round((($lastTurnover - $previousTurnover) / $previousTurnover) * 100, 1)
            : 0;

        $lastFillRate = $trends->last()['fill_rate'] ?? 100;
        $previousFillRate = $trends->skip($trends->count() - 2)->first()['fill_rate'] ?? 100;
        $fillRateTrend = $previousFillRate > 0
            ? round($lastFillRate - $previousFillRate, 1)
            : 0;

        return [
            Stat::make('Inventory Turnover', number_format($kpis['average_turnover_ratio'], 2) . 'x')
                ->description($this->getTrendDescription($turnoverTrend, 'vs last month'))
                ->descriptionIcon($this->getTrendIcon($turnoverTrend))
                ->color($this->getTurnoverColor($kpis['average_turnover_ratio']))
                ->chart($trends->pluck('turnover')->toArray()),

            Stat::make('Days On Hand', number_format($kpis['average_days_on_hand'], 0))
                ->description('Average inventory age')
                ->descriptionIcon('heroicon-o-calendar')
                ->color($this->getDaysOnHandColor($kpis['average_days_on_hand'])),

            Stat::make('Fill Rate', number_format($kpis['overall_fill_rate'], 1) . '%')
                ->description($this->getTrendDescription($fillRateTrend, 'vs last month'))
                ->descriptionIcon($this->getTrendIcon($fillRateTrend))
                ->color($this->getFillRateColor($kpis['overall_fill_rate']))
                ->chart($trends->pluck('fill_rate')->toArray()),

            Stat::make('Inventory Accuracy', number_format($kpis['inventory_accuracy'], 1) . '%')
                ->description('From cycle counts')
                ->descriptionIcon('heroicon-o-clipboard-document-check')
                ->color($this->getAccuracyColor($kpis['inventory_accuracy']))
                ->chart($trends->pluck('accuracy')->toArray()),
        ];
    }

    private function getTrendDescription(float $trend, string $suffix): string
    {
        if ($trend === 0.0) {
            return "No change {$suffix}";
        }

        $direction = $trend > 0 ? '+' : '';

        return "{$direction}{$trend}% {$suffix}";
    }

    private function getTrendIcon(float $trend): string
    {
        return match (true) {
            $trend > 0 => 'heroicon-o-arrow-trending-up',
            $trend < 0 => 'heroicon-o-arrow-trending-down',
            default => 'heroicon-o-minus',
        };
    }

    private function getTurnoverColor(float $ratio): string
    {
        return match (true) {
            $ratio >= 6 => 'success',
            $ratio >= 3 => 'info',
            $ratio >= 1 => 'warning',
            default => 'danger',
        };
    }

    private function getDaysOnHandColor(float $days): string
    {
        return match (true) {
            $days <= 30 => 'success',
            $days <= 60 => 'info',
            $days <= 90 => 'warning',
            default => 'danger',
        };
    }

    private function getFillRateColor(float $rate): string
    {
        return match (true) {
            $rate >= 98 => 'success',
            $rate >= 95 => 'info',
            $rate >= 90 => 'warning',
            default => 'danger',
        };
    }

    private function getAccuracyColor(float $accuracy): string
    {
        return match (true) {
            $accuracy >= 99 => 'success',
            $accuracy >= 97 => 'info',
            $accuracy >= 95 => 'warning',
            default => 'danger',
        };
    }
}
