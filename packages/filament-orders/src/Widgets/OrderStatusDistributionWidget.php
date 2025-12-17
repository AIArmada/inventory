<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders\Widgets;

use AIArmada\Orders\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrderStatusDistributionWidget extends ChartWidget
{
    protected ?string $heading = 'Order Status Distribution';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $statuses = [
            'pending_payment' => ['label' => 'Pending Payment', 'color' => '#eab308'],
            'processing' => ['label' => 'Processing', 'color' => '#3b82f6'],
            'on_hold' => ['label' => 'On Hold', 'color' => '#6b7280'],
            'shipped' => ['label' => 'Shipped', 'color' => '#8b5cf6'],
            'delivered' => ['label' => 'Delivered', 'color' => '#22c55e'],
            'completed' => ['label' => 'Completed', 'color' => '#10b981'],
            'canceled' => ['label' => 'Canceled', 'color' => '#9ca3af'],
            'returned' => ['label' => 'Returned', 'color' => '#f97316'],
            'refunded' => ['label' => 'Refunded', 'color' => '#64748b'],
        ];

        /** @var array<string, int> $countsByStatus */
        $countsByStatus = Order::query()
            ->forOwner()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $counts = [];
        $labels = [];
        $colors = [];

        foreach ($statuses as $status => $config) {
            $count = $countsByStatus[$status] ?? 0;
            if ($count <= 0) {
                continue;
            }

            $counts[] = $count;
            $labels[] = $config['label'];
            $colors[] = $config['color'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $counts,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
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
                    'position' => 'right',
                ],
            ],
        ];
    }
}
