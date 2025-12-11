<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Filament\Widgets\ChartWidget;
use Laravel\Cashier\Subscription;

final class GatewayBreakdownWidget extends ChartWidget
{
    protected ?string $heading = null;

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return __('filament-cashier::dashboard.widgets.gateway_breakdown.label');
    }

    protected function getData(): array
    {
        $detector = app(GatewayDetector::class);
        $revenueByGateway = $this->getRevenueByGateway();

        $labels = [];
        $data = [];
        $backgroundColor = [];

        foreach ($revenueByGateway as $gateway => $amount) {
            $config = $detector->getGatewayConfig($gateway);
            $labels[] = $config['label'];
            $data[] = round($amount / 100, 2); // Convert cents to dollars
            $backgroundColor[] = $this->getColorValue($config['color']);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data,
                    'backgroundColor' => $backgroundColor,
                    'borderWidth' => 0,
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
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => true,
        ];
    }

    /**
     * Get revenue by gateway.
     *
     * Uses once() to cache the result for the current request, avoiding
     * redundant database queries during the widget render cycle.
     *
     * @return array<string, int>
     */
    protected function getRevenueByGateway(): array
    {
        return once(function (): array {
            $detector = app(GatewayDetector::class);
            $revenue = [];

            if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
                $stripeRevenue = Subscription::query()
                    ->with('items')
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    })
                    ->get()
                    ->map(fn ($sub) => UnifiedSubscription::fromStripe($sub))
                    ->filter(fn (UnifiedSubscription $sub) => $sub->status->isActive())
                    ->sum('amount');

                if ($stripeRevenue > 0) {
                    $revenue['stripe'] = $stripeRevenue;
                }
            }

            if ($detector->isAvailable('chip') && class_exists(\AIArmada\CashierChip\Models\Subscription::class)) {
                $chipRevenue = \AIArmada\CashierChip\Models\Subscription::query()
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    })
                    ->get()
                    ->map(fn ($sub) => UnifiedSubscription::fromChip($sub))
                    ->filter(fn (UnifiedSubscription $sub) => $sub->status->isActive())
                    ->sum('amount');

                if ($chipRevenue > 0) {
                    $revenue['chip'] = $chipRevenue;
                }
            }

            // Ensure we have at least empty data
            if (empty($revenue)) {
                foreach ($detector->availableGateways() as $gateway) {
                    $revenue[$gateway] = 0;
                }
            }

            return $revenue;
        });
    }

    protected function getColorValue(string $color): string
    {
        return match ($color) {
            'primary' => 'rgb(99, 102, 241)',
            'success', 'emerald' => 'rgb(16, 185, 129)',
            'warning' => 'rgb(245, 158, 11)',
            'danger' => 'rgb(239, 68, 68)',
            'info' => 'rgb(6, 182, 212)',
            'indigo' => 'rgb(99, 102, 241)',
            'gray' => 'rgb(107, 114, 128)',
            default => 'rgb(99, 102, 241)',
        };
    }
}
