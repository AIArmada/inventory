<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventorySerialResource\Tables;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventorySerial;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InventorySerialsTable
{
    public static function configure(Table $table): Table
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
}
