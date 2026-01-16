<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources;

use AIArmada\FilamentInventory\Resources\InventoryAllocationResource\Pages\ListInventoryAllocations;
use AIArmada\FilamentInventory\Resources\InventoryAllocationResource\Pages\ViewInventoryAllocation;
use AIArmada\FilamentInventory\Resources\InventoryAllocationResource\Schemas\InventoryAllocationInfolist;
use AIArmada\FilamentInventory\Resources\InventoryAllocationResource\Tables\InventoryAllocationsTable;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryAllocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class InventoryAllocationResource extends Resource
{
    protected static ?string $model = InventoryAllocation::class;

    protected static ?string $tenantOwnershipRelationshipName = 'location.owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Allocations';

    protected static ?string $modelLabel = 'Allocation';

    protected static ?string $pluralModelLabel = 'Allocations';

    public static function getEloquentQuery(): Builder
    {
        $query = InventoryAllocation::query()->with(['location', 'level']);

        return InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryAllocationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryAllocationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryAllocations::route('/'),
            'view' => ViewInventoryAllocation::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $expiredCount = self::getEloquentQuery()
            ->where('expires_at', '<', now())
            ->count();

        return $expiredCount > 0 ? (string) $expiredCount : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-inventory.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-inventory.resources.navigation_sort.allocations', 40);
    }
}
