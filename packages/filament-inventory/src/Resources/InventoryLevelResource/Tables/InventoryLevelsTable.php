<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLevelResource\Tables;

use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryLevel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InventoryLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('inventoryable_type')
                    ->label('Type')
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

                TextColumn::make('quantity_on_hand')
                    ->label('On Hand')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->color('warning'),

                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (InventoryLevel $record): int => $record->getAvailableQuantity())
                    ->numeric()
                    ->alignCenter()
                    ->color(fn (int $state, InventoryLevel $record): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= $record->reorder_point => 'warning',
                        default => 'success',
                    })
                    ->weight('bold'),

                TextColumn::make('reorder_point')
                    ->label('Reorder Pt')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('allocation_strategy')
                    ->label('Strategy')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Default')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship(
                        name: 'location',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => InventoryOwnerScope::applyToLocationQuery($query),
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('allocation_strategy')
                    ->label('Strategy')
                    ->options(AllocationStrategy::class),

                SelectFilter::make('stock_status')
                    ->label('Stock Status')
                    ->options([
                        'in_stock' => 'In Stock',
                        'low_stock' => 'Low Stock',
                        'out_of_stock' => 'Out of Stock',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'in_stock' => $query->whereRaw('quantity_on_hand - quantity_reserved > reorder_point'),
                            'low_stock' => $query->whereRaw('quantity_on_hand - quantity_reserved <= reorder_point AND quantity_on_hand - quantity_reserved > 0'),
                            'out_of_stock' => $query->whereRaw('quantity_on_hand - quantity_reserved <= 0'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([25, 50, 100])
            ->striped();
    }
}
