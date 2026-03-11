<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Widgets;

use AIArmada\FilamentSignals\Support\SignalsUiConfig;
use AIArmada\Signals\Services\SignalsDashboardService;
use Filament\Widgets\ChartWidget;

final class EventTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Event Trend';

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $trend = app(SignalsDashboardService::class)->trend();

        return [
            'datasets' => [
                [
                    'label' => 'Events',
                    'data' => array_map(static fn (array $row): int => $row['events'], $trend),
                    'borderColor' => 'rgb(14, 116, 144)',
                    'backgroundColor' => 'rgba(14, 116, 144, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => SignalsUiConfig::outcomesLabel(),
                    'data' => array_map(static fn (array $row): int => $row['conversions'], $trend),
                    'borderColor' => 'rgb(22, 163, 74)',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.12)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(static fn (array $row): string => $row['date'], $trend),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
