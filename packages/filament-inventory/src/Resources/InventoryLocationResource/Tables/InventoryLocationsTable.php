<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory\Resources\InventoryLocationResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class InventoryLocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->copyable(),

                TextColumn::make('line1')
                    ->label('Address Line 1')
                    ->limit(30)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('inventory_levels_count')
                    ->label('SKUs')
                    ->counts('inventoryLevels')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->actions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye),

                EditAction::make()
                    ->icon(Heroicon::OutlinedPencil),

                DeleteAction::make()
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->requiresConfirmation(),
                ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('priority', 'desc')
            ->paginated([25, 50, 100])
            ->striped();
    }
}
