<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources;

use AIArmada\FilamentVouchers\Resources\VoucherWalletResource\Pages\ListVoucherWallets;
use AIArmada\FilamentVouchers\Resources\VoucherWalletResource\Tables\VoucherWalletsTable;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Models\VoucherWallet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class VoucherWalletResource extends Resource
{
    protected static ?string $model = VoucherWallet::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedWallet;

    protected static ?string $navigationLabel = 'Voucher Wallets';

    protected static ?string $modelLabel = 'Wallet Entry';

    protected static ?string $pluralModelLabel = 'Wallet Entries';

    public static function table(Table $table): Table
    {
        return VoucherWalletsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVoucherWallets::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) self::getEloquentQuery()->where('is_claimed', true)
            ->where('is_redeemed', false)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Builder<VoucherWallet>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<VoucherWallet> $query */
        $query = parent::getEloquentQuery();

        if (! OwnerScopedQueries::isEnabled()) {
            return $query;
        }

        return $query->whereIn('voucher_id', OwnerScopedQueries::voucherIds());
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'info';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-vouchers.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-vouchers.resources.navigation_sort.voucher_wallets', 30);
    }
}
