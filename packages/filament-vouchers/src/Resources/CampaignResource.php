<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Resources;

use AIArmada\FilamentVouchers\Resources\CampaignResource\Pages\CreateCampaign;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Pages\EditCampaign;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Pages\ListCampaigns;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Pages\ViewCampaign;
use AIArmada\FilamentVouchers\Resources\CampaignResource\RelationManagers\VariantsRelationManager;
use AIArmada\FilamentVouchers\Resources\CampaignResource\RelationManagers\VouchersRelationManager;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Schemas\CampaignForm;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Schemas\CampaignInfolist;
use AIArmada\FilamentVouchers\Resources\CampaignResource\Tables\CampaignsTable;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Campaigns\Enums\CampaignStatus;
use AIArmada\Vouchers\Campaigns\Models\Campaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?string $modelLabel = 'Campaign';

    protected static ?string $pluralModelLabel = 'Campaigns';

    public static function form(Schema $schema): Schema
    {
        return CampaignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
            VouchersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'view' => ViewCampaign::route('/{record}'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) self::getEloquentQuery()
            ->where('status', CampaignStatus::Active->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Builder<Campaign>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Campaign> $query */
        $query = parent::getEloquentQuery();

        /** @var Builder<Campaign> $scoped */
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
        return config('filament-vouchers.resources.navigation_sort.campaigns', 5);
    }
}
