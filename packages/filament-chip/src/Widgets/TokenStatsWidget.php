<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Models\Purchase;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class TokenStatsWidget extends BaseWidget
{
    protected static ?int $sort = 30;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = $this->getTokenStats();

        return [
            Stat::make('Active Tokens', (string) $stats['active_tokens'])
                ->description('Recurring payment tokens')
                ->descriptionIcon(Heroicon::Key)
                ->color('success'),

            Stat::make('Token Purchases', (string) $stats['token_purchases'])
                ->description('Payments using tokens')
                ->descriptionIcon(Heroicon::ArrowPath)
                ->color('primary'),

            Stat::make('Token Revenue', $this->formatCurrency($stats['token_revenue']))
                ->description('Revenue from recurring')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * @return array{active_tokens: int, token_purchases: int, token_revenue: int}
     */
    private function getTokenStats(): array
    {
        $activeTokens = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereNotNull('purchase->recurring_token')
            ->where('status', 'paid')
            ->distinct('purchase->recurring_token')
            ->count();

        $tokenPurchases = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereNotNull('purchase->recurring_token')
            ->where('status', 'paid')
            ->count();

        $tokenRevenue = tap(Purchase::query(), function ($query): void {
            if (method_exists($query->getModel(), 'scopeForOwner')) {
                $query->forOwner();
            }
        })
            ->whereNotNull('purchase->recurring_token')
            ->where('status', 'paid')
            ->get()
            ->sum(function (Purchase $purchase): int {
                $total = $purchase->purchase['total'] ?? $purchase->purchase['amount'] ?? 0;

                if (is_array($total)) {
                    return (int) ($total['amount'] ?? 0);
                }

                return (int) $total;
            });

        return [
            'active_tokens' => $activeTokens,
            'token_purchases' => $tokenPurchases,
            'token_revenue' => $tokenRevenue,
        ];
    }

    private function formatCurrency(int $amountInCents): string
    {
        $currency = config('filament-chip.default_currency', 'MYR');
        $amount = $amountInCents / 100;

        return sprintf('%s %s', mb_strtoupper($currency), number_format($amount, 2));
    }
}
