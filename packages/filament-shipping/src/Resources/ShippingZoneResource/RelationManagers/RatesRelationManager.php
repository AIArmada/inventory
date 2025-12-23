<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources\ShippingZoneResource\RelationManagers;

use AIArmada\Shipping\Models\ShippingRate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('method_code')
                    ->required()
                    ->maxLength(50),

                Forms\Components\Select::make('carrier_code')
                    ->options([
                        'jnt' => 'J&T Express',
                        'flat_rate' => 'Flat Rate',
                        'manual' => 'Manual',
                    ])
                    ->placeholder('All carriers'),

                Forms\Components\Select::make('calculation_type')
                    ->options([
                        'flat' => 'Flat Rate',
                        'per_kg' => 'Per Kilogram',
                        'per_item' => 'Per Item',
                        'percentage' => 'Percentage of Order',
                        'table' => 'Table Based',
                    ])
                    ->required()
                    ->live(),

                Forms\Components\TextInput::make('base_rate')
                    ->numeric()
                    ->prefix('RM')
                    ->required()
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : 0),

                Forms\Components\TextInput::make('per_unit_rate')
                    ->numeric()
                    ->prefix('RM')
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : 0)
                    ->visible(fn (Get $get) => in_array($get('calculation_type'), ['per_kg', 'per_item', 'percentage'])),

                Forms\Components\TextInput::make('min_charge')
                    ->numeric()
                    ->prefix('RM')
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null),

                Forms\Components\TextInput::make('max_charge')
                    ->numeric()
                    ->prefix('RM')
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null),

                Forms\Components\TextInput::make('free_shipping_threshold')
                    ->numeric()
                    ->prefix('RM')
                    ->helperText('Orders above this amount get free shipping')
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null),

                Forms\Components\Repeater::make('rate_table')
                    ->schema([
                        Forms\Components\TextInput::make('min_weight')
                            ->numeric()
                            ->suffix('g')
                            ->required(),
                        Forms\Components\TextInput::make('max_weight')
                            ->numeric()
                            ->suffix('g')
                            ->required(),
                        Forms\Components\TextInput::make('rate')
                            ->numeric()
                            ->prefix('RM')
                            ->required()
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : 0),
                    ])
                    ->columns(3)
                    ->visible(fn (Get $get) => $get('calculation_type') === 'table'),

                Grid::make()
                    ->schema([
                        Forms\Components\TextInput::make('estimated_days_min')
                            ->label('Min Days')
                            ->numeric(),
                        Forms\Components\TextInput::make('estimated_days_max')
                            ->label('Max Days')
                            ->numeric(),
                    ])
                    ->columns(2),

                Forms\Components\Toggle::make('active')
                    ->default(true),

                Forms\Components\Textarea::make('description')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        $canManageRates = function (): bool {
            $user = auth()->user();

            if ($user === null) {
                return false;
            }

            $owner = $this->getOwnerRecord();
            if (! $owner instanceof Model) {
                return false;
            }

            return $user->can('manageRates', $owner);
        };

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('method_code')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('carrier_code')
                    ->badge()
                    ->color('info')
                    ->placeholder('All'),

                Tables\Columns\TextColumn::make('calculation_type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'flat' => 'success',
                        'per_kg' => 'info',
                        'per_item' => 'warning',
                        'percentage' => 'primary',
                        'table' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('base_rate')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivery_estimate')
                    ->label('Delivery')
                    ->getStateUsing(fn (ShippingRate $record) => $record->getDeliveryEstimate())
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),

                Tables\Filters\SelectFilter::make('calculation_type')
                    ->options([
                        'flat' => 'Flat Rate',
                        'per_kg' => 'Per Kilogram',
                        'per_item' => 'Per Item',
                        'percentage' => 'Percentage',
                        'table' => 'Table Based',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize($canManageRates),
            ])
            ->actions([
                EditAction::make()
                    ->authorize($canManageRates),
                DeleteAction::make()
                    ->authorize($canManageRates),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize($canManageRates),
                ]),
            ]);
    }
}
