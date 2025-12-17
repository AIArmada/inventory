<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources;

use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages\CreateInventoryBatch;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages\EditInventoryBatch;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages\ListInventoryBatches;
use AIArmada\FilamentInventory\Resources\InventoryBatchResource\Pages\ViewInventoryBatch;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class InventoryBatchResource extends Resource
{
    protected static ?string $model = InventoryBatch::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'batch_number';

    protected static ?string $navigationLabel = 'Batches';

    protected static ?string $modelLabel = 'Batch';

    protected static ?string $pluralModelLabel = 'Batches';

    /**
     * @return Builder<InventoryBatch>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = InventoryBatch::query()->with('location');

        return InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                                TextInput::make('initial_quantity')
                                    ->label('Initial Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0),

                                TextInput::make('current_quantity')
                                    ->label('Current Quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0),

                                TextInput::make('reserved_quantity')
                                    ->label('Reserved Quantity')
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

                                TextInput::make('cost_per_unit_minor')
                                    ->label('Cost per Unit (Minor)')
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('lot_number')
                    ->label('Lot')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('inventoryable_type')
                    ->label('Product Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('No location')
                    ->sortable(),

                TextColumn::make('current_quantity')
                    ->label('Current')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('available_quantity')
                    ->label('Available')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('reserved_quantity')
                    ->label('Reserved')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->color(fn (?InventoryBatch $record): string => match (true) {
                        $record?->expires_at === null => 'gray',
                        $record->isExpired() => 'danger',
                        $record->daysUntilExpiry() <= 7 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InventoryBatch $record): string => $record->getStatusEnum()->color()),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BatchStatus::cases())->mapWithKeys(
                        fn (BatchStatus $status) => [$status->value => $status->label()]
                    )),

                SelectFilter::make('location')
                    ->relationship(
                        name: 'location',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                    ),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryBatches::route('/'),
            'create' => CreateInventoryBatch::route('/create'),
            'view' => ViewInventoryBatch::route('/{record}'),
            'edit' => EditInventoryBatch::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->allocatable()->expiringSoon(30)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-inventory.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-inventory.resources.navigation_sort.batches', 50);
    }
}
