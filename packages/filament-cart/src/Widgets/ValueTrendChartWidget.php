<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Pages\AnalyticsPage;
use AIArmada\FilamentCart\Services\CartAnalyticsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Cart value trend chart widget.
 */
class ValueTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Cart Value Trends';

    protected ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    #[On('date-range-updated')]
    public function refresh(): void
    {
        // Widget will refresh on event
    }

    protected function getData(): array
    {
        $page = $this->getPageInstance();
        $from = $page?->getDateFrom() ?? Carbon::now()->subDays(30);
        $to = $page?->getDateTo() ?? Carbon::now();
        $interval = $page?->getInterval() ?? 'day';

        $service = app(CartAnalyticsService::class);
        $trends = $service->getValueTrends($from, $to, $interval);

        $labels = [];
        $values = [];
        $counts = [];

        foreach ($trends as $point) {
            $labels[] = $this->formatLabel($point['date'], $interval);
            $values[] = round($point['value'] / 100, 2);
            $counts[] = $point['count'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Value ($)',
                    'data' => $values,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Cart Count',
                    'data' => $counts,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Value ($)',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Count',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }

    private function getPageInstance(): ?AnalyticsPage
    {
        $livewire = $this->getLivewire();

        if ($livewire instanceof AnalyticsPage) {
            return $livewire;
        }

        return null;
    }

    private function formatLabel(string $date, string $interval): string
    {
        return match ($interval) {
            'week' => 'Week ' . mb_substr($date, 4),
            'month' => Carbon::parse($date . '-01')->format('M Y'),
            default => Carbon::parse($date)->format('M j'),
        };
    }
}
