<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Services\RecoveryAnalytics;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Strategy comparison chart widget.
 */
class StrategyComparisonWidget extends ChartWidget
{
    protected ?string $heading = 'Strategy Performance Comparison';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $analytics = app(RecoveryAnalytics::class);
        $comparison = $analytics->getStrategyComparison(
            Carbon::now()->subDays(30),
            Carbon::now(),
        );

        $labels = [];
        $openRates = [];
        $clickRates = [];
        $conversionRates = [];

        foreach ($comparison as $strategy) {
            $labels[] = $this->formatStrategyName($strategy['strategy']);
            $openRates[] = $strategy['open_rate'];
            $clickRates[] = $strategy['click_rate'];
            $conversionRates[] = $strategy['conversion_rate'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Open Rate %',
                    'data' => $openRates,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                ],
                [
                    'label' => 'Click Rate %',
                    'data' => $clickRates,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                ],
                [
                    'label' => 'Conversion Rate %',
                    'data' => $conversionRates,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Rate (%)',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
        ];
    }

    private function formatStrategyName(string $strategy): string
    {
        return match ($strategy) {
            'email' => 'Email',
            'sms' => 'SMS',
            'push' => 'Push',
            'multi_channel' => 'Multi-Channel',
            default => ucfirst(str_replace('_', ' ', $strategy)),
        };
    }
}
