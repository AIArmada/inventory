<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources;

use AIArmada\FilamentInventory\Resources\InventoryLocationResource\Pages\CreateInventoryLocation;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource\Pages\EditInventoryLocation;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource\Pages\ListInventoryLocations;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource\Pages\ViewInventoryLocation;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource\Schemas\InventoryLocationForm;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource\Schemas\InventoryLocationInfolist;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource\Tables\InventoryLocationsTable;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class InventoryLocationResource extends Resource
{
    protected static ?string $model = InventoryLocation::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $modelLabel = 'Location';

    protected static ?string $pluralModelLabel = 'Locations';

    /**
     * @return Builder<InventoryLocation>
     */
    public static function getEloquentQuery(): Builder
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());
    }

    public static function form(Schema $schema): Schema
    {
        return InventoryLocationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryLocationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryLocationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryLocations::route('/'),
            'create' => CreateInventoryLocation::route('/create'),
            'view' => ViewInventoryLocation::route('/{record}'),
            'edit' => EditInventoryLocation::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-inventory.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-inventory.resources.navigation_sort.locations', 10);
    }
}
