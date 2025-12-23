<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Resources;

use AIArmada\FilamentTax\Resources\TaxExemptionResource\Pages;
use AIArmada\FilamentTax\Resources\TaxExemptionResource\Schemas\TaxExemptionForm;
use AIArmada\FilamentTax\Resources\TaxExemptionResource\Tables\TaxExemptionsTable;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Support\TaxOwnerScope;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class TaxExemptionResource extends Resource
{
    protected static ?string $model = TaxExemption::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string | UnitEnum | null $navigationGroup = 'Tax';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'certificate_number';

    /**
     * @return Builder<TaxExemption>
     */
    public static function getEloquentQuery(): Builder
    {
        return TaxOwnerScope::applyToOwnedQuery(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return TaxExemptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxExemptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxExemptions::route('/'),
            'create' => Pages\CreateTaxExemption::route('/create'),
            'view' => Pages\ViewTaxExemption::route('/{record}'),
            'edit' => Pages\EditTaxExemption::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $now = CarbonImmutable::now();

        $expiring = TaxOwnerScope::applyToOwnedQuery(self::getModel()::query())
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', $now)
            ->where('expires_at', '<=', $now->addDays(30))
            ->count();

        return $expiring > 0 ? (string) $expiring : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
