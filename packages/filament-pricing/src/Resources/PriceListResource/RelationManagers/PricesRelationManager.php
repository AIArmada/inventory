<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Prices';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\MorphToSelect::make('priceable')
                    ->label('Product/Variant')
                    ->types([
                        Forms\Components\MorphToSelect\Type::make(\AIArmada\Products\Models\Product::class)
                            ->titleAttribute('name')
                            ->label('Product'),
                        Forms\Components\MorphToSelect\Type::make(\AIArmada\Products\Models\Variant::class)
                            ->titleAttribute('sku')
                            ->label('Variant'),
                    ])
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('amount')
                    ->label('Price (cents)')
                    ->numeric()
                    ->required()
                    ->helperText('Enter price in cents (e.g., 1000 = RM 10.00)'),

                Forms\Components\TextInput::make('compare_amount')
                    ->label('Compare Price (cents)')
                    ->numeric()
                    ->helperText('Original price for strike-through display'),

                Forms\Components\TextInput::make('min_quantity')
                    ->label('Minimum Quantity')
                    ->numeric()
                    ->default(1),

                Forms\Components\Select::make('currency')
                    ->options([
                        'MYR' => 'MYR',
                        'USD' => 'USD',
                        'SGD' => 'SGD',
                    ])
                    ->default('MYR'),

                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Start Date'),

                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('End Date'),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('priceable_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => class_basename($state)),

                Tables\Columns\TextColumn::make('priceable.name')
                    ->label('Product')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Price')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('compare_amount')
                    ->label('Compare')
                    ->money('MYR', divideBy: 100)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('min_quantity')
                    ->label('Min Qty')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y')
                    ->placeholder('Always'),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y')
                    ->placeholder('Never'),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
