<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources;

use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Pages\ListVoucherUsages;
use AIArmada\FilamentVouchers\Resources\VoucherUsageResource\Tables\VoucherUsagesTable;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Models\VoucherUsage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class VoucherUsageResource extends Resource
{
    protected static ?string $model = VoucherUsage::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Voucher Usage';

    protected static ?string $recordTitleAttribute = 'user_identifier';

    public static function table(Table $table): Table
    {
        return VoucherUsagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVoucherUsages::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        // The resource's record title is provided by an accessor (`user_identifier`),
        // which is not a database column. Prevent Filament global search from
        // attempting to query that non-existent column by limiting searchable
        // attributes to a real DB column.
        return ['id'];
    }

    /**
     * Voucher usages do not have owner columns; scope via the related voucher.
     *
     * @return Builder<VoucherUsage>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<VoucherUsage> $query */
        $query = parent::getEloquentQuery();

        if (! OwnerScopedQueries::isEnabled()) {
            return $query;
        }

        return $query->whereIn('voucher_id', OwnerScopedQueries::voucherIds());
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-vouchers.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-vouchers.resources.navigation_sort.voucher_usage', 45);
    }
}
