<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources;

use AIArmada\FilamentInventory\Resources\InventoryMovementResource\Pages\ListInventoryMovements;
use AIArmada\FilamentInventory\Resources\InventoryMovementResource\Pages\ViewInventoryMovement;
use AIArmada\FilamentInventory\Resources\InventoryMovementResource\Schemas\InventoryMovementInfolist;
use AIArmada\FilamentInventory\Resources\InventoryMovementResource\Tables\InventoryMovementsTable;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static ?string $tenantOwnershipRelationshipName = 'fromLocation.owner';

    protected static ?string $recordTitleAttribute = 'id';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Movements';

    protected static ?string $modelLabel = 'Movement';

    protected static ?string $pluralModelLabel = 'Movements';

    public static function getEloquentQuery(): Builder
    {
        $query = InventoryMovement::query()->with(['fromLocation', 'toLocation']);

        return InventoryOwnerScope::applyToMovementQuery($query);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
            'view' => ViewInventoryMovement::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-inventory.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-inventory.resources.navigation_sort.movements', 30);
    }
}
