<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources;

use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Pages\ListFraudSignals;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Pages\ViewFraudSignal;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Schemas\FraudSignalInfolist;
use AIArmada\FilamentVouchers\Resources\FraudSignalResource\Tables\FraudSignalsTable;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Fraud\Enums\FraudRiskLevel;
use AIArmada\Vouchers\Fraud\Models\VoucherFraudSignal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class FraudSignalResource extends Resource
{
    protected static ?string $model = VoucherFraudSignal::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $recordTitleAttribute = 'message';

    protected static ?string $navigationLabel = 'Fraud Signals';

    protected static ?string $modelLabel = 'Fraud Signal';

    protected static ?string $pluralModelLabel = 'Fraud Signals';

    public static function infolist(Schema $schema): Schema
    {
        return FraudSignalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FraudSignalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFraudSignals::route('/'),
            'view' => ViewFraudSignal::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) self::getEloquentQuery()->where('reviewed', false)
            ->whereIn('risk_level', [FraudRiskLevel::High->value, FraudRiskLevel::Critical->value])
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Fraud signals do not have owner columns; scope via the related voucher.
     *
     * @return Builder<VoucherFraudSignal>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<VoucherFraudSignal> $query */
        $query = parent::getEloquentQuery();

        if (! OwnerScopedQueries::isEnabled()) {
            return $query;
        }

        return $query->where(function (Builder $builder): void {
            $builder->whereIn('voucher_id', OwnerScopedQueries::voucherIds())
                ->orWhereIn('voucher_code', OwnerScopedQueries::voucherCodes());
        });
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-vouchers.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-vouchers.resources.navigation_sort.fraud_signals', 60);
    }
}
