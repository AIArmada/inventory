<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLocationResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Location Details')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Unique identifier for this location'),
                    ]),

                    TextInput::make('line1')
                        ->label('Address Line 1')
                        ->maxLength(255),

                    TextInput::make('line2')
                        ->label('Address Line 2')
                        ->maxLength(255),

                    Grid::make(3)->schema([
                        TextInput::make('city')
                            ->label('City')
                            ->maxLength(255),

                        TextInput::make('state')
                            ->label('State')
                            ->maxLength(255),

                        TextInput::make('postcode')
                            ->label('Postcode')
                            ->maxLength(20),
                    ]),

                    TextInput::make('country')
                        ->label('Country')
                        ->maxLength(2)
                        ->helperText('ISO 3166-1 alpha-2'),

                    Grid::make(2)->schema([
                        TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority locations are used first for allocation'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive locations are excluded from allocation'),
                    ]),
                ]),

            Section::make('Metadata')
                ->schema([
                    KeyValue::make('metadata')
                        ->label('Additional Data')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->addActionLabel('Add Field'),
                ])
                ->collapsed(),
        ]);
    }
}
