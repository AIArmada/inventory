<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Widgets;

use AIArmada\FilamentCart\Models\Cart;
use Akaunting\Money\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Enhanced cart statistics overview widget.
 *
 * Shows key metrics including conversion funnel data
 * and abandonment statistics.
 */
final class CartStatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = $this->calculateStats();

        return [
            Stat::make('Active Carts', number_format($stats['active_carts']))
                ->description('With items in last 24h')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->chart($this->getActiveCartsChart())
                ->color('primary'),

            Stat::make('Cart Value', $this->formatMoney($stats['total_value']))
                ->description('Potential revenue')
                ->descriptionIcon(Heroicon::OutlinedCurrencyDollar)
                ->chart($this->getValueChart())
                ->color('success'),

            Stat::make('Checkouts Started', number_format($stats['checkouts_started']))
                ->description('Last 24 hours')
                ->descriptionIcon(Heroicon::OutlinedCreditCard)
                ->color('info'),

            Stat::make('Abandoned Carts', number_format($stats['abandoned_carts']))
                ->description($this->getAbandonmentRate($stats) . '% abandonment rate')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($stats['abandoned_carts'] > 0 ? 'warning' : 'success'),

            Stat::make('Recovered', number_format($stats['recovered_carts']))
                ->description($this->getRecoveryRate($stats) . '% recovery rate')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($stats['recovered_carts'] > 0 ? 'success' : 'gray'),

            Stat::make('Recovery Value', $this->formatMoney($stats['recovered_value']))
                ->description('Revenue saved')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 6;
    }

    /**
     * Calculate dashboard statistics.
     *
     * @return array<string, int>
     */
    private function calculateStats(): array
    {
        $yesterday = now()->subDay();

        // Use raw queries for performance
        $base = Cart::query()->forOwner();

        return [
            'active_carts' => (clone $base)
                ->whereNotNull('items')
                ->where('items', '!=', '[]')
                ->where('updated_at', '>=', $yesterday)
                ->count(),

            'total_value' => (int) (clone $base)
                ->whereNotNull('items')
                ->where('items', '!=', '[]')
                ->sum(DB::raw("COALESCE(JSON_EXTRACT(metadata, '$.subtotal'), 0)")),

            'checkouts_started' => (clone $base)
                ->whereNotNull('checkout_started_at')
                ->where('checkout_started_at', '>=', $yesterday)
                ->count(),

            'abandoned_carts' => (clone $base)
                ->whereNotNull('checkout_abandoned_at')
                ->where('checkout_abandoned_at', '>=', $yesterday)
                ->count(),

            'recovered_carts' => (clone $base)
                ->whereNotNull('recovered_at')
                ->where('recovered_at', '>=', $yesterday)
                ->count(),

            'recovered_value' => (int) (clone $base)
                ->whereNotNull('recovered_at')
                ->where('recovered_at', '>=', $yesterday)
                ->sum(DB::raw("COALESCE(JSON_EXTRACT(metadata, '$.subtotal'), 0)")),
        ];
    }

    /**
     * Get abandonment rate as percentage.
     *
     * @param  array<string, int>  $stats
     */
    private function getAbandonmentRate(array $stats): string
    {
        if ($stats['checkouts_started'] === 0) {
            return '0';
        }

        $rate = ($stats['abandoned_carts'] / $stats['checkouts_started']) * 100;

        return number_format($rate, 1);
    }

    /**
     * Get recovery rate as percentage.
     *
     * @param  array<string, int>  $stats
     */
    private function getRecoveryRate(array $stats): string
    {
        if ($stats['abandoned_carts'] === 0) {
            return '0';
        }

        $rate = ($stats['recovered_carts'] / $stats['abandoned_carts']) * 100;

        return number_format($rate, 1);
    }

    /**
     * Get chart data for active carts over time.
     *
     * @return array<int>
     */
    private function getActiveCartsChart(): array
    {
        // Simplified: return last 7 days of active cart counts
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Cart::query()->forOwner()
                ->whereNotNull('items')
                ->where('items', '!=', '[]')
                ->whereDate('updated_at', $date->toDateString())
                ->count();
            $data[] = $count;
        }

        return $data;
    }

    /**
     * Get chart data for cart value over time.
     *
     * @return array<int>
     */
    private function getValueChart(): array
    {
        // Simplified: return last 7 days of cart values
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $value = (int) Cart::query()->forOwner()
                ->whereNotNull('items')
                ->where('items', '!=', '[]')
                ->whereDate('updated_at', $date->toDateString())
                ->sum(DB::raw("COALESCE(JSON_EXTRACT(metadata, '$.subtotal'), 0)"));
            $data[] = $value / 100; // Convert cents to dollars for chart
        }

        return $data;
    }

    private function formatMoney(int $amount): string
    {
        $currency = mb_strtoupper(config('cart.money.default_currency', 'USD'));

        return (string) Money::{$currency}($amount);
    }
}
