<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources;

use AIArmada\FilamentInventory\Resources\InventoryLevelResource\Pages\EditInventoryLevel;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource\Pages\ListInventoryLevels;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource\Pages\ViewInventoryLevel;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource\Schemas\InventoryLevelForm;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource\Schemas\InventoryLevelInfolist;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource\Tables\InventoryLevelsTable;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class InventoryLevelResource extends Resource
{
    protected static ?string $model = InventoryLevel::class;

    protected static ?string $tenantOwnershipRelationshipName = 'location.owner';

    protected static ?string $recordTitleAttribute = 'id';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $navigationLabel = 'Stock Levels';

    protected static ?string $modelLabel = 'Stock Level';

    protected static ?string $pluralModelLabel = 'Stock Levels';

    public static function getEloquentQuery(): Builder
    {
        $query = InventoryLevel::query()->with('location');

        return InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
    }

    public static function form(Schema $schema): Schema
    {
        return InventoryLevelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryLevelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryLevelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryLevels::route('/'),
            'view' => ViewInventoryLevel::route('/{record}'),
            'edit' => EditInventoryLevel::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $lowStockCount = self::getEloquentQuery()
            ->whereRaw('quantity_on_hand - quantity_reserved <= reorder_point')
            ->where('reorder_point', '>', 0)
            ->count();

        return $lowStockCount > 0 ? (string) $lowStockCount : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-inventory.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-inventory.resources.navigation_sort.levels', 20);
    }
}
