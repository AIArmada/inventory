<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources;

use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages\CreateInventoryBatch;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages\EditInventoryBatch;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages\ListInventoryBatches;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages\ViewInventoryBatch;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Schemas\InventoryBatchForm;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Schemas\InventoryBatchInfolist;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Tables\InventoryBatchesTable;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class InventoryBatchResource extends Resource
{
    protected static ?string $model = InventoryBatch::class;

    protected static ?string $tenantOwnershipRelationshipName = 'location.owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'batch_number';

    protected static ?string $navigationLabel = 'Batches';

    protected static ?string $modelLabel = 'Batch';

    protected static ?string $pluralModelLabel = 'Batches';

    /**
     * @return Builder<InventoryBatch>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = InventoryBatch::query()->with('location');

        return InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
    }

    public static function form(Schema $schema): Schema
    {
        return InventoryBatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryBatches::route('/'),
            'create' => CreateInventoryBatch::route('/create'),
            'view' => ViewInventoryBatch::route('/{record}'),
            'edit' => EditInventoryBatch::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->allocatable()->expiringSoon(30)->count();

        return $count > 0 ? (string) $count : null;
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
        return config('filament-inventory.resources.navigation_sort.batches', 50);
    }
}
