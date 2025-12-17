<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLevelResource\Schemas;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\AllocationStrategy;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class InventoryLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Stock Level Settings')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('location_id')
                            ->label('Location')
                            ->relationship(
                                name: 'location',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(),

                        TextInput::make('inventoryable_type')
                            ->label('Product Type')
                            ->disabled(),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('quantity_on_hand')
                            ->label('On Hand')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Total physical stock at this location'),

                        TextInput::make('quantity_reserved')
                            ->label('Reserved')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->disabled()
                            ->helperText('Stock reserved for pending orders'),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('reorder_point')
                            ->label('Reorder Point')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Alert when available stock falls below this'),

                        Select::make('allocation_strategy')
                            ->label('Allocation Strategy')
                            ->options(AllocationStrategy::class)
                            ->placeholder('Use global default')
                            ->helperText('Override global allocation strategy for this SKU'),
                    ]),
                ]),
        ]);
    }
}
