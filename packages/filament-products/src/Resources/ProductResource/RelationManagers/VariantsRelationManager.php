<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers;

use AIArmada\Products\Models\Product;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'sku';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),

                TextInput::make('barcode')
                    ->label('Barcode')
                    ->maxLength(100),

                Section::make('Pricing')
                    ->schema([
                        TextInput::make('price')
                            ->label('Price Override')
                            ->numeric()
                            ->prefix('RM')
                            ->helperText('Leave blank to use product price'),

                        TextInput::make('compare_price')
                            ->label('Compare Price')
                            ->numeric()
                            ->prefix('RM'),

                        TextInput::make('cost')
                            ->label('Cost')
                            ->numeric()
                            ->prefix('RM'),
                    ])
                    ->columns(3),

                Section::make('Physical Attributes')
                    ->schema([
                        TextInput::make('weight')
                            ->label('Weight')
                            ->numeric()
                            ->suffix('kg'),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('length')
                                    ->label('Length')
                                    ->numeric()
                                    ->suffix('cm'),

                                TextInput::make('width')
                                    ->label('Width')
                                    ->numeric()
                                    ->suffix('cm'),

                                TextInput::make('height')
                                    ->label('Height')
                                    ->numeric()
                                    ->suffix('cm'),
                            ]),
                    ])
                    ->collapsible(),

                Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true),

                Toggle::make('is_default')
                    ->label('Default Variant'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('optionValues.name')
                    ->label('Options')
                    ->badge()
                    ->separator(', '),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('MYR', divideBy: 100)
                    ->placeholder('Uses product price'),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert prices to cents
                        if (isset($data['price']) && is_numeric($data['price'])) {
                            $data['price'] = (int) round(((float) $data['price']) * 100);
                        }
                        if (isset($data['compare_price']) && is_numeric($data['compare_price'])) {
                            $data['compare_price'] = (int) round(((float) $data['compare_price']) * 100);
                        }
                        if (isset($data['cost']) && is_numeric($data['cost'])) {
                            $data['cost'] = (int) round(((float) $data['cost']) * 100);
                        }

                        return $data;
                    }),
                \Filament\Actions\Action::make('generate_variants')
                    ->label('Generate All Variants')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Product Variants')
                    ->modalDescription('This will generate all possible variant combinations from the product options. Existing variants will be removed.')
                    ->action(function (): void {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();
                        $service = app(\AIArmada\Products\Services\VariantGeneratorService::class);
                        $variants = $service->generate($product);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Variants Generated')
                            ->body("Generated {$variants->count()} variant(s).")
                            ->send();
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        // Convert cents to display values
                        if (isset($data['price'])) {
                            $data['price'] /= 100;
                        }
                        if (isset($data['compare_price'])) {
                            $data['compare_price'] /= 100;
                        }
                        if (isset($data['cost'])) {
                            $data['cost'] /= 100;
                        }

                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert to cents before save
                        if (isset($data['price']) && is_numeric($data['price'])) {
                            $data['price'] = (int) round(((float) $data['price']) * 100);
                        }
                        if (isset($data['compare_price']) && is_numeric($data['compare_price'])) {
                            $data['compare_price'] = (int) round(((float) $data['compare_price']) * 100);
                        }
                        if (isset($data['cost']) && is_numeric($data['cost'])) {
                            $data['cost'] = (int) round(((float) $data['cost']) * 100);
                        }

                        return $data;
                    }),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('enable')
                        ->label('Enable')
                        ->icon('heroicon-o-check-circle')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_enabled' => true])
                        ),
                    \Filament\Actions\BulkAction::make('disable')
                        ->label('Disable')
                        ->icon('heroicon-o-x-circle')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_enabled' => false])
                        ),
                ]),
            ]);
    }
}
