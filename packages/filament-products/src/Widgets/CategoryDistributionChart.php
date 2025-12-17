<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Widgets;

use AIArmada\Products\Models\Category;
use Filament\Widgets\ChartWidget;

class CategoryDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Products by Category';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $categories = Category::query()
            ->forOwner()
            ->withCount('products')
            ->having('products_count', '>', 0)
            ->orderByDesc('products_count')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Products',
                    'data' => $categories->pluck('products_count')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6',
                        '#8b5cf6',
                        '#ec4899',
                        '#f59e0b',
                        '#10b981',
                        '#06b6d4',
                        '#6366f1',
                        '#f97316',
                        '#14b8a6',
                        '#a855f7',
                    ],
                ],
            ],
            'labels' => $categories->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
