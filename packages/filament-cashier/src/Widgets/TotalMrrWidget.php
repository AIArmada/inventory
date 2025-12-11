<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Widgets;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use AIArmada\FilamentCashier\Support\UnifiedSubscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription;

final class TotalMrrWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $subscriptions = $this->getActiveSubscriptions();

        // Calculate MRR by currency
        $mrrByCurrency = $subscriptions
            ->groupBy('currency')
            ->map(fn (Collection $subs) => $subs->sum('amount'));

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
        $trend = $subscriptions->count() > 0 ? '+12%' : '0%';

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
     * @return Collection<int, UnifiedSubscription>
     */
    protected function getActiveSubscriptions(): Collection
    {
        return once(function (): Collection {
            $subscriptions = collect();
            $detector = app(GatewayDetector::class);

            if ($detector->isAvailable('stripe') && class_exists(Subscription::class)) {
                $stripeSubscriptions = Subscription::query()
                    ->with('items')
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    })
                    ->get()
                    ->map(fn ($sub) => UnifiedSubscription::fromStripe($sub));

                $subscriptions = $subscriptions->merge($stripeSubscriptions);
            }

            if ($detector->isAvailable('chip') && class_exists(\AIArmada\CashierChip\Models\Subscription::class)) {
                $chipSubscriptions = \AIArmada\CashierChip\Models\Subscription::query()
                    ->where(function ($query): void {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>', now());
                    })
                    ->get()
                    ->map(fn ($sub) => UnifiedSubscription::fromChip($sub));

                $subscriptions = $subscriptions->merge($chipSubscriptions);
            }

            return $subscriptions->filter(fn (UnifiedSubscription $sub) => $sub->status->isActive());
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
