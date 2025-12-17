<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

final class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue Trend (Last 30 Days)';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = '120s';

    protected function getData(): array
    {
        $data = $this->getRevenueData();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_values($data['amounts']),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'fill' => true,
                ],
            ],
            'labels' => array_values($data['labels']),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "' . config('filament-chip.default_currency', 'MYR') . ' " + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{labels: array<string>, amounts: array<int>}
     */
    private function getRevenueData(): array
    {
        $labels = [];
        $amounts = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $labels[] = $date->format('M d');

            $startOfDay = $date->copy()->startOfDay()->getTimestamp();
            $endOfDay = $date->copy()->endOfDay()->getTimestamp();

            $purchases = tap(Purchase::query(), function ($query): void {
                if (method_exists($query->getModel(), 'scopeForOwner')) {
                    $query->forOwner();
                }
            })
                ->where('status', 'paid')
                ->where('is_test', false)
                ->where('created_on', '>=', $startOfDay)
                ->where('created_on', '<=', $endOfDay)
                ->get();

            $dayTotal = $purchases->sum(function (Purchase $purchase): int {
                $total = $purchase->purchase['total'] ?? $purchase->purchase['amount'] ?? 0;

                if (is_array($total)) {
                    return (int) ($total['amount'] ?? 0);
                }

                return (int) $total;
            });

            $amounts[] = (int) ($dayTotal / 100);
        }

        return [
            'labels' => $labels,
            'amounts' => $amounts,
        ];
    }
}
