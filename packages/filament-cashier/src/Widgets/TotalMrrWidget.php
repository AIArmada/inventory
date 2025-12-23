<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\CashierChip\Cashier as CashierChip;
use AIArmada\FilamentCashier\Support\CashierOwnerScope;
use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class TotalMrrWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $summary = $this->getActiveSubscriptionsSummary();

        // Calculate MRR by currency
        $mrrByCurrency = collect($summary['mrrByCurrency']);

        // Primary MRR (use base currency from config or largest)
        $baseCurrency = config('filament-cashier.currency.base', 'USD');
        $primaryMrr = $mrrByCurrency->get($baseCurrency, 0);

        // Convert other currencies if enabled
        if (config('filament-cashier.currency.display_converted', false)) {
            $rates = config('filament-cashier.currency.conversion_rates', []);
            foreach ($mrrByCurrency as $currency => $amount) {
                if ($currency !== $baseCurrency && isset($rates[$currency])) {
                    $primaryMrr += (int) ($amount / $rates[$currency]);
                }
            }
        }

        $formattedMrr = $this->formatCurrency($primaryMrr, $baseCurrency);

        // Calculate trend (mock for now - would need historical data)
        $trend = $summary['count'] > 0 ? '+12%' : '0%';

        return [
            Stat::make(__('filament-cashier::dashboard.widgets.total_mrr.label'), $formattedMrr)
                ->description(__('filament-cashier::dashboard.widgets.total_mrr.description'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 3, 4, 5, 6, 3, 5, 8])
                ->color('success'),
        ];
    }

    /**
     * Get active subscriptions across all gateways.
     *
     * Uses once() to cache the result for the current request, avoiding
     * redundant database queries during the widget render cycle.
     *
     * @return array{count: int, mrrByCurrency: array<string, int>}
     */
    protected function getActiveSubscriptionsSummary(): array
    {
        return once(function (): array {
            $count = 0;
            $mrrByCurrency = [];
            $detector = app(GatewayDetector::class);

            if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
                $stripeQuery = CashierOwnerScope::apply(Subscription::query())
                    ->with('items')
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    });

                $stripeQuery->chunk(200, function (Collection $chunk) use (&$count, &$mrrByCurrency): void {
                    foreach ($chunk as $model) {
                        if (! $model instanceof Model) {
                            continue;
                        }

                        $unified = UnifiedSubscription::fromStripe($model);

                        if (! $unified->status->isActive()) {
                            continue;
                        }

                        $mrrByCurrency[$unified->currency] = ($mrrByCurrency[$unified->currency] ?? 0) + $unified->amount;
                        $count++;
                    }
                });
            }

            if ($detector->isAvailable('chip')) {
                $subscriptionModel = CashierChip::$subscriptionModel;
                $chipQuery = CashierOwnerScope::apply($subscriptionModel::query())
                    ->with('items')
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    });

                $chipQuery->chunk(200, function (Collection $chunk) use (&$count, &$mrrByCurrency): void {
                    foreach ($chunk as $model) {
                        if (! $model instanceof Model) {
                            continue;
                        }

                        $unified = UnifiedSubscription::fromChip($model);

                        if (! $unified->status->isActive()) {
                            continue;
                        }

                        $mrrByCurrency[$unified->currency] = ($mrrByCurrency[$unified->currency] ?? 0) + $unified->amount;
                        $count++;
                    }
                });
            }

            return [
                'count' => $count,
                'mrrByCurrency' => $mrrByCurrency,
            ];
        });
    }

    protected function formatCurrency(int $amountInCents, string $currency): string
    {
        $symbol = match ($currency) {
            'MYR' => 'RM',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency . ' ',
        };

        return $symbol . number_format($amountInCents / 100, 2);
    }
}
