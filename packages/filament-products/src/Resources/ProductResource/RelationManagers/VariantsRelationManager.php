<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $recordTitleAttribute = 'sku';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100),

                Forms\Components\TextInput::make('barcode')
                    ->label('Barcode')
                    ->maxLength(100),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Price Override')
                            ->numeric()
                            ->prefix('RM')
                            ->helperText('Leave blank to use product price'),

                        Forms\Components\TextInput::make('compare_price')
                            ->label('Compare Price')
                            ->numeric()
                            ->prefix('RM'),

                        Forms\Components\TextInput::make('cost')
                            ->label('Cost')
                            ->numeric()
                            ->prefix('RM'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Physical Attributes')
                    ->schema([
                        Forms\Components\TextInput::make('weight')
                            ->label('Weight')
                            ->numeric()
                            ->suffix('kg'),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('length')
                                    ->label('Length')
                                    ->numeric()
                                    ->suffix('cm'),

                                Forms\Components\TextInput::make('width')
                                    ->label('Width')
                                    ->numeric()
                                    ->suffix('cm'),

                                Forms\Components\TextInput::make('height')
                                    ->label('Height')
                                    ->numeric()
                                    ->suffix('cm'),
                            ]),
                    ])
                    ->collapsible(),

                Forms\Components\Toggle::make('is_enabled')
                    ->label('Enabled')
                    ->default(true),

                Forms\Components\Toggle::make('is_default')
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
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert prices to cents
                        if (isset($data['price']) && is_numeric($data['price'])) {
                            $data['price'] = (int) ($data['price'] * 100);
                        }
                        if (isset($data['compare_price']) && is_numeric($data['compare_price'])) {
                            $data['compare_price'] = (int) ($data['compare_price'] * 100);
                        }
                        if (isset($data['cost']) && is_numeric($data['cost'])) {
                            $data['cost'] = (int) ($data['cost'] * 100);
                        }

                        return $data;
                    }),
                Tables\Actions\Action::make('generate_variants')
                    ->label('Generate All Variants')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Product Variants')
                    ->modalDescription('This will generate all possible variant combinations from the product options. Existing variants will be removed.')
                    ->action(function (): void {
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
                Tables\Actions\EditAction::make()
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
                            $data['price'] = (int) ($data['price'] * 100);
                        }
                        if (isset($data['compare_price']) && is_numeric($data['compare_price'])) {
                            $data['compare_price'] = (int) ($data['compare_price'] * 100);
                        }
                        if (isset($data['cost']) && is_numeric($data['cost'])) {
                            $data['cost'] = (int) ($data['cost'] * 100);
                        }

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('enable')
                        ->label('Enable')
                        ->icon('heroicon-o-check-circle')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_enabled' => true])
                        ),
                    Tables\Actions\BulkAction::make('disable')
                        ->label('Disable')
                        ->icon('heroicon-o-x-circle')
                        ->action(
                            fn (\Illuminate\Support\Collection $records) => $records->each->update(['is_enabled' => false])
                        ),
                ]),
            ]);
    }
}
