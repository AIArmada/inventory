<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use DateTimeInterface;
use Filament\Widgets\ChartWidget;
use Laravel\Cashier\Subscription;

final class GatewayComparisonWidget extends ChartWidget
{
    protected ?string $heading = null;

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 2;

    public function getHeading(): ?string
    {
        return __('filament-cashier::dashboard.widgets.comparison.label');
    }

    protected function getData(): array
    {
        return once(function (): array {
            $detector = app(GatewayDetector::class);
            $gateways = $detector->availableGateways();

            // Generate last 6 months labels
            $labels = collect(range(5, 0))->map(function ($monthsAgo) {
                return now()->subMonths($monthsAgo)->format('M Y');
            })->toArray();

            $datasets = [];

            foreach ($gateways as $gateway) {
                $config = $detector->getGatewayConfig($gateway);
                $datasets[] = [
                    'label' => $config['label'],
                    'data' => $this->getMonthlyDataForGateway($gateway),
                    'borderColor' => $this->getColorValue($config['color']),
                    'backgroundColor' => $this->getColorValue($config['color'], 0.1),
                    'fill' => true,
                    'tension' => 0.3,
                ];
            }

            return [
                'datasets' => $datasets,
                'labels' => $labels,
            ];
        });
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
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { return '$' + value.toLocaleString(); }",
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }

    /**
     * Get monthly revenue data for a gateway.
     *
     * @return array<int>
     */
    protected function getMonthlyDataForGateway(string $gateway): array
    {
        $detector = app(GatewayDetector::class);
        $data = [];

        // Generate data for last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $startOfMonth = now()->subMonths($i)->startOfMonth();
            $endOfMonth = now()->subMonths($i)->endOfMonth();

            $revenue = $this->getRevenueForPeriod($gateway, $startOfMonth, $endOfMonth);
            $data[] = round($revenue / 100, 2); // Convert cents to dollars
        }

        return $data;
    }

    protected function getRevenueForPeriod(string $gateway, DateTimeInterface $start, DateTimeInterface $end): int
    {
        $detector = app(GatewayDetector::class);

        if ($gateway === 'stripe' && $detector->isAvailable('stripe') && class_exists(Subscription::class)) {
            return CashierOwnerScope::apply(Subscription::query())
                ->whereBetween('created_at', [$start, $end])
                ->where(function ($query) use ($end): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', $end);
                })
                ->count() * 2900; // Approximate average subscription value
        }

        if ($gateway === 'chip' && $detector->isAvailable('chip')) {
            $subscriptionModel = CashierChip::$subscriptionModel;

            return CashierOwnerScope::apply($subscriptionModel::query())
                ->whereBetween('created_at', [$start, $end])
                ->where(function ($query) use ($end): void {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', $end);
                })
                ->count() * 9900; // Approximate average subscription value in MYR cents
        }

        return 0;
    }

    protected function getColorValue(string $color, float $alpha = 1): string
    {
        $rgb = match ($color) {
            'primary' => '99, 102, 241',
            'success', 'emerald' => '16, 185, 129',
            'warning' => '245, 158, 11',
            'danger' => '239, 68, 68',
            'info' => '6, 182, 212',
            'indigo' => '99, 102, 241',
            'gray' => '107, 114, 128',
            default => '99, 102, 241',
        };

        return "rgba({$rgb}, {$alpha})";
    }
}
