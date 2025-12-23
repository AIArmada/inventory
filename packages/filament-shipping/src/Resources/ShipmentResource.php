<?php

declare(strict_types=1);

namespace AIArmada\FilamentShipping\Resources;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentShipping\Actions;
use AIArmada\FilamentShipping\Resources\ShipmentResource\Pages;
use AIArmada\FilamentShipping\Resources\ShipmentResource\RelationManagers;
use AIArmada\Shipping\Enums\ShipmentStatus;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\ShippingManager;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string | UnitEnum | null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Shipment> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('shipping.features.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::resolve();
        if ($owner === null) {
            return $query->whereRaw('0 = 1');
        }

        /** @var Builder<Shipment> $scoped */
        $scoped = $query->forOwner($owner, includeGlobal: true);

        return $scoped;
    }

    public static function form(Schema $schema): Schema
    {
        $currency = (string) config('shipping.defaults.currency', 'MYR');
        $weightUnit = (string) config('shipping.defaults.weight_unit', 'g');

        return $schema
            ->schema([
                Section::make('Shipment Details')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('carrier_code')
                            ->label('Carrier')
                            ->options(fn () => static::getCarrierOptions())
                            ->required(),

                        Forms\Components\TextInput::make('service_code')
                            ->maxLength(50),

                        Forms\Components\Select::make('status')
                            ->options(collect(ShipmentStatus::cases())
                                ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()]))
                            ->required(),

                        Forms\Components\TextInput::make('tracking_number')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Origin Address')
                    ->schema([
                        Forms\Components\KeyValue::make('origin_address')
                            ->keyLabel('Field')
                            ->valueLabel('Value'),
                    ])
                    ->collapsible(),

                Section::make('Destination Address')
                    ->schema([
                        Forms\Components\KeyValue::make('destination_address')
                            ->keyLabel('Field')
                            ->valueLabel('Value'),
                    ])
                    ->collapsible(),

                Section::make('Package Info')
                    ->schema([
                        Forms\Components\TextInput::make('package_count')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),

                        Forms\Components\TextInput::make('total_weight')
                            ->numeric()
                            ->suffix($weightUnit)
                            ->formatStateUsing(fn ($state) => $state === null
                                ? null
                                : ($weightUnit === 'kg' ? $state / 1000 : $state))
                            ->dehydrateStateUsing(fn ($state) => $state === null
                                ? null
                                : ($weightUnit === 'kg' ? (int) round($state * 1000) : (int) $state)),

                        Forms\Components\TextInput::make('declared_value')
                            ->numeric()
                            ->prefix($currency)
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null),

                        Forms\Components\TextInput::make('shipping_cost')
                            ->numeric()
                            ->prefix($currency)
                            ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                            ->dehydrateStateUsing(fn ($state) => $state ? $state * 100 : null),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = (string) config('shipping.defaults.currency', 'MYR');
        $weightUnit = (string) config('shipping.defaults.weight_unit', 'g');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('carrier_code')
                    ->label('Carrier')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tracking_number')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Tracking number copied'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ShipmentStatus $state) => $state->getColor())
                    ->icon(fn (ShipmentStatus $state) => $state->getIcon()),

                Tables\Columns\TextColumn::make('total_weight')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state === null
                        ? '-'
                        : ($weightUnit === 'kg'
                            ? number_format($state / 1000, 2) . ' kg'
                            : number_format($state) . ' g'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('shipping_cost')
                    ->label('Cost')
                    ->money($currency, divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('shipped_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ShipmentStatus::cases())
                        ->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])),

                Tables\Filters\SelectFilter::make('carrier_code')
                    ->label('Carrier')
                    ->options(fn () => static::getCarrierOptions()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Actions\ShipAction::make(),
                Actions\PrintLabelAction::make(),
                Actions\CancelShipmentAction::make(),
                Actions\SyncTrackingAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    Actions\BulkShipAction::make(),
                    Actions\BulkPrintLabelsAction::make(),
                    Actions\BulkCancelAction::make(),
                    Actions\BulkSyncTrackingAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
            'create' => Pages\CreateShipment::route('/create'),
            'view' => Pages\ViewShipment::route('/{record}'),
            'edit' => Pages\EditShipment::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function getCarrierOptions(): array
    {
        $shipping = app(ShippingManager::class);

        return collect($shipping->getAvailableDrivers())
            ->mapWithKeys(fn ($driver) => [$driver => ucfirst($driver)])
            ->toArray();
    }
}
