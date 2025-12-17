<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources;

use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\CreateGiftCard;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\EditGiftCard;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\ListGiftCards;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Pages\ViewGiftCard;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\RelationManagers\TransactionsRelationManager;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Schemas\GiftCardForm;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Schemas\GiftCardInfolist;
use AIArmada\FilamentVouchers\Resources\GiftCardResource\Tables\GiftCardsTable;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class GiftCardResource extends Resource
{
    protected static ?string $model = GiftCard::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $navigationLabel = 'Gift Cards';

    protected static ?string $modelLabel = 'Gift Card';

    protected static ?string $pluralModelLabel = 'Gift Cards';

    public static function form(Schema $schema): Schema
    {
        return GiftCardForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GiftCardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GiftCardsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGiftCards::route('/'),
            'create' => CreateGiftCard::route('/create'),
            'view' => ViewGiftCard::route('/{record}'),
            'edit' => EditGiftCard::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) self::getEloquentQuery()
            ->where('status', GiftCardStatus::Active->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Builder<GiftCard>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<GiftCard> $query */
        $query = parent::getEloquentQuery();

        /** @var Builder<GiftCard> $scoped */
        $scoped = OwnerScopedQueries::scopeVoucherLike($query);

        return $scoped;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-vouchers.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-vouchers.resources.navigation_sort.gift_cards', 50);
    }
}
