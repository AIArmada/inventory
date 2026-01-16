<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryBatchResource\Schemas;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\BatchStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class InventoryBatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Batch Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('batch_number')
                                ->label('Batch Number')
                                ->required()
                                ->maxLength(100)
                                ->unique(ignoreRecord: true),

                            TextInput::make('lot_number')
                                ->label('Lot Number')
                                ->maxLength(100),

                            Select::make('location_id')
                                ->label('Location')
                                ->relationship(
                                    name: 'location',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('status')
                                ->label('Status')
                                ->options(collect(BatchStatus::cases())->mapWithKeys(
                                    fn (BatchStatus $status) => [$status->value => $status->label()]
                                ))
                                ->required()
                                ->default(BatchStatus::Active->value),
                        ]),
                ]),

            Section::make('Quantities')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('quantity_received')
                                ->label('Quantity Received')
                                ->numeric()
                                ->required()
                                ->minValue(0),

                            TextInput::make('quantity_on_hand')
                                ->label('Quantity On Hand')
                                ->numeric()
                                ->required()
                                ->minValue(0),

                            TextInput::make('quantity_reserved')
                                ->label('Quantity Reserved')
                                ->numeric()
                                ->default(0)
                                ->minValue(0),
                        ]),
                ]),

            Section::make('Dates')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            DatePicker::make('manufactured_at')
                                ->label('Manufactured Date'),

                            DatePicker::make('expires_at')
                                ->label('Expiry Date'),

                            DatePicker::make('received_at')
                                ->label('Received Date')
                                ->default(now()),
                        ]),
                ]),

            Section::make('Additional Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('supplier_batch_number')
                                ->label('Supplier Batch Number')
                                ->maxLength(100),

                            TextInput::make('unit_cost_minor')
                                ->label('Unit Cost (Minor)')
                                ->numeric()
                                ->prefix(config('inventory.defaults.currency', 'MYR')),
                        ]),

                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3),
                ])
                ->collapsible(),
        ]);
    }
}
