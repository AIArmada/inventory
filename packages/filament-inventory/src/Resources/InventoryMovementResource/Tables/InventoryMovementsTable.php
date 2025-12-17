<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryMovementResource\Tables;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\MovementType;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match (MovementType::from($state)) {
                        MovementType::Receipt => 'success',
                        MovementType::Shipment => 'info',
                        MovementType::Transfer => 'warning',
                        MovementType::Adjustment => 'gray',
                        MovementType::Allocation => 'primary',
                        MovementType::Release => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('inventoryable_type')
                    ->label('Product Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('inventoryable_id')
                    ->label('Product ID')
                    ->limit(8)
                    ->tooltip(fn (string $state): string => $state)
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('fromLocation.name')
                    ->label('From')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('toLocation.name')
                    ->label('To')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->alignCenter()
                    ->formatStateUsing(fn (int $state): string => $state >= 0 ? "+{$state}" : (string) $state)
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(30)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),

                TextColumn::make('reference_type')
                    ->label('Ref Type')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user_id')
                    ->label('User')
                    ->limit(8)
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Movement Type')
                    ->options(MovementType::class),

                SelectFilter::make('from_location_id')
                    ->label('From Location')
                    ->relationship(
                        name: 'fromLocation',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('to_location_id')
                    ->label('To Location')
                    ->relationship(
                        name: 'toLocation',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                    )
                    ->searchable()
                    ->preload(),

                Filter::make('date_range')
                    ->form([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->striped();
    }
}
