<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources;

use AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages\CreateInventorySerial;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages\EditInventorySerial;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages\ListInventorySerials;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages\ViewInventorySerial;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Schemas\InventorySerialForm;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Schemas\InventorySerialInfolist;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Tables\InventorySerialsTable;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventorySerial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use UnitEnum;

final class InventorySerialResource extends Resource
{
    protected static ?string $model = InventorySerial::class;

    protected static ?string $tenantOwnershipRelationshipName = 'location.owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $recordTitleAttribute = 'serial_number';

    protected static ?string $navigationLabel = 'Serial Numbers';

    protected static ?string $modelLabel = 'Serial';

    protected static ?string $pluralModelLabel = 'Serial Numbers';

    public static function getEloquentQuery(): Builder
    {
        $query = InventorySerial::query()->with([
            'location',
            'batch' => static function (Relation $relation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($relation->getQuery(), 'location');
            },
        ]);

        return InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
    }

    public static function form(Schema $schema): Schema
    {
        return InventorySerialForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventorySerialInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventorySerialsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventorySerials::route('/'),
            'create' => CreateInventorySerial::route('/create'),
            'view' => ViewInventorySerial::route('/{record}'),
            'edit' => EditInventorySerial::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->where('status', SerialStatus::Available->value)->count();

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
        return config('filament-inventory.resources.navigation_sort.serials', 60);
    }
}
