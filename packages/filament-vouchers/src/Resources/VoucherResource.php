<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources;

use AIArmada\FilamentVouchers\Models\Voucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\CreateVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\EditVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\ListVouchers;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Pages\ViewVoucher;
use AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers\VoucherUsagesRelationManager;
use AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers\WalletEntriesRelationManager;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherForm;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherInfolist;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Tables\VouchersTable;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

final class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $navigationLabel = 'Vouchers';

    protected static ?string $modelLabel = 'Voucher';

    protected static ?string $pluralModelLabel = 'Vouchers';

    public static function form(Schema $schema): Schema
    {
        return VoucherForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VoucherInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VouchersTable::configure($table);
    }

    public static function getRelations(): array
    {
        $relations = [
            VoucherUsagesRelationManager::class,
            WalletEntriesRelationManager::class,
        ];

        // Add carts relation manager if filament-cart is available
        // Note: This shows carts in the conditions/metadata, not a direct database relationship
        // if (class_exists(\AIArmada\FilamentCart\Models\Cart::class)) {
        //     $relations[] = \AIArmada\FilamentVouchers\Resources\VoucherResource\RelationManagers\CartsRelationManager::class;
        // }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVouchers::route('/'),
            'create' => CreateVoucher::route('/create'),
            'view' => ViewVoucher::route('/{record}'),
            'edit' => EditVoucher::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) self::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Builder<Voucher>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Voucher> $query */
        $query = parent::getEloquentQuery();

        /** @var Builder<Voucher> $scoped */
        $scoped = OwnerScopedQueries::scopeVoucherLike($query);

        return $scoped;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'primary';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-vouchers.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-vouchers.resources.navigation_sort.vouchers', 40);
    }
}
