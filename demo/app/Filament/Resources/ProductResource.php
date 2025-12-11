<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::ShoppingBag;

    protected static string | UnitEnum | null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        RichEditor::make('description')
                            ->columnSpanFull(),

                        TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),

                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),

                Section::make('Pricing')
                    ->schema([
                        TextInput::make('price')
                            ->numeric()
                            ->prefix('RM')
                            ->required()
                            ->helperText('Price in cents (e.g., 1000 = RM 10.00)'),

                        TextInput::make('compare_at_price')
                            ->numeric()
                            ->prefix('RM')
                            ->helperText('Original price for showing discounts'),

                        TextInput::make('currency')
                            ->default('MYR')
                            ->maxLength(3),
                    ])
                    ->columns(3),

                Section::make('Inventory')
                    ->schema([
                        Checkbox::make('track_stock')
                            ->default(true),

                        TextInput::make('available_stock')
                            ->label('Available (all locations)')
                            ->numeric()
                            ->default(0)
                            ->dehydrated(false)
                            ->disabled()
                            ->formatStateUsing(fn (?Product $record): int => $record?->available_stock ?? 0),

                        TextInput::make('low_stock_threshold')
                            ->numeric()
                            ->default(5),
                    ])
                    ->columns(3),

                Section::make('Status')
                    ->schema([
                        Checkbox::make('is_active')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),

                TextColumn::make('category.name')
                    ->sortable(),

                TextColumn::make('formatted_price')
                    ->label('Price'),

                TextColumn::make('available_stock')
                    ->label('Inventory')
                    ->sortable()
                    ->state(fn (Product $record): int => $record->available_stock)
                    ->color(fn (Product $record): string => $record->isLowInventory() ? 'warning' : ($record->isOutOfStock() ? 'danger' : 'success')),

                IconColumn::make('is_active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name'),

                TernaryFilter::make('is_active'),

                TernaryFilter::make('track_stock'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
