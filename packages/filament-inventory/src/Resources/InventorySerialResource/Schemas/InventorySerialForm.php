<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventorySerialResource\Schemas;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class InventorySerialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Serial Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('serial_number')
                                ->label('Serial Number')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),

                            Select::make('location_id')
                                ->label('Location')
                                ->relationship(
                                    name: 'location',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('batch_id')
                                ->label('Batch')
                                ->relationship(
                                    name: 'batch',
                                    titleAttribute: 'batch_number',
                                    modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location'),
                                )
                                ->searchable()
                                ->preload(),

                            Select::make('status')
                                ->label('Status')
                                ->options(collect(SerialStatus::cases())->mapWithKeys(
                                    fn (SerialStatus $status) => [$status->value => $status->label()]
                                ))
                                ->required()
                                ->default(SerialStatus::Available->value),

                            Select::make('condition')
                                ->label('Condition')
                                ->options(collect(SerialCondition::cases())->mapWithKeys(
                                    fn (SerialCondition $condition) => [$condition->value => $condition->label()]
                                ))
                                ->required()
                                ->default(SerialCondition::New->value),
                        ]),
                ]),

            Section::make('Cost & Warranty')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('unit_cost_minor')
                                ->label('Unit Cost (Minor)')
                                ->numeric()
                                ->prefix(config('inventory.defaults.currency', 'MYR')),

                            DatePicker::make('warranty_expires_at')
                                ->label('Warranty Expires'),
                        ]),
                ]),

            Section::make('Order Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('order_id')
                                ->label('Order ID')
                                ->maxLength(36),

                            TextInput::make('customer_id')
                                ->label('Customer ID')
                                ->maxLength(36),
                        ]),
                ])
                ->collapsible(),

            Section::make('Dates')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            DatePicker::make('received_at')
                                ->label('Received Date')
                                ->default(now()),

                            DatePicker::make('sold_at')
                                ->label('Sold Date'),
                        ]),
                ])
                ->collapsible(),
        ]);
    }
}
