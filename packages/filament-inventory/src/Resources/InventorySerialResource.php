<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources;

use AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages\CreateInventorySerial;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages\EditInventorySerial;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages\ListInventorySerials;
use AIArmada\FilamentInventory\Resources\InventorySerialResource\Pages\ViewInventorySerial;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventorySerial;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
use Illuminate\Database\Eloquent\Relations\Relation;
use UnitEnum;

final class InventorySerialResource extends Resource
{
    protected static ?string $model = InventorySerial::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $recordTitleAttribute = 'serial_number';

    protected static ?string $navigationLabel = 'Serial Numbers';

    protected static ?string $modelLabel = 'Serial';

    protected static ?string $pluralModelLabel = 'Serial Numbers';

    public static function getEloquentQuery(): Builder
    {
        $query = InventorySerial::query()->with([
            'location',
            'batch' => static function (Relation $relation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($relation->getQuery(), 'location');
            },
        ]);

        return InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('inventoryable_type')
                    ->label('Product Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('No location')
                    ->sortable(),

                TextColumn::make('batch.batch_number')
                    ->label('Batch')
                    ->placeholder('No batch')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InventorySerial $record): string => $record->getStatusEnum()->color()),

                TextColumn::make('condition')
                    ->badge()
                    ->color(fn (InventorySerial $record): string => $record->getConditionEnum()->color()),

                TextColumn::make('unit_cost_minor')
                    ->label('Cost')
                    ->money(config('inventory.defaults.currency', 'MYR'), divideBy: 100)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('warranty_expires_at')
                    ->label('Warranty')
                    ->date()
                    ->placeholder('No warranty')
                    ->color(fn (?InventorySerial $record): string => match (true) {
                        $record?->warranty_expires_at === null => 'gray',
                        ! $record->isUnderWarranty() => 'danger',
                        $record->warrantyDaysRemaining() <= 30 => 'warning',
                        default => 'success',
                    })
                    ->toggleable(),

                TextColumn::make('order_id')
                    ->label('Order')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(SerialStatus::cases())->mapWithKeys(
                        fn (SerialStatus $status) => [$status->value => $status->label()]
                    )),

                SelectFilter::make('condition')
                    ->options(collect(SerialCondition::cases())->mapWithKeys(
                        fn (SerialCondition $condition) => [$condition->value => $condition->label()]
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
            'index' => ListInventorySerials::route('/'),
            'create' => CreateInventorySerial::route('/create'),
            'view' => ViewInventorySerial::route('/{record}'),
            'edit' => EditInventorySerial::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getEloquentQuery()->where('status', SerialStatus::Available->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-inventory.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-inventory.resources.navigation_sort.serials', 60);
    }
}
