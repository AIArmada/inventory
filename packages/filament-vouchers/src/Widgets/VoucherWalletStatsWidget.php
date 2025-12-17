<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Widgets;

use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Models\VoucherWallet;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

final class VoucherWalletStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $wallets = $this->wallets();

        $total = (clone $wallets)->count();
        $claimed = (clone $wallets)->where('is_claimed', true)->count();
        $redeemed = (clone $wallets)->where('is_redeemed', true)->count();
        $available = (clone $wallets)->where('is_redeemed', false)->count();

        // Calculate unique vouchers in wallets
        $uniqueVouchers = (clone $wallets)->distinct('voucher_id')->count('voucher_id');

        // Calculate unique owners (users/stores/teams) who have vouchers in their wallets
        /** @var Connection $connection */
        $connection = VoucherWallet::query()->getConnection();
        $driver = $connection->getDriverName();
        $concat = $driver === 'pgsql'
            ? "owner_type || '-' || owner_id"
            : "CONCAT(owner_type, '-', owner_id)";
        $uniqueOwners = (clone $wallets)->selectRaw("COUNT(DISTINCT {$concat}) as count")
            ->value('count') ?? 0;

        return [
            Stat::make('Total Wallet Entries', $total)
                ->description('Vouchers saved to wallets')
                ->descriptionIcon(Heroicon::Ticket)
                ->color('primary')
                ->chart($this->getWalletTrend()),

            Stat::make('Unique Vouchers', $uniqueVouchers)
                ->description('Different vouchers in wallets')
                ->descriptionIcon(Heroicon::Sparkles)
                ->color('info'),

            Stat::make('Unique Owners', $uniqueOwners)
                ->description('Users with saved vouchers')
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('success'),

            Stat::make('Available', $available)
                ->description('Ready to be used')
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color('success'),

            Stat::make('Claimed', $claimed)
                ->description('Claimed by owners')
                ->descriptionIcon(Heroicon::ShieldCheck)
                ->color('warning'),

            Stat::make('Redeemed', $redeemed)
                ->description('Already used')
                ->descriptionIcon(Heroicon::CheckBadge)
                ->color('danger'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }

    /**
     * Get wallet entries trend for the last 7 days.
     *
     * @return array<int, int>
     */
    private function getWalletTrend(): array
    {
        $data = [];
        $now = now();

        $wallets = $this->wallets();

        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->startOfDay();
            $count = (clone $wallets)->whereDate('created_at', $date)->count();
            $data[] = $count;
        }

        return $data;
    }

    /**
     * @return Builder<VoucherWallet>
     */
    private function wallets(): Builder
    {
        /** @var Builder<VoucherWallet> $query */
        $query = VoucherWallet::query();

        /** @var Builder<VoucherWallet> $scoped */
        $scoped = OwnerScopedQueries::scopeOwnerColumns(
            $query,
            OwnerScopedQueries::owner(),
            OwnerScopedQueries::includeGlobal(),
        );

        return $scoped;
    }
}
