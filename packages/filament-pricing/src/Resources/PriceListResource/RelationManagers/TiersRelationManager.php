<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Resources\PriceListResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';

    protected static ?string $title = 'Price Tiers';

    protected static ?string $recordTitleAttribute = 'min_quantity';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tier Configuration')
                    ->schema([
                        Forms\Components\Select::make('priceable_type')
                            ->label('Apply To')
                            ->options([
                                'AIArmada\\Products\\Models\\Product' => 'Product',
                                'AIArmada\\Products\\Models\\Variant' => 'Variant',
                            ])
                            ->required()
                            ->live()
                            ->default('AIArmada\\Products\\Models\\Product'),

                        Forms\Components\Select::make('priceable_id')
                            ->label('Product/Variant')
                            ->searchable()
                            ->required()
                            ->options(function (Forms\Get $get) {
                                $type = $get('priceable_type');

                                if ($type === 'AIArmada\\Products\\Models\\Product') {
                                    return \AIArmada\Products\Models\Product::pluck('name', 'id');
                                }

                                if ($type === 'AIArmada\\Products\\Models\\Variant') {
                                    return \AIArmada\Products\Models\Variant::with('product')
                                        ->get()
                                        ->mapWithKeys(fn ($v) => [
                                            $v->id => $v->product->name . ' - ' . $v->sku,
                                        ]);
                                }

                                return [];
                            }),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('min_quantity')
                                    ->label('Minimum Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1)
                                    ->helperText('Start of this tier range'),

                                Forms\Components\TextInput::make('max_quantity')
                                    ->label('Maximum Quantity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->helperText('Leave empty for unlimited')
                                    ->nullable(),

                                Forms\Components\Placeholder::make('range_display')
                                    ->label('Range')
                                    ->content(function (Forms\Get $get) {
                                        $min = $get('min_quantity') ?? 1;
                                        $max = $get('max_quantity');

                                        return $max ? "{$min} - {$max}" : "{$min}+";
                                    }),
                            ]),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\Radio::make('pricing_type')
                            ->label('Pricing Type')
                            ->options([
                                'fixed_price' => 'Fixed Price',
                                'percentage_discount' => 'Percentage Discount',
                                'fixed_discount' => 'Fixed Discount',
                            ])
                            ->required()
                            ->live()
                            ->default('fixed_price')
                            ->inline()
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('price')
                            ->label('Price per Unit')
                            ->numeric()
                            ->prefix('RM')
                            ->required()
                            ->visible(fn (Forms\Get $get) => $get('pricing_type') === 'fixed_price')
                            ->helperText('Price for each unit in this tier'),

                        Forms\Components\TextInput::make('discount_percentage')
                            ->label('Discount Percentage')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->visible(fn (Forms\Get $get) => $get('pricing_type') === 'percentage_discount')
                            ->helperText('Percentage off the original price'),

                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount Amount')
                            ->numeric()
                            ->prefix('RM')
                            ->required()
                            ->minValue(0)
                            ->visible(fn (Forms\Get $get) => $get('pricing_type') === 'fixed_discount')
                            ->helperText('Fixed amount off per unit'),

                        Forms\Components\Textarea::make('description')
                            ->label('Tier Description')
                            ->rows(2)
                            ->placeholder('e.g., Bulk discount for large orders')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('min_quantity')
            ->columns([
                Tables\Columns\TextColumn::make('priceable.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(
                        fn ($record) => $record->priceable_type === 'AIArmada\\Products\\Models\\Variant'
                        ? 'Variant: ' . $record->priceable->sku
                        : null
                    ),

                Tables\Columns\TextColumn::make('quantity_range')
                    ->label('Quantity Range')
                    ->state(function ($record) {
                        return $record->max_quantity
                            ? "{$record->min_quantity} - {$record->max_quantity}"
                            : "{$record->min_quantity}+";
                    })
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('pricing_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'fixed_price' => 'Fixed Price',
                        'percentage_discount' => '% Discount',
                        'fixed_discount' => 'Fixed Discount',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fixed_price' => 'success',
                        'percentage_discount' => 'warning',
                        'fixed_discount' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('MYR')
                    ->visible(fn ($record) => $record->pricing_type === 'fixed_price'),

                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label('Discount')
                    ->suffix('%')
                    ->visible(fn ($record) => $record->pricing_type === 'percentage_discount'),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('MYR')
                    ->visible(fn ($record) => $record->pricing_type === 'fixed_discount'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->description)
                    ->toggleable(),
            ])
            ->defaultSort('min_quantity', 'asc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert cents to display format if needed
                        if (isset($data['price'])) {
                            $data['price'] *= 100;
                        }
                        if (isset($data['discount_amount'])) {
                            $data['discount_amount'] *= 100;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert to cents
                        if (isset($data['price'])) {
                            $data['price'] *= 100;
                        }
                        if (isset($data['discount_amount'])) {
                            $data['discount_amount'] *= 100;
                        }

                        return $data;
                    })
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Convert from cents for display
                        if (isset($data['price'])) {
                            $data['price'] /= 100;
                        }
                        if (isset($data['discount_amount'])) {
                            $data['discount_amount'] /= 100;
                        }

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
