<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Services\GiftCardService;
use Akaunting\Money\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class GiftCardStatsWidget extends StatsOverviewWidget
{
    public ?GiftCard $record = null;

    protected function getStats(): array
    {
        if ($this->record === null) {
            return $this->getGlobalStats();
        }

        return $this->getRecordStats();
    }

    /**
     * @return array<Stat>
     */
    protected function getRecordStats(): array
    {
        /** @var GiftCard $record */
        $record = $this->record;

        $transactionCount = $record->transactions()->count();
        $totalRedeemed = $record->transactions()
            ->where('amount', '<', 0)
            ->sum('amount');

        return [
            Stat::make('Current Balance', (string) Money::{$record->currency}($record->current_balance))
                ->description('Available balance')
                ->color($record->current_balance > 0 ? 'success' : 'danger'),

            Stat::make('Total Redeemed', (string) Money::{$record->currency}(abs((int) $totalRedeemed)))
                ->description('Amount used')
                ->color('warning'),

            Stat::make('Utilization', number_format($record->balance_utilization, 1) . '%')
                ->description('Of initial balance used'),

            Stat::make('Transactions', (string) $transactionCount)
                ->description('Total transactions'),
        ];
    }

    /**
     * @return array<Stat>
     */
    protected function getGlobalStats(): array
    {
        /** @var GiftCardService $service */
        $service = app(GiftCardService::class);
        $stats = $service->getStatistics(OwnerScopedQueries::owner());

        $currency = config('filament-vouchers.default_currency', 'MYR');

        return [
            Stat::make('Total Gift Cards', (string) $stats['total_cards'])
                ->description($stats['active_cards'] . ' active'),

            Stat::make('Total Issued', (string) Money::{$currency}($stats['total_issued_cents']))
                ->description('Value issued'),

            Stat::make('Total Outstanding', (string) Money::{$currency}($stats['total_outstanding_cents']))
                ->description('Unredeemed balance')
                ->color('success'),

            Stat::make('Redemption Rate', number_format($stats['redemption_rate'], 1) . '%')
                ->description('Of issued value redeemed'),
        ];
    }
}
